<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Services\Platform\PlatformSettingsService;
use App\Support\MediaUrl;

class ServiceInvoiceBuilder
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
        private readonly PaymentGatewaySettingsService $paymentSettings,
    ) {}

    /** @return array<string, mixed> */
    public function membership(MembershipTransaction $transaction): array
    {
        $transaction->loadMissing(['user', 'membershipPlan']);
        $platform = $this->platformSettings->all();
        $gateway = $this->paymentSettings->all();
        $user = $transaction->user;
        $plan = $transaction->membershipPlan;
        $currency = $transaction->currency_code ?? $plan?->currency_code ?? 'NGN';
        $isPending = $transaction->status === 'pending';
        $termMonths = (int) ($transaction->term_months ?? 1);
        $purchaseType = (string) ($transaction->purchase_type ?? 'new');

        return [
            'type' => 'membership',
            'invoice_number' => 'INV-MEM-'.$transaction->id,
            'transaction_id' => $transaction->id,
            'issued_at' => $transaction->created_at,
            'company' => $this->companyBlock($platform),
            'client' => [
                'username' => $user?->username ?? $user?->slug,
                'first_name' => $user?->first_name,
                'last_name' => $user?->last_name,
                'email' => $user?->email,
                'phone_number' => $user?->phone_number,
            ],
            'payment' => [
                'status' => $transaction->status,
                'method' => $transaction->payment_method,
                'currency_code' => $currency,
                'reference' => $transaction->payment_reference,
            ],
            'plan' => $plan ? [
                'id' => $plan->id,
                'title' => $plan->title,
                'monthly_price' => $plan->price,
            ] : null,
            'purchase_type' => $purchaseType,
            'term_months' => $termMonths,
            'description' => $plan?->title ?? 'Membership plan',
            'payment_method' => $transaction->payment_method,
            'payment_status' => $transaction->status,
            'amount' => $transaction->amount,
            'currency_code' => $currency,
            'price_total' => $transaction->amount,
            'customer' => [
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'items' => [[
                'description' => sprintf(
                    '%s (%d month%s, %s)',
                    $plan?->title ?? 'Membership plan',
                    $termMonths,
                    $termMonths === 1 ? '' : 's',
                    ucfirst($purchaseType),
                ),
                'quantity' => 1,
                'total_price' => $transaction->amount,
            ]],
            'totals' => [
                'subtotal' => $transaction->gross_amount ?? $transaction->amount,
                'discount' => $transaction->discount_amount ?? 0,
                'credit' => $transaction->credit_amount ?? 0,
                'total' => $transaction->amount,
            ],
            'is_pending_payment' => $isPending,
            'can_complete_payment' => $isPending,
            'bank_transfer_instructions' => $isPending && $transaction->payment_method === 'bank_transfer'
                ? ($gateway['bank_transfer_instructions'] ?? null)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function promotion(PromotionTransaction $transaction): array
    {
        $transaction->loadMissing(['user', 'product.translations']);
        $platform = $this->platformSettings->all();
        $gateway = $this->paymentSettings->all();
        $user = $transaction->user;
        $productTitle = $transaction->product?->translations->firstWhere('locale', 'en')?->title
            ?? $transaction->product?->translations->first()?->title
            ?? 'Product promotion';
        $currency = $transaction->currency_code ?? 'NGN';
        $isPending = $transaction->status === 'pending';

        return [
            'type' => 'promotion',
            'invoice_number' => 'INVP'.$transaction->id,
            'transaction_id' => $transaction->id,
            'issued_at' => $transaction->created_at,
            'company' => $this->companyBlock($platform),
            'client' => [
                'username' => $user?->username ?? $user?->slug,
                'first_name' => $user?->first_name,
                'last_name' => $user?->last_name,
                'email' => $user?->email,
                'phone_number' => $user?->phone_number,
            ],
            'payment' => [
                'status' => $transaction->status,
                'method' => $transaction->payment_method,
                'currency_code' => $currency,
                'reference' => $transaction->payment_reference,
            ],
            'product' => $transaction->product ? [
                'id' => $transaction->product->id,
                'slug' => $transaction->product->slug,
                'title' => $productTitle,
            ] : null,
            'purchased_plan' => $transaction->purchased_plan,
            'day_count' => $transaction->day_count,
            'description' => $productTitle,
            'payment_method' => $transaction->payment_method,
            'payment_status' => $transaction->status,
            'amount' => $transaction->amount,
            'currency_code' => $currency,
            'price_total' => $transaction->amount,
            'customer' => [
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'items' => [[
                'description' => 'Product promotion',
                'product_id' => $transaction->product_id,
                'product_title' => $productTitle,
                'purchased_plan' => $transaction->purchased_plan,
                'quantity' => 1,
                'total_price' => $transaction->amount,
            ]],
            'totals' => [
                'subtotal' => $transaction->amount,
                'total' => $transaction->amount,
            ],
            'is_pending_payment' => $isPending,
            'can_complete_payment' => $isPending,
            'bank_transfer_instructions' => $isPending && $transaction->payment_method === 'bank_transfer'
                ? ($gateway['bank_transfer_instructions'] ?? null)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function walletDeposit(WalletDeposit $deposit): array
    {
        $deposit->loadMissing('user');
        $platform = $this->platformSettings->all();

        return [
            'type' => 'wallet_deposit',
            'invoice_number' => 'INV-WAL-'.$deposit->id,
            'issued_at' => $deposit->created_at,
            'payment_method' => $deposit->payment_method,
            'payment_status' => $deposit->status,
            'amount' => $deposit->amount,
            'currency_code' => $deposit->currency_code ?? 'NGN',
            'description' => 'Wallet deposit',
            'customer' => [
                'name' => $deposit->user?->name,
                'email' => $deposit->user?->email,
            ],
            'company' => $this->companyBlock($platform),
        ];
    }

    public function authorizeMembership(MembershipTransaction $transaction, User $viewer): void
    {
        if ($viewer->can('admin_panel') || $viewer->can('payment_settings')) {
            return;
        }

        abort_unless((int) $transaction->user_id === (int) $viewer->id, 403);
    }

    public function authorizePromotion(PromotionTransaction $transaction, User $viewer): void
    {
        if ($viewer->can('admin_panel') || $viewer->can('payment_settings')) {
            return;
        }

        abort_unless((int) $transaction->user_id === (int) $viewer->id, 403);
    }

    public function authorizeWalletDeposit(WalletDeposit $deposit, User $viewer): void
    {
        if ($viewer->can('admin_panel') || $viewer->can('payment_settings')) {
            return;
        }

        abort_unless((int) $deposit->user_id === (int) $viewer->id, 403);
    }

    public function authorizeOrder(Order $order, User $viewer): void
    {
        if ($viewer->can('admin_panel')) {
            return;
        }

        if ((int) $order->buyer_id === (int) $viewer->id) {
            return;
        }

        if ($viewer->can('vendor') && $order->items()->where('seller_id', $viewer->id)->exists()) {
            return;
        }

        abort(403);
    }

    /** @param  array<string, mixed>  $platform
     * @return array<string, mixed>
     */
    private function companyBlock(array $platform): array
    {
        return [
            'logo_url' => $this->logoUrl($platform),
            'address' => (string) ($platform['contact_address'] ?? ''),
            'email' => (string) ($platform['contact_email'] ?? ''),
            'phone' => (string) ($platform['contact_phone'] ?? ''),
            'additional_info' => (string) ($platform['contact_text'] ?? ''),
        ];
    }

    /** @param  array<string, mixed>  $platform */
    private function logoUrl(array $platform): ?string
    {
        $logo = $platform['site_logo_url'] ?? $platform['site_logo_email_url'] ?? null;
        if (! is_string($logo) || $logo === '') {
            return null;
        }

        return MediaUrl::resolve($logo);
    }
}
