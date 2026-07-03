<?php

namespace App\Modules\Selloff\Escrow\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Cart\Services\CommerceGtmService;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowStatus;
use App\Services\Media\MediaUploadService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EscrowService
{
    public function __construct(
        private readonly EscrowNotificationService $notifications,
        private readonly CommerceGtmService $gtm,
        private readonly EscrowWorkflowService $workflow,
        private readonly EscrowFundingService $funding,
        private readonly EscrowReleaseService $release,
        private readonly EscrowDisputeService $disputes,
        private readonly EscrowPricingService $pricing,
    ) {}

    /**
     * @return list<string>
     */
    private function escrowRelations(): array
    {
        return ['buyer', 'seller', 'product.translations', 'product.category', 'product.images'];
    }

    public function initiate(User $buyer, int $productId): EscrowTransaction
    {
        $product = Product::query()->with(['vendor', 'translations', 'category'])->findOrFail($productId);

        $listingType = (string) ($product->listing_type ?? 'sell_on_site');
        if (in_array($listingType, ['sell_on_site', 'license_key'], true)) {
            throw ValidationException::withMessages([
                'product_id' => ['Escrow is only available for classified and quote-based listings.'],
            ]);
        }

        $existing = EscrowTransaction::query()
            ->where('product_id', $product->id)
            ->where('buyer_id', $buyer->id)
            ->where('seller_id', $product->vendor_id)
            ->whereNotIn('status', [EscrowStatus::COMPLETED, EscrowStatus::CANCELLED, EscrowStatus::REFUNDED, 'completed', 'cancelled'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'product_id' => ['You have an existing escrow transaction for this item.'],
            ]);
        }

        $amount = (float) $product->price;
        $commissionRate = (float) ($product->category?->escrow_commission_rate ?? 0);
        $commission = round($amount * $commissionRate / 100, 2);

        $transaction = EscrowTransaction::query()->create([
            'ref' => strtoupper(Str::random(10)),
            'buyer_id' => $buyer->id,
            'seller_id' => $product->vendor_id,
            'product_id' => $product->id,
            'amount' => $amount,
            'commission_amount' => $commission,
            'seller_amount' => $amount - $commission,
            'currency_code' => $product->currency_code ?? 'NGN',
            'status' => EscrowStatus::PENDING_AGREEMENT,
            'buyer_email' => $buyer->email,
            'seller_email' => $product->vendor?->email,
            'buyer_agreement_token' => Str::random(40),
            'seller_agreement_token' => Str::random(40),
        ]);

        $this->workflow->recordEvent($transaction, 'initiated', 'buyer', $buyer->id, [
            'product_id' => $product->id,
        ]);

        $this->notifications->sendBuyerAgreement($transaction);
        $this->notifications->sendSellerAgreement($transaction);

        return $transaction;
    }

    public function findByAgreementToken(string $token): ?EscrowTransaction
    {
        return EscrowTransaction::query()
            ->with($this->escrowRelations())
            ->where(function ($query) use ($token): void {
                $query->where('buyer_agreement_token', $token)
                    ->orWhere('seller_agreement_token', $token);
            })
            ->first();
    }

    public function confirmByToken(string $token): EscrowTransaction
    {
        $transaction = $this->findByAgreementToken($token);

        if (! $transaction) {
            abort(404, 'Escrow transaction was not found.');
        }

        if ($transaction->buyer_agreement_token === $token && ! $transaction->buyer_agreed) {
            $transaction->update(['buyer_agreed' => true, 'status' => 'buyer_agreed']);
            $this->workflow->recordEvent($transaction, 'buyer_agreed', 'buyer', $transaction->buyer_id);
        } elseif ($transaction->seller_agreement_token === $token && ! $transaction->seller_agreed) {
            $transaction->update(['seller_agreed' => true, 'status' => 'seller_agreed']);
            $this->workflow->recordEvent($transaction, 'seller_agreed', 'seller', $transaction->seller_id);
            $this->notifications->sendNewEscrowToAdmin($transaction->fresh($this->escrowRelations()));
        }

        $transaction = $transaction->fresh($this->escrowRelations());

        if ($transaction->buyer_agreed && $transaction->seller_agreed && ! in_array($transaction->status, [EscrowStatus::AWAITING_FUNDING, EscrowStatus::FUNDED, EscrowStatus::COMPLETED], true)) {
            $transaction->update(['status' => EscrowStatus::AWAITING_FUNDING]);
            $this->workflow->recordEvent($transaction, 'contract_initiated', 'system', null);
            $transaction = $transaction->fresh($this->escrowRelations());
        }

        return $transaction;
    }

    public function disputeByToken(string $token, ?string $reason = null): EscrowTransaction
    {
        $transaction = $this->findByAgreementToken($token);

        if (! $transaction) {
            abort(404, 'Escrow transaction was not found.');
        }

        abort_unless($transaction->buyer_agreed && $transaction->seller_agreed, 422, 'Both parties must agree before raising a dispute.');
        abort_if(in_array($transaction->status, [EscrowStatus::COMPLETED, EscrowStatus::CANCELLED, EscrowStatus::REFUNDED, EscrowStatus::DISPUTED, 'completed', 'cancelled', 'disputed'], true), 422, 'This escrow transaction cannot be disputed.');

        $role = $this->resolveViewerRole($transaction, $token);
        abort_if($role === 'unknown', 403, 'Invalid agreement token.');

        $metadata = $transaction->metadata ?? [];
        $metadata['dispute'] = [
            'reason' => $reason,
            'raised_by' => $role,
            'raised_at' => now()->toIso8601String(),
        ];

        $transaction->update([
            'status' => EscrowStatus::DISPUTED,
            'metadata' => $metadata,
        ]);

        $this->workflow->recordEvent($transaction, 'dispute_opened', $role, $role === 'buyer' ? $transaction->buyer_id : $transaction->seller_id, [
            'reason' => $reason,
        ]);

        return $transaction->fresh($this->escrowRelations());
    }

    public function confirmShippedByToken(string $token): EscrowTransaction
    {
        $transaction = $this->findByAgreementToken($token);

        if (! $transaction) {
            abort(404, 'Escrow transaction was not found.');
        }

        abort_unless($this->resolveViewerRole($transaction, $token) === 'seller', 403, 'Only the seller can confirm shipment.');
        abort_unless($transaction->buyer_agreed && $transaction->seller_agreed, 422, 'Both parties must agree before confirming shipment.');
        abort_unless($transaction->payment_received, 422, 'Buyer payment must be recorded before confirming shipment.');
        abort_if($transaction->seller_shipped_item, 422, 'Shipment has already been confirmed.');
        abort_if(in_array($transaction->status, [EscrowStatus::COMPLETED, EscrowStatus::CANCELLED, EscrowStatus::REFUNDED, EscrowStatus::DISPUTED, 'completed', 'cancelled', 'disputed'], true), 422, 'This escrow transaction cannot be updated.');

        $transaction->update([
            'seller_shipped_item' => true,
            'shipped_at' => now(),
            'status' => EscrowStatus::SHIPPED,
        ]);
        $transaction = $transaction->fresh($this->escrowRelations());

        $this->workflow->recordEvent($transaction, 'seller_shipped', 'seller', $transaction->seller_id);
        $this->notifications->sendItemShippedToBuyer($transaction);
        $this->notifications->sendItemShippedToAdmin($transaction);

        return $transaction;
    }

    public function confirmDeliveryByToken(string $token): EscrowTransaction
    {
        $transaction = $this->findByAgreementToken($token);

        if (! $transaction) {
            abort(404, 'Escrow transaction was not found.');
        }

        abort_unless($this->resolveViewerRole($transaction, $token) === 'buyer', 403, 'Only the buyer can confirm delivery.');
        abort_unless($transaction->seller_shipped_item, 422, 'The seller must confirm shipment before delivery can be confirmed.');
        abort_if($transaction->buyer_confirmed_item_delivery, 422, 'Delivery has already been confirmed.');
        abort_if(in_array($transaction->status, [EscrowStatus::COMPLETED, EscrowStatus::CANCELLED, EscrowStatus::REFUNDED, EscrowStatus::DISPUTED, 'completed', 'cancelled', 'disputed'], true), 422, 'This escrow transaction cannot be updated.');

        $transaction = $this->release->scheduleAfterDeliveryConfirm($transaction, $transaction->buyer_id);
        $transaction->load($this->escrowRelations());

        $this->notifications->sendItemReceivedToSeller($transaction);
        $this->notifications->sendItemReceivedToAdmin($transaction);

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAdminStages(EscrowTransaction $transaction, array $data, ?User $admin = null): EscrowTransaction
    {
        $updates = [];

        if (array_key_exists('delivery_cost', $data)) {
            $updates['delivery_cost'] = round((float) $data['delivery_cost'], 2);
        }

        if (array_key_exists('delivery_address', $data)) {
            $updates['delivery_address'] = $data['delivery_address'];
        }

        foreach ([
            'payment_link_sent',
            'seller_shipped_item',
            'buyer_confirmed_item_delivery',
        ] as $flag) {
            if (array_key_exists($flag, $data)) {
                $updates[$flag] = (bool) $data[$flag];
            }
        }

        if ($updates !== []) {
            $transaction->update($updates);
            $transaction = $transaction->fresh();
        }

        if (! empty($data['payment_link_sent']) && ! empty($data['payment_link_url'])) {
            $metadata = $transaction->metadata ?? [];
            $metadata['payment_link_url'] = $data['payment_link_url'];
            $transaction->update(['metadata' => $metadata, 'payment_link_sent' => true]);
            $this->notifications->sendBuyerPaymentLink($transaction->fresh(), $data['payment_link_url']);
            $this->workflow->recordEvent($transaction, 'payment_link_sent', 'admin', $admin?->id, [
                'payment_link_url' => $data['payment_link_url'],
            ]);
        } elseif (! empty($data['payment_link_sent'])) {
            $transaction->update(['payment_link_sent' => true]);
        }

        if (! empty($data['payment_received']) && ! $transaction->payment_received) {
            $transaction = $this->funding->markFundedManual(
                $transaction->fresh(),
                $admin,
                $data['payment_reference'] ?? null,
                $data['offline_payment_note'] ?? null,
            );
        }

        if (! empty($data['seller_received_payment']) || ! empty($data['transaction_complete'])) {
            if (! $transaction->transaction_complete) {
                $transaction = $this->release->releaseNow($transaction->fresh(), $admin, true);
            }
        }

        return $transaction->load($this->escrowRelations());
    }

    public function resolveDispute(EscrowTransaction $transaction, User $admin, string $resolution, ?string $note = null): EscrowTransaction
    {
        $transaction = $this->disputes->resolve($transaction, $admin, $resolution, $note);

        return $transaction->load($this->escrowRelations());
    }

    /**
     * @return list<EscrowTransaction>
     */
    public function listForBuyer(User $buyer, ?string $status = null): \Illuminate\Support\Collection
    {
        return EscrowTransaction::query()
            ->with($this->escrowRelations())
            ->where('buyer_id', $buyer->id)
            ->when($status === 'active', fn ($q) => $q->whereNotIn('status', [EscrowStatus::COMPLETED, EscrowStatus::CANCELLED, EscrowStatus::REFUNDED, 'completed', 'cancelled']))
            ->when($status === 'completed', fn ($q) => $q->whereIn('status', [EscrowStatus::COMPLETED, 'completed']))
            ->when($status && ! in_array($status, ['active', 'completed'], true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->get();
    }

    public function resolveViewerRole(EscrowTransaction $transaction, string $token): string
    {
        if ($transaction->buyer_agreement_token === $token) {
            return 'buyer';
        }

        if ($transaction->seller_agreement_token === $token) {
            return 'seller';
        }

        return 'unknown';
    }

    /**
     * @return list<string>
     */
    public function resolveAllowedActions(EscrowTransaction $transaction, string $token): array
    {
        $role = $this->resolveViewerRole($transaction, $token);

        if ($role === 'unknown') {
            return [];
        }

        $actions = [];

        if ($role === 'buyer' && ! $transaction->buyer_agreed) {
            $actions[] = 'confirm';
        }

        if ($role === 'seller' && ! $transaction->seller_agreed) {
            $actions[] = 'confirm';
        }

        if (
            $transaction->buyer_agreed
            && $transaction->seller_agreed
            && ! in_array($transaction->status, [
                EscrowStatus::COMPLETED,
                EscrowStatus::CANCELLED,
                EscrowStatus::REFUNDED,
                EscrowStatus::DISPUTED,
                'completed',
                'cancelled',
                'disputed',
            ], true)
        ) {
            $actions[] = 'dispute';
        }

        if (
            $role === 'seller'
            && $transaction->payment_received
            && ! $transaction->seller_shipped_item
            && ! in_array($transaction->status, [EscrowStatus::COMPLETED, EscrowStatus::CANCELLED, EscrowStatus::REFUNDED, EscrowStatus::DISPUTED, 'completed', 'cancelled', 'disputed'], true)
        ) {
            $actions[] = 'confirm_shipped';
        }

        if (
            $role === 'buyer'
            && in_array($transaction->status, [EscrowStatus::AWAITING_FUNDING, EscrowStatus::LEGACY_PROCESSING, 'processing'], true)
            && $transaction->buyer_agreed
            && $transaction->seller_agreed
            && ! $transaction->payment_received
            && (float) $transaction->delivery_cost > 0
            && trim((string) ($transaction->delivery_address ?? '')) !== ''
        ) {
            $actions[] = 'pay';
        }

        if (
            $role === 'buyer'
            && $transaction->seller_shipped_item
            && ! $transaction->buyer_confirmed_item_delivery
            && ! in_array($transaction->status, [EscrowStatus::COMPLETED, EscrowStatus::CANCELLED, EscrowStatus::REFUNDED, EscrowStatus::DISPUTED, 'completed', 'cancelled', 'disputed'], true)
        ) {
            $actions[] = 'confirm_delivery';
        }

        return $actions;
    }

    /**
     * @return list<array{key: string, label: string, done: bool}>
     */
    public function buildStages(EscrowTransaction $transaction): array
    {
        $contractInitiated = $transaction->buyer_agreed && $transaction->seller_agreed;
        $deliveryCostSet = (float) $transaction->delivery_cost > 0;
        $deliveryAddressSet = trim((string) ($transaction->delivery_address ?? '')) !== '';

        return [
            ['key' => 'buyer_agreed', 'label' => '1. Buyer agreed to escrow', 'done' => (bool) $transaction->buyer_agreed],
            ['key' => 'seller_agreed', 'label' => '2. Seller agreed to escrow', 'done' => (bool) $transaction->seller_agreed],
            ['key' => 'contract_initiated', 'label' => '3. Escrow contract initiated', 'done' => $contractInitiated],
            ['key' => 'delivery_cost_set', 'label' => '4. Delivery cost is set', 'done' => $deliveryCostSet],
            ['key' => 'delivery_address_set', 'label' => '5. Delivery address is set', 'done' => $deliveryAddressSet],
            ['key' => 'payment_link_sent', 'label' => '6. Payment link sent to buyer', 'done' => (bool) $transaction->payment_link_sent],
            ['key' => 'payment_received', 'label' => '7. Buyer has paid', 'done' => (bool) $transaction->payment_received],
            ['key' => 'seller_shipped_item', 'label' => '8. Seller shipped item', 'done' => (bool) $transaction->seller_shipped_item],
            ['key' => 'buyer_confirmed_item_delivery', 'label' => '9. Buyer received item', 'done' => (bool) $transaction->buyer_confirmed_item_delivery],
            ['key' => 'seller_received_payment', 'label' => '10. Seller received payment', 'done' => (bool) $transaction->seller_received_payment],
            ['key' => 'transaction_complete', 'label' => '11. Transaction is complete', 'done' => (bool) $transaction->transaction_complete],
        ];
    }

    public function resolveUiMessage(EscrowTransaction $transaction, ?string $viewerToken = null): ?string
    {
        if (! $viewerToken) {
            return null;
        }

        $role = $this->resolveViewerRole($transaction, $viewerToken);

        if ($role === 'buyer' && $transaction->buyer_agreed && ! $transaction->seller_agreed) {
            return 'Your purchase agreement has been recorded. The seller will be notified to confirm.';
        }

        if ($role === 'seller' && $transaction->seller_agreed && ! $transaction->buyer_agreed) {
            return 'Your sale agreement has been recorded. Waiting for buyer confirmation.';
        }

        if ($transaction->buyer_agreed && $transaction->seller_agreed && in_array($transaction->status, [EscrowStatus::AWAITING_FUNDING, EscrowStatus::FUNDED, EscrowStatus::SHIPPED, EscrowStatus::AWAITING_ACCEPTANCE, EscrowStatus::LEGACY_PROCESSING, 'processing', 'funded', 'shipped', 'awaiting_acceptance', EscrowStatus::PENDING_AGREEMENT, 'pending', 'buyer_agreed', 'seller_agreed'], true)) {
            if ($role === 'buyer' && $transaction->seller_shipped_item && ! $transaction->buyer_confirmed_item_delivery) {
                return 'The seller has shipped your item. Confirm when you receive it.';
            }

            if ($role === 'seller' && $transaction->payment_received && ! $transaction->seller_shipped_item) {
                return 'The buyer has paid. Confirm when you have shipped the item.';
            }

            if ($role === 'buyer' && $transaction->buyer_confirmed_item_delivery && ! $transaction->transaction_complete) {
                if ($transaction->release_scheduled_at?->isFuture()) {
                    return 'Delivery confirmed. Payment will be released to the seller after the inspection window.';
                }

                return 'Thank you for confirming delivery. Selloff will release payment to the seller.';
            }

            if ($role === 'buyer' && ! $transaction->payment_received) {
                return 'Both parties have agreed. Complete payment to continue.';
            }

            return 'Both parties have agreed. Selloff Escrow will contact you with next steps.';
        }

        if ($transaction->status === EscrowStatus::COMPLETED || $transaction->transaction_complete || $transaction->status === 'completed') {
            return 'This escrow transaction is complete.';
        }

        if ($transaction->status === EscrowStatus::DISPUTED || $transaction->status === 'disputed') {
            return 'This escrow transaction is under dispute review.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatTransaction(EscrowTransaction $transaction, ?string $viewerToken = null): array
    {
        $metadata = $this->legacyMetadata($transaction);
        $product = $this->pricing->resolveProduct($transaction, $metadata);
        $viewerRole = $viewerToken ? $this->resolveViewerRole($transaction, $viewerToken) : null;
        $pricing = $this->pricing->resolvePricing($transaction, $product);

        return [
            'id' => $transaction->id,
            'ref' => $transaction->ref ?? ($metadata['ref'] ?? null),
            'status' => $transaction->status,
            'amount' => $pricing['item_price'],
            'commission_amount' => $pricing['commission_amount'],
            'commission_rate' => $pricing['commission_rate'],
            'seller_amount' => $pricing['seller_amount'],
            'delivery_cost' => $transaction->delivery_cost ?? $pricing['delivery_cost'],
            'delivery_address' => $transaction->delivery_address ?? ($metadata['delivery_address'] ?? null),
            'total_amount' => $pricing['total_amount'],
            'currency_code' => $transaction->currency_code ?? ($metadata['currency'] ?? null),
            'buyer_agreed' => $this->legacyBool($transaction, 'buyer_agreed', 'buyer_agreed_to_escrow', $metadata),
            'seller_agreed' => $this->legacyBool($transaction, 'seller_agreed', 'seller_agreed_to_escrow', $metadata),
            'payment_link_sent' => $this->legacyBool($transaction, 'payment_link_sent', 'payment_link_sent', $metadata),
            'payment_received' => $this->legacyBool($transaction, 'payment_received', 'payment_received', $metadata),
            'seller_shipped_item' => $this->legacyBool($transaction, 'seller_shipped_item', 'seller_shipped_item', $metadata),
            'buyer_confirmed_item_delivery' => $this->legacyBool($transaction, 'buyer_confirmed_item_delivery', 'buyer_confirmed_item_delivery', $metadata),
            'seller_received_payment' => $this->legacyBool($transaction, 'seller_received_payment', 'seller_received_payment', $metadata),
            'transaction_complete' => $this->legacyBool($transaction, 'transaction_complete', 'transaction_complete', $metadata),
            'stages' => $this->buildStages($this->withLegacyStageOverlay($transaction, $metadata)),
            'viewer_role' => $viewerRole,
            'allowed_actions' => $viewerToken ? $this->resolveAllowedActions($transaction, $viewerToken) : [],
            'ui_message' => $this->resolveUiMessage($transaction, $viewerToken),
            'agreement_urls' => [
                'buyer' => $transaction->buyer_agreement_token
                    ? $this->agreementUrl($transaction->buyer_agreement_token)
                    : null,
                'seller' => $transaction->seller_agreement_token
                    ? $this->agreementUrl($transaction->seller_agreement_token)
                    : null,
            ],
            'dispute' => $transaction->metadata['dispute'] ?? null,
            'payment_method' => $transaction->payment_method,
            'payment_reference' => $transaction->payment_reference,
            'funded_at' => $transaction->funded_at?->toIso8601String(),
            'shipped_at' => $transaction->shipped_at?->toIso8601String(),
            'accepted_at' => $transaction->accepted_at?->toIso8601String(),
            'released_at' => $transaction->released_at?->toIso8601String(),
            'release_scheduled_at' => $transaction->release_scheduled_at?->toIso8601String(),
            'events' => $this->workflow->formatEvents($transaction),
            'payment_link_url' => ($transaction->payment_link_sent && $viewerRole === 'buyer')
                ? ($transaction->metadata['payment_link_url'] ?? null)
                : null,
            'product' => $this->formatProduct($product, $metadata),
            'buyer' => $transaction->relationLoaded('buyer') && $transaction->buyer ? [
                'id' => $transaction->buyer->id,
                'name' => $transaction->buyer->name,
                'email' => $transaction->buyer->email,
                'slug' => $transaction->buyer->slug,
            ] : null,
            'seller' => $transaction->relationLoaded('seller') && $transaction->seller ? [
                'id' => $transaction->seller->id,
                'name' => $transaction->seller->name,
                'email' => $transaction->seller->email,
                'slug' => $transaction->seller->slug,
            ] : null,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'updated_at' => $transaction->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function gtmEventsForInitiate(Product $product, User $buyer): array
    {
        return $this->gtm->buyWithEscrow($product, $buyer);
    }

    private function agreementUrl(string $token): string
    {
        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        return "{$base}/escrow/{$token}";
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyMetadata(EscrowTransaction $transaction): array
    {
        return is_array($transaction->metadata) ? $transaction->metadata : [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{id: int, slug: ?string, title: ?string, price: float, image_url: ?string}|null
     */
    private function formatProduct(?Product $product, array $metadata): ?array
    {
        if ($product !== null) {
            if (! $product->relationLoaded('images')) {
                $product->load('images');
            }

            $image = $product->images->isNotEmpty()
                ? $product->images->sortBy('sort_order')->first()
                : null;

            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'title' => $product->translations->first()?->title ?? ($metadata['item_title'] ?? null),
                'price' => (float) $product->price,
                'image_url' => $image
                    ? app(MediaUploadService::class)->urlForProductImageWithVariants(
                        $image->path,
                        $image->disk,
                        'small',
                        is_array($image->variant_paths) ? $image->variant_paths : null,
                    )
                    : null,
            ];
        }

        $title = $metadata['item_title'] ?? null;
        $slug = $metadata['item_slug'] ?? null;
        if ($title === null && $slug === null) {
            return null;
        }

        return [
            'id' => (int) ($metadata['item_id'] ?? 0),
            'slug' => is_string($slug) ? $slug : null,
            'title' => is_string($title) ? $title : null,
            'price' => (float) ($metadata['item_price'] ?? 0),
            'image_url' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function legacyBool(
        EscrowTransaction $transaction,
        string $column,
        string $legacyKey,
        array $metadata,
    ): bool {
        if ((bool) $transaction->{$column}) {
            return true;
        }

        if (! array_key_exists($legacyKey, $metadata)) {
            return false;
        }

        return (bool) (int) $metadata[$legacyKey];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function withLegacyStageOverlay(EscrowTransaction $transaction, array $metadata): EscrowTransaction
    {
        $overlay = clone $transaction;

        foreach ([
            'buyer_agreed' => 'buyer_agreed_to_escrow',
            'seller_agreed' => 'seller_agreed_to_escrow',
            'payment_link_sent' => 'payment_link_sent',
            'payment_received' => 'payment_received',
            'seller_shipped_item' => 'seller_shipped_item',
            'buyer_confirmed_item_delivery' => 'buyer_confirmed_item_delivery',
            'seller_received_payment' => 'seller_received_payment',
            'transaction_complete' => 'transaction_complete',
        ] as $column => $legacyKey) {
            if (! $overlay->{$column} && array_key_exists($legacyKey, $metadata)) {
                $overlay->{$column} = (bool) (int) $metadata[$legacyKey];
            }
        }

        if ((float) $overlay->delivery_cost <= 0 && isset($metadata['delivery_cost'])) {
            $overlay->delivery_cost = (float) $metadata['delivery_cost'];
        }

        if (trim((string) ($overlay->delivery_address ?? '')) === '' && ! empty($metadata['delivery_address'])) {
            $overlay->delivery_address = (string) $metadata['delivery_address'];
        }

        return $overlay;
    }
}
