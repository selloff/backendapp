<?php

namespace App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Content\Http\Resources\Api\V1\SliderResource;
use App\Modules\Selloff\Content\Models\HomepageBanner;
use App\Modules\Selloff\Content\Models\Slider;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminHomepageController extends Controller
{
    public function sliders(): JsonResponse
    {
        $sliders = Slider::query()->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success(SliderResource::collection($sliders)->resolve());
    }

    public function storeSlider(Request $request): JsonResponse
    {
        $data = $request->validate($this->sliderRules());

        $slider = Slider::query()->create($this->mapSliderPayload($data));

        return ApiResponse::success((new SliderResource($slider))->resolve(), 201);
    }

    public function updateSlider(Request $request, Slider $slider): JsonResponse
    {
        $data = $request->validate($this->sliderRules(isUpdate: true));

        $slider->update($this->mapSliderPayload($data, partial: true));

        return ApiResponse::success((new SliderResource($slider->fresh()))->resolve());
    }

    public function destroySlider(Slider $slider): JsonResponse
    {
        $slider->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function banners(): JsonResponse
    {
        return ApiResponse::success(HomepageBanner::query()->orderBy('sort_order')->orderBy('id')->get());
    }

    public function storeBanner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'link' => ['nullable', 'string', 'max:500'],
            'banner_location' => ['nullable', 'string', 'max:64'],
            'banner_width' => ['nullable', 'integer', 'min:10', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $banner = HomepageBanner::query()->create([
            'title' => $data['title'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'link' => $data['link'] ?? null,
            'banner_location' => $data['banner_location'] ?? null,
            'banner_width' => $data['banner_width'] ?? 50,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ApiResponse::success($banner, 201);
    }

    public function updateBanner(Request $request, HomepageBanner $homepageBanner): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'link' => ['nullable', 'string', 'max:500'],
            'banner_location' => ['nullable', 'string', 'max:64'],
            'banner_width' => ['nullable', 'integer', 'min:10', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $homepageBanner->update($data);

        return ApiResponse::success($homepageBanner->fresh());
    }

    public function destroyBanner(HomepageBanner $homepageBanner): JsonResponse
    {
        $homepageBanner->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function categoryLayout(): JsonResponse
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('status', true)
            ->with('translations:category_id,locale,name')
            ->orderBy('category_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success([
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->translations->firstWhere('locale', 'en')?->name
                    ?? $category->translations->first()?->name
                    ?? $category->slug,
                'is_featured' => (bool) $category->is_featured,
                'featured_order' => (int) $category->featured_order,
                'show_products_on_index' => (bool) $category->show_products_on_index,
                'homepage_order' => (int) $category->homepage_order,
                'show_subcategory_products' => (bool) $category->show_subcategory_products,
            ])->values(),
            'featured_ids' => $categories
                ->where('is_featured', true)
                ->sortBy('featured_order')
                ->pluck('id')
                ->values(),
            'index_ids' => $categories
                ->where('show_products_on_index', true)
                ->sortBy('homepage_order')
                ->pluck('id')
                ->values(),
        ]);
    }

    public function syncFeaturedCategories(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        DB::transaction(function () use ($data): void {
            Category::query()->where('is_featured', true)->update([
                'is_featured' => false,
                'featured_order' => 0,
            ]);

            foreach ($data['category_ids'] as $index => $categoryId) {
                Category::query()->whereKey($categoryId)->update([
                    'is_featured' => true,
                    'featured_order' => $index + 1,
                ]);
            }
        });

        return $this->categoryLayout();
    }

    public function syncIndexCategories(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'entries' => ['sometimes', 'array'],
            'entries.*.id' => ['required', 'integer', 'exists:categories,id'],
            'entries.*.show_subcategory_products' => ['sometimes', 'boolean'],
        ]);

        $entries = collect($data['entries'] ?? [])
            ->map(fn (array $entry) => [
                'id' => (int) $entry['id'],
                'show_subcategory_products' => (bool) ($entry['show_subcategory_products'] ?? false),
            ])
            ->values();

        if ($entries->isEmpty()) {
            $entries = collect($data['category_ids'] ?? [])->map(fn (int $categoryId) => [
                'id' => $categoryId,
                'show_subcategory_products' => false,
            ]);
        }

        DB::transaction(function () use ($entries): void {
            Category::query()->where('show_products_on_index', true)->update([
                'show_products_on_index' => false,
                'homepage_order' => 0,
                'show_subcategory_products' => false,
            ]);

            foreach ($entries as $index => $entry) {
                Category::query()->whereKey($entry['id'])->update([
                    'show_products_on_index' => true,
                    'homepage_order' => $index + 1,
                    'show_subcategory_products' => $entry['show_subcategory_products'],
                ]);
            }
        });

        return $this->categoryLayout();
    }

    /**
     * @return array<string, mixed>
     */
    private function sliderRules(bool $isUpdate = false): array
    {
        $sometimes = $isUpdate ? 'sometimes' : 'nullable';

        return [
            'title' => [$sometimes, 'nullable', 'string', 'max:255'],
            'description' => [$sometimes, 'nullable', 'string', 'max:1000'],
            'image_path' => [$sometimes, 'nullable', 'string', 'max:500'],
            'image_mobile_path' => [$sometimes, 'nullable', 'string', 'max:500'],
            'link' => [$sometimes, 'nullable', 'string', 'max:500'],
            'sort_order' => [$sometimes, 'nullable', 'integer', 'min:0'],
            'is_active' => [$sometimes, 'nullable', 'boolean'],
            'lang_id' => [$sometimes, 'nullable', 'integer', 'min:1', 'max:255'],
            'button_text' => [$sometimes, 'nullable', 'string', 'max:255'],
            'text_color' => [$sometimes, 'nullable', 'string', 'max:30'],
            'button_color' => [$sometimes, 'nullable', 'string', 'max:30'],
            'button_text_color' => [$sometimes, 'nullable', 'string', 'max:30'],
            'animation_title' => [$sometimes, 'nullable', 'string', 'max:50'],
            'animation_description' => [$sometimes, 'nullable', 'string', 'max:50'],
            'animation_button' => [$sometimes, 'nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapSliderPayload(array $data, bool $partial = false): array
    {
        $mapped = [];

        foreach (['title', 'description', 'image_path', 'image_mobile_path', 'link', 'button_text', 'text_color', 'button_color', 'button_text_color', 'animation_title', 'animation_description', 'animation_button'] as $key) {
            if (array_key_exists($key, $data)) {
                $mapped[$key] = $data[$key];
            }
        }

        if (array_key_exists('sort_order', $data)) {
            $mapped['sort_order'] = $data['sort_order'] ?? 0;
        } elseif (! $partial) {
            $mapped['sort_order'] = 0;
        }

        if (array_key_exists('is_active', $data)) {
            $mapped['is_active'] = $data['is_active'] ?? true;
        } elseif (! $partial) {
            $mapped['is_active'] = true;
        }

        if (array_key_exists('lang_id', $data)) {
            $mapped['lang_id'] = $data['lang_id'] ?? 1;
        } elseif (! $partial) {
            $mapped['lang_id'] = 1;
        }

        return $mapped;
    }
}
