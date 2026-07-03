<?php

namespace App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Models\Language;
use App\Modules\Selloff\Content\Models\BlogCategory;
use App\Modules\Selloff\Content\Models\BlogPost;
use App\Modules\Selloff\Content\Models\BlogTag;
use App\Modules\Selloff\Content\Models\Page;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminBlogController extends Controller
{
    public function indexPosts(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);
        $search = trim((string) ($request->input('q') ?: $request->input('search', '')));
        $langId = $request->input('lang_id');

        $posts = BlogPost::query()
            ->with(['categories', 'tags'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when(
                $langId !== null && $langId !== '' && Schema::hasColumn('blog_posts', 'lang_id'),
                fn ($query) => $query->where('lang_id', (int) $langId),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $posts->getCollection()->transform(fn (BlogPost $post) => $this->formatPost($post));

        return ApiResponse::success($posts);
    }

    public function showPost(BlogPost $blogPost): JsonResponse
    {
        $blogPost->load(['categories', 'tags']);

        return ApiResponse::success($this->formatPost($blogPost));
    }

    public function storePost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:500'],
            'summary' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'is_published' => ['nullable', 'boolean'],
            'category_id' => ['required', 'integer', 'exists:blog_categories,id'],
            'lang_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $payload = [
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'content' => $data['content'] ?? null,
            'user_id' => $request->user()->id,
            'slug' => $this->resolvePostSlug($data['title'], $data['slug'] ?? null),
            'is_published' => $data['is_published'] ?? false,
            'published_at' => ($data['is_published'] ?? false) ? now() : null,
        ];

        if (Schema::hasColumn('blog_posts', 'lang_id')) {
            $payload['lang_id'] = $data['lang_id'] ?? 1;
        }
        if (Schema::hasColumn('blog_posts', 'keywords')) {
            $payload['keywords'] = $data['keywords'] ?? null;
        }
        if (Schema::hasColumn('blog_posts', 'image_path')) {
            $payload['image_path'] = $data['image_path'] ?? null;
        }

        $post = BlogPost::query()->create($payload);

        $this->syncCategory($post, $data['category_id']);
        $this->syncTags($post, $data['tags'] ?? []);

        return ApiResponse::success($this->formatPost($post->fresh()->load(['categories', 'tags'])), 201);
    }

    public function updatePost(Request $request, BlogPost $blogPost): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:500'],
            'summary' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'is_published' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'lang_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (array_key_exists('title', $data)) {
            $blogPost->title = $data['title'];
        }
        if (array_key_exists('slug', $data)) {
            $blogPost->slug = $this->resolvePostSlug($blogPost->title, $data['slug'], $blogPost->id);
        } elseif (array_key_exists('title', $data)) {
            $blogPost->slug = $this->resolvePostSlug($data['title'], $blogPost->slug, $blogPost->id);
        }
        if (array_key_exists('summary', $data)) {
            $blogPost->summary = $data['summary'];
        }
        if (array_key_exists('keywords', $data) && Schema::hasColumn('blog_posts', 'keywords')) {
            $blogPost->keywords = $data['keywords'];
        }
        if (array_key_exists('content', $data)) {
            $blogPost->content = $data['content'];
        }
        if (array_key_exists('image_path', $data) && Schema::hasColumn('blog_posts', 'image_path')) {
            $blogPost->image_path = $data['image_path'];
        }
        if (array_key_exists('is_published', $data)) {
            $blogPost->is_published = (bool) $data['is_published'];
            $blogPost->published_at = $blogPost->is_published ? ($blogPost->published_at ?? now()) : null;
        }
        if (array_key_exists('lang_id', $data) && Schema::hasColumn('blog_posts', 'lang_id')) {
            $blogPost->lang_id = (int) $data['lang_id'];
        }

        $blogPost->save();

        if (array_key_exists('category_id', $data)) {
            $this->syncCategory($blogPost, $data['category_id']);
        }
        if (array_key_exists('tags', $data)) {
            $this->syncTags($blogPost, $data['tags'] ?? []);
        }

        return ApiResponse::success($this->formatPost($blogPost->fresh()->load(['categories', 'tags'])));
    }

    public function destroyPost(BlogPost $blogPost): JsonResponse
    {
        $blogPost->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function indexCategories(): JsonResponse
    {
        $categories = BlogCategory::query()
            ->when(
                Schema::hasColumn('blog_categories', 'category_order'),
                fn ($query) => $query->orderBy('category_order')->orderBy('id'),
                fn ($query) => $query->orderBy('name'),
            )
            ->get()
            ->map(fn (BlogCategory $category) => $this->formatCategory($category));

        return ApiResponse::success($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'category_order' => ['nullable', 'integer', 'min:1'],
            'lang_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $payload = [
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
        ];

        if (Schema::hasColumn('blog_categories', 'lang_id')) {
            $payload['lang_id'] = $data['lang_id'] ?? 1;
        }
        if (Schema::hasColumn('blog_categories', 'description')) {
            $payload['description'] = $data['description'] ?? null;
        }
        if (Schema::hasColumn('blog_categories', 'keywords')) {
            $payload['keywords'] = $data['keywords'] ?? null;
        }
        if (Schema::hasColumn('blog_categories', 'category_order')) {
            $payload['category_order'] = $data['category_order'] ?? 1;
        }

        $category = BlogCategory::query()->create($payload);

        return ApiResponse::success($this->formatCategory($category), 201);
    }

    public function updateCategory(Request $request, BlogCategory $blogCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'category_order' => ['nullable', 'integer', 'min:1'],
            'lang_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $blogCategory->update($data);

        return ApiResponse::success($this->formatCategory($blogCategory->fresh()));
    }

    public function destroyCategory(BlogCategory $blogCategory): JsonResponse
    {
        $blogCategory->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function indexPages(): JsonResponse
    {
        $query = Page::query();

        if (Schema::hasColumn('pages', 'page_order')) {
            $query->orderBy('page_order')->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        $pages = $query->get()->map(fn (Page $page) => $this->formatPage($page));

        return ApiResponse::success($pages);
    }

    public function storePage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', 'max:10'],
            'location' => ['nullable', 'string', 'max:50'],
            'is_custom' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'title' => $data['title'],
            'slug' => $data['slug'] ?? Str::slug($data['title']),
            'content' => $data['content'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'locale' => $data['locale'] ?? 'en',
        ];

        if (Schema::hasColumn('pages', 'location')) {
            $payload['location'] = $data['location'] ?? 'information';
        }
        if (Schema::hasColumn('pages', 'is_custom')) {
            $payload['is_custom'] = $data['is_custom'] ?? true;
        }

        $page = Page::query()->create($payload);

        return ApiResponse::success($this->formatPage($page), 201);
    }

    public function updatePage(Request $request, Page $page): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', 'max:10'],
            'location' => ['nullable', 'string', 'max:50'],
            'is_custom' => ['nullable', 'boolean'],
        ]);

        $page->update($data);

        return ApiResponse::success($this->formatPage($page->fresh()));
    }

    public function destroyPage(Page $page): JsonResponse
    {
        if ($this->isDefaultPage($page)) {
            return ApiResponse::error('Default pages cannot be deleted.', 422);
        }

        $page->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));

        return in_array($perPage, [15, 30, 60, 100], true) ? $perPage : 15;
    }

    private function formatPost(BlogPost $post): array
    {
        $category = $post->categories->first();
        $langId = Schema::hasColumn('blog_posts', 'lang_id') ? (int) ($post->lang_id ?? 1) : 1;

        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'summary' => $post->summary,
            'keywords' => Schema::hasColumn('blog_posts', 'keywords') ? $post->keywords : null,
            'content' => $post->content,
            'tags' => $post->relationLoaded('tags')
                ? $post->tags->pluck('tag')->values()->all()
                : [],
            'is_published' => (bool) $post->is_published,
            'lang_id' => $langId,
            'language' => $this->languageName($langId),
            'category_id' => $category?->id,
            'category_name' => $category?->name,
            'category_slug' => $category?->slug,
            'image_path' => $post->image_path,
            'image_url' => $this->blogImageUrl($post->image_path),
            'created_at' => $post->created_at?->toIso8601String(),
            'categories' => $post->categories->map(fn (BlogCategory $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
            ])->values()->all(),
        ];
    }

    private function formatCategory(BlogCategory $category): array
    {
        $langId = Schema::hasColumn('blog_categories', 'lang_id') ? (int) ($category->lang_id ?? 1) : 1;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => Schema::hasColumn('blog_categories', 'description') ? $category->description : null,
            'keywords' => Schema::hasColumn('blog_categories', 'keywords') ? $category->keywords : null,
            'category_order' => Schema::hasColumn('blog_categories', 'category_order') ? (int) ($category->category_order ?? 1) : 1,
            'lang_id' => $langId,
            'language' => $this->languageName($langId),
        ];
    }

    private function formatPage(Page $page): array
    {
        $langId = Schema::hasColumn('pages', 'lang_id') ? (int) ($page->lang_id ?? 0) : 0;
        $locale = $page->locale ?? 'en';
        $language = $langId > 0
            ? ($this->languageName($langId) ?? strtoupper($locale))
            : (Language::query()->where('code', $locale)->value('name') ?? strtoupper($locale));
        $location = Schema::hasColumn('pages', 'location') ? ($page->location ?? 'information') : 'information';
        $isCustom = ! $this->isDefaultPage($page);

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => $page->content,
            'locale' => $locale,
            'lang_id' => $langId > 0 ? $langId : null,
            'language' => $language,
            'location' => $location,
            'location_label' => $this->pageLocationLabel($location),
            'visibility' => (bool) $page->is_active,
            'is_active' => (bool) $page->is_active,
            'is_custom' => $isCustom,
            'page_order' => Schema::hasColumn('pages', 'page_order') ? (int) ($page->page_order ?? 1) : 1,
            'description' => Schema::hasColumn('pages', 'description') ? $page->description : null,
            'keywords' => Schema::hasColumn('pages', 'keywords') ? $page->keywords : null,
            'title_active' => Schema::hasColumn('pages', 'title_active') ? (bool) ($page->title_active ?? true) : true,
            'page_default_name' => Schema::hasColumn('pages', 'page_default_name') ? $page->page_default_name : null,
            'created_at' => $page->created_at?->toIso8601String(),
        ];
    }

    private function isDefaultPage(Page $page): bool
    {
        if (Schema::hasColumn('pages', 'page_default_name') && filled($page->page_default_name)) {
            return true;
        }

        if (Schema::hasColumn('pages', 'is_custom')) {
            return ! (bool) ($page->is_custom ?? true);
        }

        return false;
    }

    private function pageLocationLabel(string $location): string
    {
        return match ($location) {
            'top_menu' => 'Top menu',
            'footer_bottom' => 'Footer bottom',
            'quick_links' => 'Footer quick links',
            'information' => 'Footer information',
            default => str_replace('_', ' ', ucfirst($location)),
        };
    }

    private function languageName(int $langId): ?string
    {
        return Language::query()->whereKey($langId)->value('name');
    }

    private function blogImageUrl(?string $imagePath): ?string
    {
        if ($imagePath === null || $imagePath === '') {
            return null;
        }

        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        return url('/storage/'.$imagePath);
    }

    private function syncCategory(BlogPost $post, mixed $categoryId): void
    {
        if ($categoryId === null || $categoryId === '') {
            $post->categories()->sync([]);

            return;
        }

        $post->categories()->sync([(int) $categoryId]);
    }

    /**
     * @param  array<int, string>|null  $tags
     */
    private function syncTags(BlogPost $post, ?array $tags): void
    {
        $post->tags()->delete();

        if ($tags === null || $tags === []) {
            return;
        }

        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '' || strlen($tag) <= 1) {
                continue;
            }

            $tagSlug = Str::slug($tag);
            if ($tagSlug === '' || $tagSlug === '-') {
                $tagSlug = 'tag-'.Str::random(8);
            }

            BlogTag::query()->create([
                'blog_post_id' => $post->id,
                'tag' => $tag,
                'tag_slug' => $tagSlug,
            ]);
        }
    }

    private function resolvePostSlug(string $title, ?string $slug, ?int $excludeId = null): string
    {
        $base = $slug !== null && trim($slug) !== '' ? Str::slug(trim($slug)) : Str::slug($title);
        if ($base === '' || $base === '-') {
            $base = 'post';
        }

        $candidate = $base;
        $suffix = 0;

        while (
            BlogPost::query()
                ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }
}
