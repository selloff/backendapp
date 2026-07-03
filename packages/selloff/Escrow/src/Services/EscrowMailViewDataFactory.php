<?php

namespace App\Modules\Selloff\Escrow\Services;

use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowMailBranding;
use App\Modules\Selloff\Escrow\Support\EscrowMailStage;
use App\Modules\Selloff\Escrow\Support\EscrowMailViewData;
use App\Services\Media\MediaUploadService;

class EscrowMailViewDataFactory
{
    public function __construct(
        private readonly EscrowPricingService $pricing,
        private readonly EscrowMailBranding $branding,
    ) {}

    public function forStage(
        EscrowTransaction $transaction,
        EscrowMailStage $stage,
        string $subject,
        string $recipientName,
        ?string $agreementUrl = null,
        ?string $paymentUrl = null,
        bool $deliveryCostPending = false,
    ): EscrowMailViewData {
        $transaction->loadMissing(['product.translations', 'product.images', 'product.category', 'buyer', 'seller']);

        $product = $this->pricing->resolveProduct($transaction);
        $pricing = $this->pricing->resolvePricing($transaction, $product);
        $title = $this->productTitle($transaction, $product);

        return new EscrowMailViewData(
            stage: $stage,
            subject: $subject,
            recipientName: $recipientName,
            productTitle: $title,
            productUrl: $this->productUrl($transaction),
            productImageUrl: $this->productImageUrl($transaction),
            pricing: $pricing,
            currencyCode: (string) ($transaction->currency_code ?? 'NGN'),
            agreementUrl: $agreementUrl,
            paymentUrl: $paymentUrl,
            deliveryAddress: $transaction->delivery_address,
            ref: (string) $transaction->ref,
            buyerName: $this->partyName($transaction->buyer, $transaction->buyer_email),
            buyerPhone: $transaction->buyer?->phone_number,
            buyerUsername: $transaction->buyer?->username ?? $transaction->buyer?->slug,
            sellerName: $this->partyName($transaction->seller, $transaction->seller_email),
            sellerPhone: $transaction->seller?->phone_number,
            sellerUsername: $transaction->seller?->username ?? $transaction->seller?->slug,
            branding: $this->branding->resolve(),
            bank: (array) config('selloff.escrow_bank', []),
            deliveryCostPending: $deliveryCostPending,
        );
    }

    private function productTitle(EscrowTransaction $transaction, ?\App\Modules\Selloff\Catalog\Models\Product $product): string
    {
        $fromProduct = $product?->translations->firstWhere('locale', 'en')?->title
            ?? $product?->translations->first()?->title;

        return (string) ($fromProduct ?? $transaction->ref ?? 'your item');
    }

    private function productUrl(EscrowTransaction $transaction): string
    {
        $slug = $transaction->product?->slug;
        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        return $slug ? "{$base}/products/{$slug}" : $base;
    }

    private function productImageUrl(EscrowTransaction $transaction): ?string
    {
        $product = $transaction->product;
        if ($product === null) {
            return null;
        }

        if (! $product->relationLoaded('images')) {
            $product->load('images');
        }

        $image = $product->images->sortBy('sort_order')->first();
        if ($image === null) {
            return null;
        }

        return app(MediaUploadService::class)->urlForProductImageWithVariants(
            $image->path,
            $image->disk,
            'small',
            is_array($image->variant_paths) ? $image->variant_paths : null,
        );
    }

    private function partyName(?\App\Models\User $user, ?string $fallbackEmail): string
    {
        if ($user !== null) {
            $name = trim((string) ($user->name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return (string) ($fallbackEmail ?? 'Customer');
    }
}
