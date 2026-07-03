<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\AdminBrandResource;
use App\Modules\Selloff\Catalog\Models\Brand;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBrandController extends Controller
{
    /** @var list<string> */
    private const SETTINGS_KEYS = [
        'brand_status',
        'is_brand_optional',
        'brand_where_to_display',
    ];

    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $query = Brand::query()
            ->with(['categories.translations'])
            ->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereLike('name', '%'.$search.'%', caseSensitive: false);
        }

        $paginated = $query->paginate($perPage);

        return ApiResponse::success([
            'data' => AdminBrandResource::collection($paginated->items())->resolve(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    public function show(Brand $brand): JsonResponse
    {
        $brand->load(['categories.translations']);

        return ApiResponse::success(new AdminBrandResource($brand));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateBrand($request);

        $brand = Brand::query()->create([
            'name' => $data['name'],
            'image_path' => $data['image_path'] ?? null,
            'storage' => $data['storage'] ?? 'local',
            'show_on_slider' => $data['show_on_slider'] ?? false,
        ]);

        if (! empty($data['category_ids'])) {
            $brand->categories()->sync($data['category_ids']);
        }

        $brand->load(['categories.translations']);

        return ApiResponse::success(new AdminBrandResource($brand), 201);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $data = $this->validateBrand($request, updating: true);

        if (array_key_exists('image_path', $data) && $data['image_path'] === null) {
            $data['storage'] = 'local';
        }

        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);

        if ($data !== []) {
            $brand->update($data);
        }

        if ($categoryIds !== null) {
            $brand->categories()->sync($categoryIds);
        }

        $brand->load(['categories.translations']);

        return ApiResponse::success(new AdminBrandResource($brand));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->categories()->detach();
        $brand->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function settings(): JsonResponse
    {
        $all = $this->platformSettings->all();
        $settings = [];
        foreach (self::SETTINGS_KEYS as $key) {
            $settings[$key] = $all[$key] ?? $this->defaultSetting($key);
        }

        return ApiResponse::success($settings);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand_status' => ['required', 'boolean'],
            'is_brand_optional' => ['required', 'boolean'],
            'brand_where_to_display' => ['required', 'integer', Rule::in([1, 2])],
        ]);

        $this->platformSettings->upsertMany([
            'brand_status' => (bool) $data['brand_status'],
            'is_brand_optional' => (bool) $data['is_brand_optional'],
            'brand_where_to_display' => (int) $data['brand_where_to_display'],
        ], 'product');

        return $this->settings();
    }

    private function defaultSetting(string $key): mixed
    {
        return config('selloff.platform_settings.'.$key);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBrand(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'storage' => ['nullable', 'string', 'max:50'],
            'show_on_slider' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);
    }
}
