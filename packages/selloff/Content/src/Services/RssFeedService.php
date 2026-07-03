<?php

namespace App\Modules\Selloff\Content\Services;

use App\Models\User;
use App\Modules\Selloff\Admin\Services\RouteSlugService;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\CategoryPathService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RssFeedService
{
    private const LATEST_LIMIT = 30;

    private const FEED_LIMIT = 50;

    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
        private readonly RouteSlugService $routeSlugs,
        private readonly CategoryPathService $categoryPaths,
    ) {}

    public function isEnabled(): bool
    {
        $platform = $this->platformSettings->all();

        return (bool) ($platform['rss_enabled'] ?? config('selloff.platform_settings.rss_enabled', true));
    }

    /**
     * @return array{feedName: string, feedUrl: string, pageDescription: string, products: Collection<int, Product>, copyright: string|null}
     */
    public function latestFeed(): array
    {
        $siteName = $this->siteName();
        $feedSlug = $this->routeSlugs->slug('latest_products');

        return [
            'feedName' => "{$siteName} RSS Feeds - Latest Products",
            'feedUrl' => $this->publicUrl("rss/{$feedSlug}"),
            'pageDescription' => "{$siteName} RSS Feeds - Latest Products",
            'products' => $this->baseProductQuery()
                ->orderByDesc('products.id')
                ->limit(self::LATEST_LIMIT)
                ->get(),
            'copyright' => $this->copyright(),
        ];
    }

    /**
     * @return array{feedName: string, feedUrl: string, pageDescription: string, products: Collection<int, Product>, copyright: string|null}
     */
    public function featuredFeed(): array
    {
        $siteName = $this->siteName();
        $feedSlug = $this->routeSlugs->slug('featured_products');

        return [
            'feedName' => "{$siteName} RSS Feeds - Featured Products",
            'feedUrl' => $this->publicUrl("rss/{$feedSlug}"),
            'pageDescription' => "{$siteName} RSS Feeds - Featured Products",
            'products' => $this->baseProductQuery()
                ->where('is_promoted', true)
                ->where(function (Builder $query): void {
                    $query->whereNull('promoted_until')
                        ->orWhere('promoted_until', '>', now());
                })
                ->orderByDesc('products.id')
                ->limit(self::FEED_LIMIT)
                ->get(),
            'copyright' => $this->copyright(),
        ];
    }

    /**
     * @return array{feedName: string, feedUrl: string, pageDescription: string, products: Collection<int, Product>, copyright: string|null}|null
     */
    public function categoryFeed(string $slug): ?array
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('status', true)
            ->first();

        if ($category === null) {
            return null;
        }

        $categoryName = $category->translations()
            ->where('locale', 'en')
            ->value('name') ?? $category->slug;

        $siteName = $this->siteName();
        $categoryRoute = $this->routeSlugs->slug('category');
        $categoryIds = $this->categoryPaths->descendantIdsIncludingSelf($category->id);

        return [
            'feedName' => "{$siteName} RSS Feeds - {$categoryName}",
            'feedUrl' => $this->publicUrl("rss/{$categoryRoute}/{$slug}"),
            'pageDescription' => "{$siteName} RSS Feeds - {$categoryName}",
            'products' => $this->baseProductQuery()
                ->whereIn('category_id', $categoryIds)
                ->orderByDesc('products.id')
                ->limit(self::FEED_LIMIT)
                ->get(),
            'copyright' => $this->copyright(),
        ];
    }

    /**
     * @return array{feedName: string, feedUrl: string, pageDescription: string, products: Collection<int, Product>, copyright: string|null, redirectTo: string|null}
     */
    public function sellerFeed(string $slug): array
    {
        $vendor = User::query()->where('slug', $slug)->first();
        $siteName = $this->siteName();
        $sellerRoute = $this->routeSlugs->slug('seller');
        $vendorName = $vendor?->username ?? $vendor?->shop_name ?? $slug;

        if ($vendor === null) {
            return [
                'feedName' => "{$siteName} RSS Feeds",
                'feedUrl' => $this->publicUrl("rss/{$sellerRoute}/{$slug}"),
                'pageDescription' => "{$siteName} RSS Feeds",
                'products' => collect(),
                'copyright' => $this->copyright(),
                'redirectTo' => $this->publicUrl($this->routeSlugs->slug('rss_feeds')),
            ];
        }

        if (! $vendor->show_rss_feeds) {
            return [
                'feedName' => "{$siteName} RSS Feeds - {$vendorName}",
                'feedUrl' => $this->publicUrl("rss/{$sellerRoute}/{$slug}"),
                'pageDescription' => "{$siteName} RSS Feeds - {$vendorName}",
                'products' => collect(),
                'copyright' => $this->copyright(),
                'redirectTo' => $this->publicUrl("shops/{$slug}"),
            ];
        }

        return [
            'feedName' => "{$siteName} RSS Feeds - {$vendorName}",
            'feedUrl' => $this->publicUrl("rss/{$sellerRoute}/{$slug}"),
            'pageDescription' => "{$siteName} RSS Feeds - {$vendorName}",
            'products' => $this->baseProductQuery()
                ->where('vendor_id', $vendor->id)
                ->orderByDesc('products.id')
                ->limit(self::FEED_LIMIT)
                ->get(),
            'copyright' => $this->copyright(),
            'redirectTo' => null,
        ];
    }

    /**
     * @return list<array{name: string, slug: string, feed_url: string}>
     */
    public function parentCategoryFeeds(): array
    {
        $categoryRoute = $this->routeSlugs->slug('category');

        return Category::query()
            ->with(['translations'])
            ->whereNull('parent_id')
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Category $category) use ($categoryRoute): array {
                $name = $category->translations->firstWhere('locale', 'en')?->name
                    ?? $category->translations->first()?->name
                    ?? $category->slug;

                return [
                    'name' => (string) $name,
                    'slug' => (string) $category->slug,
                    'feed_url' => $this->publicUrl("rss/{$categoryRoute}/{$category->slug}"),
                ];
            })
            ->all();
    }

    public function productUrl(Product $product): string
    {
        return rtrim((string) config('selloff.spa_url', config('app.url')), '/').'/products/'.$product->slug;
    }

    public function productTitle(Product $product): string
    {
        $translation = $product->translations->firstWhere('locale', 'en')
            ?? $product->translations->first();

        return (string) ($translation?->title ?? $product->slug);
    }

    public function productDescription(Product $product): string
    {
        $translation = $product->translations->firstWhere('locale', 'en')
            ?? $product->translations->first();

        return (string) ($translation?->description ?? $translation?->short_description ?? '');
    }

    public function productCreator(Product $product): string
    {
        $vendor = $product->vendor;

        return (string) ($vendor?->username ?? $vendor?->slug ?? 'seller');
    }

    public function productImageUrl(Product $product): ?string
    {
        $image = $product->images->sortBy('sort_order')->first();

        return $image ? MediaUrl::resolve($image->path) : null;
    }

    public function formatPrice(Product $product): string
    {
        $discounted = $product->price_discounted !== null ? (float) $product->price_discounted : null;
        $price = $discounted !== null && $discounted > 0 ? $discounted : (float) $product->price;
        $currency = $product->currency_code ?: 'NGN';

        return $currency.' '.number_format($price, 2, '.', ',');
    }

    public function rssFeedsIndexUrl(): string
    {
        return $this->publicUrl($this->routeSlugs->slug('rss_feeds'));
    }

    public function latestFeedUrl(): string
    {
        return $this->publicUrl('rss/'.$this->routeSlugs->slug('latest_products'));
    }

    public function featuredFeedUrl(): string
    {
        return $this->publicUrl('rss/'.$this->routeSlugs->slug('featured_products'));
    }

    private function baseProductQuery(): Builder
    {
        $query = Product::query()
            ->listed()
            ->with(['translations', 'vendor', 'images']);

        if (! $this->showSoldProducts()) {
            $query->where('is_sold', false);
        }

        return $query;
    }

    private function showSoldProducts(): bool
    {
        $platform = $this->platformSettings->all();

        return (bool) ($platform['show_sold_products'] ?? config('selloff.platform_settings.show_sold_products', true));
    }

    public function siteName(): string
    {
        $platform = $this->platformSettings->all();

        return (string) ($platform['site_name'] ?? config('selloff.platform_settings.site_name', 'Selloff'));
    }

    private function copyright(): ?string
    {
        $platform = $this->platformSettings->all();
        $copyright = trim((string) ($platform['copyright'] ?? config('selloff.platform_settings.copyright', '')));

        return $copyright !== '' ? $copyright : null;
    }

    private function publicUrl(string $path): string
    {
        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');
        $path = ltrim($path, '/');

        return "{$base}/{$path}";
    }
}
