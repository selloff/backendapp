<?php

namespace App\Modules\Selloff\Notification\Support;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Services\Media\MediaUploadService;
use App\Services\Platform\PlatformSettingsService;

class ProductMailViewDataFactory
{
    public function __construct(
        private readonly MediaUploadService $media,
        private readonly PlatformSettingsService $settings,
    ) {}

    /**
     * @return array{
     *   firstName: string,
     *   productTitle: string,
     *   productImg: ?string,
     *   productUrl: string,
     *   editUrl: string,
     *   rejectReason: ?string
     * }
     */
    public function forProduct(Product $product, ?string $rejectReason = null): array
    {
        $product->loadMissing(['translations', 'vendor', 'images']);

        $title = (string) ($product->translations->firstWhere('locale', 'en')?->title
            ?? $product->translations->first()?->title
            ?? $product->slug
            ?? 'your item');

        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');
        $slug = trim((string) ($product->slug ?? ''));

        return [
            'firstName' => $this->vendorFirstName($product),
            'productTitle' => $title,
            'productImg' => $this->productImageUrl($product),
            'productUrl' => $slug !== '' ? "{$base}/{$slug}" : $base,
            'editUrl' => "{$base}/vendor/products/{$product->id}/edit",
            'rejectReason' => $rejectReason,
        ];
    }

    public function adminRecipient(): ?string
    {
        $settings = $this->settings->all();
        $account = trim((string) ($settings['mail_options_account'] ?? ''));

        if ($account !== '') {
            return $account;
        }

        $contact = trim((string) ($settings['contact_email'] ?? ''));

        return $contact !== '' ? $contact : null;
    }

    private function vendorFirstName(Product $product): string
    {
        $vendor = $product->vendor;
        $first = trim((string) ($vendor?->first_name ?? ''));

        if ($first !== '') {
            return $first;
        }

        $name = trim((string) ($vendor?->name ?? ''));

        return $name !== '' ? $name : 'there';
    }

    private function productImageUrl(Product $product): ?string
    {
        $image = $product->images->sortBy('sort_order')->first();

        if ($image === null) {
            return null;
        }

        return $this->media->urlForProductImageWithVariants(
            $image->path,
            $image->disk,
            'small',
            is_array($image->variant_paths) ? $image->variant_paths : null,
        );
    }
}
