<?php

namespace App\Modules\Selloff\Content\Services;

use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\BrandResource;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\CategoryResource;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Brand;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery;
use App\Modules\Selloff\Catalog\Support\ProductListingFilterCriteria;
use App\Modules\Selloff\Catalog\Services\BrandSettingsService;
use App\Modules\Selloff\Catalog\Services\ListingRankScoreService;
use App\Modules\Selloff\Catalog\Services\TrendingProductService;
use App\Modules\Selloff\Payment\Services\MembershipCatalogVisibilityService;
use App\Modules\Selloff\Content\Http\Resources\Api\V1\SliderResource;
use App\Modules\Selloff\Content\Models\BlogPost;
use App\Modules\Selloff\Content\Models\HomepageBanner;
use App\Modules\Selloff\Content\Models\Slider;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class HomepageAssemblyService
{
    private const CAROUSEL_PRODUCT_LIMIT = 12;

    private const PROMOTED_LIMIT = 12;

    private const TRENDING_LIMIT = 12;

    private const SPECIAL_OFFERS_LIMIT = 20;

    private const BLOG_LIMIT = 10;

    private const MOBILE_BANNER_LOCATIONS = [
        'mobile_home',
        'mobile_categories',
        'mobile_other',
    ];

    private ?int $priorityStateId = null;

    private ?int $priorityCityId = null;

    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
        private readonly BrandSettingsService $brandSettings,
        private readonly ListingRankScoreService $listingRank,
        private readonly MembershipCatalogVisibilityService $membershipCatalogVisibility,
        private readonly TrendingProductService $trendingProducts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?int $priorityStateId = null, ?int $priorityCityId = null): array
    {
        $this->priorityStateId = $priorityStateId;
        $this->priorityCityId = $priorityCityId;

        $settings = $this->homepageSettings();

        return [
            'sliders' => $this->loadSliders(),
            'sections' => $this->buildLatestSections($settings),
            'banners' => $this->loadBanners(),
            'mobile_banners' => $this->loadMobileBanners(),
            'site_banners' => $this->siteBanners(),
            'settings' => $settings,
            'featured_categories' => $settings['featured_categories']
                ? CategoryResource::collection($this->loadFeaturedCategories())->resolve()
                : [],
            'promoted_products' => $settings['index_promoted_products'] && $settings['promoted_products']
                ? ProductResource::collection($this->loadPromotedProducts())->resolve()
                : [],
            'trending_products' => ProductResource::collection(
                $this->loadTrendingProducts($settings['index_trending_products_count']),
            )->resolve(),
            'special_offers' => ProductResource::collection($this->loadSpecialOffers())->resolve(),
            'category_carousels' => $this->buildCategoryCarousels(),
            'brands' => BrandResource::collection($this->loadBrands())->resolve(),
            'blog_posts' => $this->loadBlogPosts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function homepageSettings(): array
    {
        $platform = $this->platformSettings->all();

        $productsPerRow = 5;

        return [
            'index_products_per_row' => $productsPerRow,
            'index_latest_products' => $this->homepageProductVisibilityEnabled($platform, 'index_latest_products', true),
            'index_latest_products_count' => max(1, (int) ($platform['index_latest_products_count'] ?? config('selloff.platform_settings.index_latest_products_count', 8))),
            'index_promoted_products' => $this->homepageProductVisibilityEnabled($platform, 'index_promoted_products', true),
            'index_promoted_products_count' => max(1, (int) ($platform['index_promoted_products_count'] ?? config('selloff.platform_settings.index_promoted_products_count', 12))),
            'index_trending_products_count' => max(1, (int) ($platform['index_trending_products_count'] ?? config('selloff.platform_settings.index_trending_products_count', 12))),
            'index_blog_slider' => $this->platformBool($platform, 'index_blog_slider', true),
            'promoted_products' => $this->platformBool($platform, 'promoted_products', true),
            'featured_categories' => $this->platformBool($platform, 'featured_categories', true),
            'slider_status' => $this->platformBool($platform, 'slider_status', true),
            'slider_type' => (string) ($platform['slider_type'] ?? config('selloff.platform_settings.slider_type', 'full_width')),
            'slider_effect' => (string) ($platform['slider_effect'] ?? config('selloff.platform_settings.slider_effect', 'fade')),
            'product_img_display_mode' => ($platform['product_img_display_mode'] ?? 'cover') === 'cover' ? 'cover' : 'full_image',
            'fea_categories_design' => $this->normalizeFeaCategoriesDesign(
                (string) ($platform['fea_categories_design'] ?? config('selloff.platform_settings.fea_categories_design', 'round_boxes')),
            ),
            'product_grid_layout' => $this->normalizeProductGridLayout(
                (string) ($platform['product_grid_layout'] ?? config('selloff.platform_settings.product_grid_layout', 'rows')),
            ),
            'index_recommended_products_count' => $this->normalizeRecommendedProductsCount(
                $platform['index_recommended_products_count'] ?? config('selloff.platform_settings.index_recommended_products_count', 10),
            ),
            'site_title' => (string) ($platform['site_name'] ?? config('selloff.platform_settings.site_name', 'Selloff')),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function buildLatestSections(array $settings): array
    {
        if (! $settings['index_latest_products']) {
            return [];
        }

        $limit = $settings['index_latest_products_count'];
        $sections = [];

        $phoneCategoryIds = $this->categoryIdsForSlugs(config('selloff.homepage.phone_category_slugs', ['phones']));
        $phoneProducts = $this->latestProductsInCategories($phoneCategoryIds, $limit);
        if ($phoneProducts->isNotEmpty()) {
            $phoneCategory = $this->firstCategoryForSlugs(config('selloff.homepage.phone_category_slugs', ['phones']));
            $sections[] = $this->sectionPayload(
                key: 'phones',
                title: (string) config('selloff.homepage.phone_section_title', 'Latest Smartphones & Tablets'),
                category: $phoneCategory,
                viewAllPath: $phoneCategory ? $this->categoryListingPath($phoneCategory) : '/products',
                products: $phoneProducts,
            );
        }

        $laptopCategoryIds = $this->categoryIdsForSlugs(config('selloff.homepage.laptop_category_slugs', ['laptops']));
        $laptopProducts = $this->latestProductsInCategories($laptopCategoryIds, $limit);
        if ($laptopProducts->isNotEmpty()) {
            $laptopCategory = $this->firstCategoryForSlugs(config('selloff.homepage.laptop_category_slugs', ['laptops']));
            $sections[] = $this->sectionPayload(
                key: 'laptops',
                title: (string) config('selloff.homepage.laptop_section_title', 'Latest Laptops and Computers'),
                category: $laptopCategory,
                viewAllPath: $laptopCategory ? $this->categoryListingPath($laptopCategory) : '/products',
                products: $laptopProducts,
            );
        }

        $excludedIds = array_values(array_unique(array_merge($phoneCategoryIds, $laptopCategoryIds)));
        $otherProducts = $this->latestProductsExcludingCategories($excludedIds, $limit);
        if ($otherProducts->isNotEmpty()) {
            $sections[] = $this->sectionPayload(
                key: 'other',
                title: (string) config('selloff.homepage.other_section_title', 'Latest In Other Categories'),
                category: null,
                viewAllPath: '/products',
                products: $otherProducts,
            );
        }

        return $sections;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryCarousels(): array
    {
        $carouselCategories = Category::query()
            ->with(['translations'])
            ->where('status', true)
            ->where('show_products_on_index', true)
            ->whereNull('parent_id')
            ->orderBy('homepage_order')
            ->orderBy('id')
            ->get();

        $carousels = [];
        foreach ($carouselCategories as $category) {
            $products = $this->carouselProductsForCategory($category->id);
            if ($products->isEmpty()) {
                continue;
            }

            $translation = $category->translations->firstWhere('locale', 'en')
                ?? $category->translations->first();

            $carousels[] = [
                'key' => $category->slug,
                'title' => $translation?->name ?? 'Category',
                'category_id' => $category->id,
                'category_slug' => $category->slug,
                'view_all_path' => $this->categoryListingPath($category),
                'products' => ProductResource::collection($products)->resolve(),
            ];
        }

        return $carousels;
    }

    /**
     * @return array<string, array{image_path: string, alt: string}|null>
     */
    private function siteBanners(): array
    {
        $platform = $this->platformSettings->all();

        return [
            'top' => $this->resolveSiteBanner(config('selloff.homepage.site_banners.top')),
            'mid' => $this->resolveSiteBannerFromPlatform(
                $platform['homepage_site_banner_mid_image'] ?? null,
                $platform['homepage_site_banner_mid_alt'] ?? null,
            ),
        ];
    }

    /**
     * @param  array{image_path?: string, alt?: string}|null  $banner
     * @return array{image_path: string, alt: string}|null
     */
    private function resolveSiteBanner(?array $banner): ?array
    {
        if ($banner === null) {
            return null;
        }

        $imagePath = trim((string) ($banner['image_path'] ?? ''));
        if ($imagePath === '') {
            return null;
        }

        $alt = trim((string) ($banner['alt'] ?? 'Selloff.ng Banner'));

        return [
            'image_path' => $imagePath,
            'alt' => $alt !== '' ? $alt : 'Selloff.ng Banner',
        ];
    }

    /**
     * @return array{image_path: string, alt: string}|null
     */
    private function resolveSiteBannerFromPlatform(mixed $image, mixed $alt): ?array
    {
        $imagePath = is_string($image) ? trim($image) : '';
        if ($imagePath === '') {
            return null;
        }

        $altText = is_string($alt) ? trim($alt) : '';

        return [
            'image_path' => $imagePath,
            'alt' => $altText !== '' ? $altText : 'Selloff.ng Banner',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadSliders(): array
    {
        $platform = $this->platformSettings->all();
        if (! $this->platformBool($platform, 'slider_status', true)) {
            return [];
        }

        $sliders = Slider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return SliderResource::collection($sliders)->resolve();
    }

    /**
     * @return Collection<int, Category>
     */
    private function loadFeaturedCategories(): Collection
    {
        return Category::query()
            ->with(['translations'])
            ->where('status', true)
            ->where('is_featured', true)
            ->orderBy('featured_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function loadPromotedProducts(): Collection
    {
        return Product::query()
            ->listed()
            ->with($this->productRelations())
            ->withCount('options')
            ->where('is_promoted', true)
            ->where(function ($query): void {
                $query->whereNull('promoted_until')
                    ->orWhere('promoted_until', '>', now());
            })
            ->tap(fn ($query) => $this->applyHomepageCatalogRules($query))
            ->tap(fn ($query) => $this->rankHomepageProducts($query))
            ->limit(self::PROMOTED_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function loadTrendingProducts(int $limit): Collection
    {
        return $this->trendingProducts->forHomepage(
            min($limit, self::TRENDING_LIMIT),
            $this->priorityStateId,
            $this->priorityCityId,
        );
    }

    /**
     * @return Collection<int, Product>
     */
    private function loadSpecialOffers(): Collection
    {
        return Product::query()
            ->listed()
            ->with($this->productRelations())
            ->withCount('options')
            ->where('is_special_offer', true)
            ->tap(fn ($query) => $this->applyHomepageCatalogRules($query))
            ->tap(fn ($query) => $this->rankHomepageProducts($query))
            ->limit(self::SPECIAL_OFFERS_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, Brand>
     */
    private function loadBrands(): Collection
    {
        if (! $this->brandSettings->isEnabled()) {
            return collect();
        }

        return Brand::query()
            ->where('show_on_slider', true)
            ->whereNotNull('image_path')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadBlogPosts(): array
    {
        return BlogPost::query()
            ->with('categories:id,name,slug')
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::BLOG_LIMIT)
            ->get()
            ->map(fn (BlogPost $post) => [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->title,
                'summary' => $post->summary,
                'image_path' => $post->image_path,
                'published_at' => $post->published_at?->toIso8601String(),
                'categories' => $post->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadBanners(): array
    {
        $columns = ['id', 'title', 'image_path', 'link', 'sort_order'];
        if (Schema::hasColumn('homepage_banners', 'banner_location')) {
            $columns[] = 'banner_location';
        }
        if (Schema::hasColumn('homepage_banners', 'banner_width')) {
            $columns[] = 'banner_width';
        }

        return HomepageBanner::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get($columns)
            ->groupBy(fn (HomepageBanner $banner) => $banner->banner_location ?: 'default')
            ->map(fn (Collection $group) => $group->values()->all())
            ->except(self::MOBILE_BANNER_LOCATIONS)
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadMobileBanners(): array
    {
        $columns = ['id', 'title', 'image_path', 'link', 'sort_order'];
        if (Schema::hasColumn('homepage_banners', 'banner_location')) {
            $columns[] = 'banner_location';
        }
        if (Schema::hasColumn('homepage_banners', 'banner_width')) {
            $columns[] = 'banner_width';
        }

        return HomepageBanner::query()
            ->where('is_active', true)
            ->whereIn('banner_location', self::MOBILE_BANNER_LOCATIONS)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get($columns)
            ->groupBy(fn (HomepageBanner $banner) => $banner->banner_location ?: 'mobile_other')
            ->map(fn (Collection $group) => $group->values()->all())
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, mixed>
     */
    private function sectionPayload(
        string $key,
        string $title,
        ?Category $category,
        string $viewAllPath,
        Collection $products,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'category_id' => $category?->id,
            'category_slug' => $category?->slug,
            'view_all_path' => $viewAllPath,
            'banner_location' => 'new_arrivals',
            'products' => ProductResource::collection($products)->resolve(),
        ];
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function categoryIdsForSlugs(array $slugs): array
    {
        $ids = [];
        foreach ($slugs as $slug) {
            $category = Category::query()->where('slug', $slug)->where('status', true)->first();
            if ($category === null) {
                continue;
            }

            $ids = array_merge($ids, $this->categoryTreeIds($category->id));
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $slugs
     */
    private function firstCategoryForSlugs(array $slugs): ?Category
    {
        foreach ($slugs as $slug) {
            $category = Category::query()->where('slug', $slug)->where('status', true)->first();
            if ($category !== null) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function categoryTreeIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = Category::query()
            ->where('parent_id', $categoryId)
            ->where('status', true)
            ->pluck('id');

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->categoryTreeIds((int) $childId));
        }

        return $ids;
    }

    /**
     * @param  list<int>  $categoryIds
     * @return Collection<int, Product>
     */
    private function latestProductsInCategories(array $categoryIds, int $limit): Collection
    {
        if ($categoryIds === []) {
            return collect();
        }

        return Product::query()
            ->listed()
            ->with($this->productRelations())
            ->withCount('options')
            ->whereIn('category_id', $categoryIds)
            ->tap(fn ($query) => $this->applyHomepageCatalogRules($query))
            ->tap(fn ($query) => ProductLocationPriorityQuery::apply($query, $this->priorityStateId, $this->priorityCityId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<int>  $categoryIds
     * @return Collection<int, Product>
     */
    private function latestProductsExcludingCategories(array $categoryIds, int $limit): Collection
    {
        return Product::query()
            ->listed()
            ->with($this->productRelations())
            ->withCount('options')
            ->when($categoryIds !== [], fn ($query) => $query->whereNotIn('category_id', $categoryIds))
            ->tap(fn ($query) => $this->applyHomepageCatalogRules($query))
            ->tap(fn ($query) => ProductLocationPriorityQuery::apply($query, $this->priorityStateId, $this->priorityCityId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function carouselProductsForCategory(int $categoryId): Collection
    {
        return Product::query()
            ->listed()
            ->with($this->productRelations())
            ->withCount('options')
            ->where('category_id', $categoryId)
            ->tap(fn ($query) => $this->applyHomepageCatalogRules($query))
            ->tap(fn ($query) => $this->rankHomepageProducts($query))
            ->limit(self::CAROUSEL_PRODUCT_LIMIT)
            ->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     */
    private function applyHomepageCatalogRules($query): void
    {
        $this->membershipCatalogVisibility->applyVendorMembershipVisibility($query);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     */
    private function rankHomepageProducts($query): void
    {
        if (! $this->listingRank->featuredProductsSortEnabled()) {
            ProductLocationPriorityQuery::apply($query, $this->priorityStateId, $this->priorityCityId);
            $query->orderByDesc('created_at');

            return;
        }

        $criteria = new ProductListingFilterCriteria(
            priorityStateId: $this->priorityStateId,
            priorityCityId: $this->priorityCityId,
        );
        $this->listingRank->apply($query, $criteria);
    }

    /**
     * @return array<int, string>
     */
    private function productRelations(): array
    {
        return ProductResource::listEagerLoads();
    }

    /**
     * @param  array<string, mixed>  $platform
     */
    private function platformBool(array $platform, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $platform)) {
            return $default;
        }

        $value = $platform[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    private function normalizeFeaCategoriesDesign(string $value): string
    {
        return match ($value) {
            'grid_layout', 'square_layout' => 'grid_layout',
            'round_boxes', 'round_layout' => 'round_boxes',
            default => 'round_boxes',
        };
    }

    private function normalizeProductGridLayout(string $value): string
    {
        return $value === 'masonry' ? 'masonry' : 'rows';
    }

    private function normalizeRecommendedProductsCount(mixed $value): int
    {
        return max(1, min(10, (int) $value));
    }

    /**
     * Latest + promoted homepage grids were accidentally hidden when saving other homepage settings.
     * If both toggles are off, fall back to enabled so product sections still render.
     *
     * @param  array<string, mixed>  $platform
     */
    private function homepageProductVisibilityEnabled(array $platform, string $key, bool $default): bool
    {
        if (
            ! $this->platformBool($platform, 'index_latest_products', true)
            && ! $this->platformBool($platform, 'index_promoted_products', true)
        ) {
            return $default;
        }

        return $this->platformBool($platform, $key, $default);
    }

    private function categoryListingPath(Category $category): string
    {
        if (! $category->slug) {
            return '/products?category_id='.$category->id;
        }

        if (! $category->parent_id) {
            return '/'.$category->slug;
        }

        $parent = Category::query()->find($category->parent_id);
        if ($parent?->slug) {
            return '/'.$parent->slug.'/'.$category->slug;
        }

        return '/'.$category->slug;
    }
}
