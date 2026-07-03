<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Requests\Api\V1\StoreProductRequest;
use App\Modules\Selloff\Catalog\Http\Requests\Api\V1\UpdateProductRequest;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use App\Modules\Selloff\Catalog\Support\ProductDraftStatusSync;
use App\Modules\Selloff\Catalog\Support\ProductVendorWriteNormalizer;
use App\Modules\Selloff\Catalog\Services\ProductFilterFieldResolver;
use App\Modules\Selloff\Catalog\Services\ProductListingFilterQuery;
use App\Modules\Selloff\Catalog\Services\ProductListingSortQuery;
use App\Modules\Selloff\Catalog\Support\ProductListingFilterCriteria;
use App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery;
use App\Modules\Selloff\Media\Models\ProductImage;
use App\Modules\Selloff\Catalog\Services\ProductEditedModerationService;
use App\Modules\Selloff\Catalog\Services\ProductRecommendationService;
use App\Modules\Selloff\Catalog\Services\ProductShippingEstimateService;
use App\Modules\Selloff\Catalog\Services\ProductSkuGenerator;
use App\Modules\Selloff\Catalog\Services\SyncProductCatalogExtrasService;
use App\Modules\Selloff\Catalog\Services\VendorListingMetricsRecorder;
use App\Modules\Selloff\Payment\Services\MembershipListingGuardService;
use App\Services\Mobile\MobileCatalogCompatService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(
        private readonly MobileCatalogCompatService $catalogCompat,
        private readonly SyncProductCatalogExtrasService $catalogExtras,
        private readonly ProductSkuGenerator $skuGenerator,
        private readonly ProductListingFilterQuery $listingFilters,
        private readonly ProductListingSortQuery $listingSort,
        private readonly ProductFilterFieldResolver $filterFields,
        private readonly MembershipListingGuardService $membershipListingGuard,
        private readonly ProductVendorWriteNormalizer $vendorWriteNormalizer,
        private readonly ProductEditedModerationService $editedModeration,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sort = $request->string('sort', 'most_recent')->toString();
        $direction = $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        $knownKeys = $this->filterFields->knownFilterKeys();
        $criteria = ProductListingFilterCriteria::fromRequest($request, $knownKeys);

        $query = $this->listingFilters->fromCriteria(
            $criteria,
            ProductResource::listEagerLoads(),
            ['options'],
        );

        $this->listingSort->apply($query, $sort, $direction, $criteria);

        $paginator = $query->paginate(min($request->integer('per_page', 15), 100));
        $paginator->through(fn (Product $product) => new ProductResource($product));

        return ApiResponse::success($paginator);
    }

    public function show(string $product, Request $request, VendorListingMetricsRecorder $metrics): JsonResponse
    {
        $model = $this->resolvePublishedProduct($product, detail: true);
        $metrics->recordProductView($model, $request);

        return ApiResponse::success(new ProductResource($model));
    }

    public function shippingEstimate(
        string $product,
        Request $request,
        ProductShippingEstimateService $estimates,
    ): JsonResponse {
        $model = $this->resolvePublishedProduct($product);
        $user = $request->user();

        $countryId = $request->integer('country_id') ?: ($user?->country_id ? (int) $user->country_id : null);
        $stateId = $request->integer('state_id') ?: ($user?->state_id ? (int) $user->state_id : null);

        return ApiResponse::success($estimates->estimate($model, $countryId, $stateId));
    }

    public function recommended(
        Request $request,
        ProductRecommendationService $recommendations,
        PlatformSettingsService $platformSettings,
    ): JsonResponse
    {
        $raw = $request->input('product_ids', '');
        $ids = is_array($raw)
            ? array_map('intval', $raw)
            : array_map('intval', array_filter(explode(',', (string) $raw)));

        $platform = $platformSettings->all();
        $configuredLimit = max(1, min(10, (int) ($platform['index_recommended_products_count'] ?? config('selloff.platform_settings.index_recommended_products_count', 10))));
        $limit = min(max($request->integer('limit', $configuredLimit), 1), $configuredLimit);
        $priority = ProductLocationPriorityQuery::fromRequest($request);

        $products = $recommendations->recommend(
            $ids,
            $limit,
            $priority['priority_state_id'],
            $priority['priority_city_id'],
        );

        return ApiResponse::success(ProductResource::collection($products));
    }

    public function related(Request $request, string $product): JsonResponse
    {
        $model = $this->resolvePublishedProduct($product);
        $limit = min(max($request->integer('limit', 12), 1), 20);
        $related = $this->catalogCompat->relatedProducts($model->id, $limit);

        return ApiResponse::success(
            ProductResource::collection(
                $related->load(ProductResource::listEagerLoads())
                    ->loadCount('options'),
            ),
        );
    }

    private function resolvePublishedProduct(string $product, bool $detail = false): Product
    {
        $query = Product::query()
            ->with([
                ...ProductResource::listEagerLoads(),
                'category.parent.translations',
            ])
            ->withCount('options');

        if ($detail) {
            $query->with([
                'options.values',
                'variants.optionValues',
                'customFieldProducts.customField',
                'customFieldProducts.option',
            ]);
        }

        $model = is_numeric($product)
            ? $query->where('id', (int) $product)->firstOrFail()
            : $query->where('slug', $product)->firstOrFail();

        abort_unless(
            $model->status === 'published'
            && $model->visibility === 'visible'
            && $model->is_active,
            404,
        );

        return $model;
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $title = $data['title'];
        $images = $data['images'] ?? [];
        $options = $data['options'] ?? null;
        $digitalFiles = $data['digital_files'] ?? null;
        $licenseKeys = $data['license_keys'] ?? null;
        $tags = $data['tags'] ?? null;
        $customFields = $data['custom_fields'] ?? null;
        unset(
            $data['title'],
            $data['description'],
            $data['short_description'],
            $data['images'],
            $data['options'],
            $data['digital_files'],
            $data['license_keys'],
            $data['tags'],
            $data['custom_fields'],
        );

        $this->applyGeneratedSku($data, (int) $request->user()->id);
        $data = $this->vendorWriteNormalizer->normalize($data, $data['type'] ?? null);

        $createAttributes = ProductDraftStatusSync::apply([
            ...$data,
            'vendor_id' => $request->user()->id,
            'slug' => $data['slug'] ?? Str::slug($title),
            'status' => $data['status'] ?? 'published',
            'visibility' => $data['visibility'] ?? 'visible',
            'currency_code' => $data['currency_code'] ?? 'NGN',
            'stock' => $data['stock'] ?? 0,
        ]);

        $this->membershipListingGuard->assertCanConsumeListingSlot(
            $request->user(),
            isset($createAttributes['category_id']) ? (int) $createAttributes['category_id'] : null,
            null,
            $createAttributes,
        );

        $product = Product::query()->create($createAttributes);

        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'en',
            'title' => $title,
            'description' => $request->input('description'),
            'short_description' => $request->input('short_description'),
        ]);

        $this->syncImages($product, $request->input('images', []));
        $this->catalogExtras->sync(
            $product,
            $options,
            $digitalFiles,
            $licenseKeys,
            $request->user()->id,
            $tags,
            $customFields,
        );

        return ApiResponse::success(
            new ProductResource($product->load([
                'translations',
                'vendor.vendorProfile',
                'category.translations',
                'brand',
                'images',
                'options.values',
                'digitalFiles',
                'licenseKeys',
                'tags',
                'customFieldProducts',
                'country',
                'state',
                'city',
            ])),
            201,
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        abort_unless((int) $product->vendor_id === (int) $request->user()->id || $request->user()->can('admin_panel'), 403);

        $data = $request->validated();
        $translationFields = array_intersect_key($data, array_flip(['title', 'description', 'short_description']));
        $images = $data['images'] ?? null;
        $options = array_key_exists('options', $data) ? $data['options'] : null;
        $digitalFiles = array_key_exists('digital_files', $data) ? $data['digital_files'] : null;
        $licenseKeys = $data['license_keys'] ?? null;
        $tags = array_key_exists('tags', $data) ? $data['tags'] : null;
        $customFields = array_key_exists('custom_fields', $data) ? $data['custom_fields'] : null;
        unset(
            $data['title'],
            $data['description'],
            $data['short_description'],
            $data['images'],
            $data['options'],
            $data['digital_files'],
            $data['license_keys'],
            $data['tags'],
            $data['custom_fields'],
        );

        if (! empty($data)) {
            $this->applyGeneratedSku($data, (int) $product->vendor_id, (string) ($data['listing_type'] ?? $product->listing_type ?? 'ordinary_listing'));
            $data = $this->vendorWriteNormalizer->normalize($data, $data['type'] ?? $product->type);
            $updateAttributes = ProductDraftStatusSync::apply($data);
            $this->membershipListingGuard->assertCanConsumeListingSlot(
                $request->user(),
                isset($updateAttributes['category_id']) ? (int) $updateAttributes['category_id'] : null,
                $product,
                $updateAttributes,
            );
            $product->update($updateAttributes);
        }

        if (! empty($translationFields)) {
            ProductTranslation::query()->updateOrCreate(
                ['product_id' => $product->id, 'locale' => 'en'],
                $translationFields,
            );
        }

        if ($images !== null) {
            $this->syncImages($product, $images);
        }

        $this->catalogExtras->sync(
            $product,
            $options,
            $digitalFiles,
            $licenseKeys,
            $request->user()->id,
            $tags,
            $customFields,
        );

        if (
            $request->user()->cannot('admin_panel')
            && $request->hasAny(['price', 'price_discounted', 'images'])
        ) {
            $this->editedModeration->applyAfterVendorEdit($product->fresh(), $request->user());
        }

        return ApiResponse::success(
            new ProductResource($product->fresh()->load([
                'translations',
                'vendor.vendorProfile',
                'category.translations',
                'brand',
                'images',
                'options.values',
                'digitalFiles',
                'licenseKeys',
                'tags',
                'customFieldProducts',
                'country',
                'state',
                'city',
            ])),
        );
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        abort_unless((int) $product->vendor_id === (int) $request->user()->id || $request->user()->can('admin_panel'), 403);

        $product->delete();

        return ApiResponse::success(message: 'Deleted.');
    }

    /** @param  array<int, array{path?: string, disk?: string, url?: string}>  $images */
    private function syncImages(Product $product, array $images): void
    {
        $product->images()->delete();

        foreach (array_values($images) as $index => $image) {
            if (empty($image['path'])) {
                continue;
            }

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $image['path'],
                'disk' => $image['disk'] ?? config('selloff.media_disk', 'public'),
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyGeneratedSku(array &$data, int $vendorId, ?string $listingType = null): void
    {
        $listingType ??= (string) ($data['listing_type'] ?? 'ordinary_listing');
        $sku = array_key_exists('sku', $data) ? (is_string($data['sku']) ? $data['sku'] : null) : null;

        if ($this->skuGenerator->shouldGenerate($sku, $listingType)) {
            $data['sku'] = $this->skuGenerator->generate($vendorId);
        }
    }
}
