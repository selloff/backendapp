<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Support\Gtm\GtmEventFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductViewGtmService
{
    public function __construct(
        private readonly GtmEventFactory $factory,
    ) {}

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function trackViewItem(Product $product, ?User $viewer, Request $request): array
    {
        $dedupeKey = sprintf(
            'gtm:view_item:%d:%s:%s',
            $product->id,
            $viewer?->id ?? 'guest',
            sha1((string) $request->ip().'|'.(string) $request->userAgent()),
        );

        if (Cache::has($dedupeKey)) {
            return [];
        }

        Cache::put($dedupeKey, true, now()->addHours(6));

        $product->loadMissing([
            'translations',
            'category.parent.translations',
            'category.translations',
            'vendor.state',
        ]);

        $translation = $product->translations->firstWhere('locale', 'en')
            ?? $product->translations->first();
        $vendor = $product->vendor;
        $category = $product->category;
        $categoryTranslation = $category?->translations->firstWhere('locale', 'en')
            ?? $category?->translations->first();
        $parent = $category?->parent;
        $parentTranslation = $parent?->translations->firstWhere('locale', 'en')
            ?? $parent?->translations->first();

        return $this->factory->list('view_item', [
            'item_id' => (string) $product->id,
            'item_title' => (string) ($translation?->title ?? ''),
            'item_category_id' => (string) ($product->category_id ?? ''),
            'item_category' => (string) ($parentTranslation?->name ?? $categoryTranslation?->name ?? ''),
            'item_sub_category' => (string) ($parent ? ($categoryTranslation?->name ?? '') : ''),
            'item_price' => (float) ($product->price_discounted ?? $product->price),
            'item_location' => (string) ($vendor?->state?->name ?? ''),
            'seller_id' => $vendor ? (string) $vendor->id : '',
            'seller_name' => $vendor ? trim($vendor->first_name.' '.$vendor->last_name) : '',
            'seller_username' => $vendor ? (string) ($vendor->username ?? $vendor->slug ?? '') : '',
            'seller_phone' => $vendor ? (string) ($vendor->phone_number ?? '') : '',
            'seller_email' => $vendor ? (string) $vendor->email : '',
            'viewer_id' => $viewer ? (string) $viewer->id : '',
            'viewer_name' => $viewer ? trim($viewer->first_name.' '.$viewer->last_name) : '',
            'viewer_username' => $viewer ? (string) ($viewer->username ?? $viewer->slug ?? '') : '',
            'viewer_phone' => $viewer ? (string) ($viewer->phone_number ?? '') : '',
            'viewer_email' => $viewer ? (string) ($viewer->email ?? '') : '',
            'viewer_ip' => (string) $request->ip(),
        ]);
    }
}
