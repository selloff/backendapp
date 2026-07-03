<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\AdminCategoryResource;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CategoryTranslation;
use App\Modules\Selloff\Catalog\Services\CategoryPathService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    /** @var list<string> */
    private const SETTINGS_KEYS = [
        'sort_categories',
        'sort_parent_categories_by_order',
    ];

    public function __construct(
        private readonly CategoryPathService $paths,
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('flat')) {
            $categories = Category::query()
                ->with('translations')
                ->orderBy('category_order')
                ->get();

            return ApiResponse::success(AdminCategoryResource::collection($categories));
        }

        if ($request->filled('q')) {
            $perPage = min(100, max(1, (int) $request->input('per_page', 15)));
            $q = trim((string) $request->input('q'));

            $paginator = Category::query()
                ->with('translations')
                ->withCount('children')
                ->whereHas('translations', function ($query) use ($q) {
                    $query->whereLike('name', $q.'%', caseSensitive: false);
                })
                ->orderByDesc('created_at')
                ->paginate($perPage);

            return ApiResponse::success([
                'data' => AdminCategoryResource::collection($paginator->items()),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ]);
        }

        $query = Category::query()
            ->with('translations')
            ->withCount('children');

        if ($request->has('parent_id')) {
            $parentId = $request->input('parent_id');
            if ($parentId === null || $parentId === '' || $parentId === '0' || $parentId === 0) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (int) $parentId);
            }
        } elseif ($request->boolean('roots')) {
            $query->whereNull('parent_id');
        } else {
            $query->whereNull('parent_id')
                ->with(['children.translations']);
        }

        $this->applySort($query);

        $categories = $query->get();

        return ApiResponse::success(AdminCategoryResource::collection($categories));
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('translations');
        $category->parent_chain = $this->paths->parentChainIds($category->id);

        return ApiResponse::success(new AdminCategoryResource($category));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateCategory($request);

        return DB::transaction(function () use ($data) {
            $translation = $this->extractTranslationFields($data);
            unset($data['name']);

            if (! isset($data['category_order'])) {
                $data['category_order'] = 1;
            }

            if (array_key_exists('image_path', $data) && $data['image_path'] === null) {
                $data['storage'] = 'local';
            }

            $category = Category::query()->create($data);

            CategoryTranslation::query()->create([
                'category_id' => $category->id,
                'locale' => 'en',
                'name' => $translation['name'],
                ...$translation['meta'],
            ]);

            $this->paths->insertPaths($category->id, $category->parent_id);
            $category->load('translations');

            return ApiResponse::success(new AdminCategoryResource($category), 201);
        });
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $this->validateCategory($request, $category->id);
        $oldParentId = $category->parent_id;
        $newParentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $oldParentId;

        if ($newParentId !== null && $this->paths->isDescendant($category->id, $newParentId)) {
            return ApiResponse::error('A category cannot be moved under one of its descendants.', 422);
        }

        return DB::transaction(function () use ($data, $category, $oldParentId) {
            $translation = $this->extractTranslationFields($data);
            unset($data['name']);

            if (array_key_exists('image_path', $data) && $data['image_path'] === null) {
                $data['storage'] = 'local';
            }

            if (! empty($data)) {
                $category->update($data);
            }

            if ($translation['name'] !== null || $translation['meta'] !== []) {
                $existing = CategoryTranslation::query()->firstOrNew([
                    'category_id' => $category->id,
                    'locale' => 'en',
                ]);

                if ($translation['name'] !== null) {
                    $existing->name = $translation['name'];
                }

                foreach ($translation['meta'] as $key => $value) {
                    $existing->{$key} = $value;
                }

                $existing->save();
            }

            if (array_key_exists('parent_id', $data) && $oldParentId !== $newParentId) {
                $category->refresh();
                $this->paths->moveSubtree($category->id, $category->parent_id);
            }

            $category->load(['translations', 'children.translations']);

            return ApiResponse::success(new AdminCategoryResource($category));
        });
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->children()->exists()) {
            return ApiResponse::error('Remove child categories first.', 422);
        }

        DB::transaction(function () use ($category) {
            DB::table('category_paths')
                ->where('category_id', $category->id)
                ->orWhere('ancestor_id', $category->id)
                ->delete();

            $category->translations()->delete();
            $category->delete();
        });

        return ApiResponse::success(message: 'Category deleted.');
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
            'sort_categories' => ['required', 'string', Rule::in(['category_order', 'date', 'date_desc', 'alphabetically'])],
            'sort_parent_categories_by_order' => ['nullable', 'boolean'],
        ]);

        $this->platformSettings->upsertMany([
            'sort_categories' => $data['sort_categories'],
            'sort_parent_categories_by_order' => (bool) ($data['sort_parent_categories_by_order'] ?? false),
        ], 'catalog');

        return $this->settings();
    }

    public function rebuildPaths(): JsonResponse
    {
        $this->paths->rebuildAll();

        return ApiResponse::success(message: 'Category paths rebuilt.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $parentId = $data['parent_id'] ?? null;

        DB::transaction(function () use ($data, $parentId): void {
            foreach ($data['category_ids'] as $index => $categoryId) {
                $category = Category::query()->findOrFail($categoryId);

                if ((int) ($category->parent_id ?? 0) !== (int) ($parentId ?? 0)) {
                    abort(422, 'Category parent mismatch for reorder.');
                }

                $category->update(['category_order' => $index + 1]);
            }
        });

        return ApiResponse::success([
            'reordered' => count($data['category_ids']),
            'parent_id' => $parentId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?int $categoryId = null): array
    {
        $data = $request->validate([
            'name' => [$categoryId === null ? 'required' : 'sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$categoryId])],
            'slug' => ['nullable', 'string', 'max:255'],
            'category_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'status' => ['nullable', 'boolean'],
            'show_on_main_menu' => ['nullable', 'boolean'],
            'show_image_on_main_menu' => ['nullable', 'boolean'],
            'show_description' => ['nullable', 'boolean'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'storage' => ['nullable', 'string', 'max:50'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:5000'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'commission_mode' => ['nullable', 'string', Rule::in(['default', 'custom', 'none'])],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
        ]);

        $hasCommissionMode = array_key_exists('commission_mode', $data);
        $commission = $hasCommissionMode ? $this->mapCommission($data) : [];
        unset($data['commission_mode'], $data['commission_rate']);

        if (isset($data['slug']) && $data['slug'] === '') {
            unset($data['slug']);
        }

        if (! isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (isset($data['parent_id']) && (int) $data['parent_id'] === 0) {
            $data['parent_id'] = null;
        }

        if ($hasCommissionMode) {
            return array_merge($data, $commission);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapCommission(array $data): array
    {
        $mode = $data['commission_mode'] ?? 'default';

        if ($mode === 'custom') {
            return [
                'is_commission_set' => true,
                'commission_rate' => $data['commission_rate'] ?? 0,
            ];
        }

        if ($mode === 'none') {
            return [
                'is_commission_set' => true,
                'commission_rate' => 0,
            ];
        }

        return [
            'is_commission_set' => false,
            'commission_rate' => 0,
        ];
    }

    private function applySort(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $settings = $this->platformSettings->all();
        $sort = (string) ($settings['sort_categories'] ?? 'category_order');

        match ($sort) {
            'date' => $query->orderBy('created_at'),
            'date_desc' => $query->orderByDesc('created_at'),
            'alphabetically' => $query->orderBy(
                CategoryTranslation::query()
                    ->select('name')
                    ->whereColumn('category_translations.category_id', 'categories.id')
                    ->where('locale', 'en')
                    ->limit(1),
            ),
            default => $query->orderBy('category_order'),
        };
    }

    private function defaultSetting(string $key): mixed
    {
        return match ($key) {
            'sort_categories' => 'category_order',
            'sort_parent_categories_by_order' => true,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: ?string, meta: array<string, ?string>}
     */
    private function extractTranslationFields(array &$data): array
    {
        $name = array_key_exists('name', $data) ? (string) $data['name'] : null;
        $meta = [];

        foreach (['meta_title', 'meta_description', 'meta_keywords'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                $meta[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
                unset($data[$field]);
            }
        }

        return [
            'name' => $name,
            'meta' => $meta,
        ];
    }
}
