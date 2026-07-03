<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyForeignKeyResolver;
use App\LegacyImport\Support\LegacyValueCoercer;
use App\Support\LegacyTextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContentLegacyImporter extends MultiTableLegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return ['blog_categories', 'blog_posts', 'blog_comments', 'pages'];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importBlogCategories($context, $reader);
        $this->importBlogPosts($context, $reader);
        $this->importBlogComments($context, $reader);
        $this->importPages($context, $reader);
    }

    private function importBlogCategories(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('blog_categories') || ! $reader->hasTable('blog_categories')) {
            return;
        }

        foreach ($reader->rows('blog_categories') as $row) {
            $context->notePlanned('blog_categories');

            $legacyId = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($legacyId <= 0 || $name === '') {
                $context->noteSkipped('blog_categories');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'slug' => $row['slug'] ?? null,
                'name' => $name,
                'legacy_id' => $legacyId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (! $context->dryRun) {
                DB::table('blog_categories')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'blog_categories', $legacyId, 'blog_categories', $legacyId);
            $context->noteImported('blog_categories');
        }
    }

    private function importBlogPosts(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('blog_posts') || ! $reader->hasTable('blog_posts')) {
            return;
        }

        foreach ($reader->rows('blog_posts') as $row) {
            $context->notePlanned('blog_posts');

            $legacyId = (int) ($row['id'] ?? 0);
            $title = trim((string) ($row['title'] ?? ''));
            if ($legacyId <= 0 || $title === '') {
                $context->noteSkipped('blog_posts');

                continue;
            }

            $legacyUserId = (int) ($row['user_id'] ?? 0);
            $userId = $legacyUserId > 0 ? $context->resolveId('users', $legacyUserId) : null;
            $publishedAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'user_id' => $userId,
                'slug' => $row['slug'] ?? null,
                'title' => $title,
                'summary' => LegacyTextNormalizer::restoreLineBreaks($row['summary'] ?? null),
                'content' => LegacyTextNormalizer::restoreLineBreaks($row['content'] ?? null),
                'image_path' => $row['image_default'] ?? $row['image_small'] ?? null,
                'is_published' => true,
                'published_at' => $publishedAt,
                'legacy_id' => $legacyId,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('blog_posts', 'lang_id')) {
                $payload['lang_id'] = (int) ($row['lang_id'] ?? 1);
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('blog_posts', 'keywords')) {
                $payload['keywords'] = $row['keywords'] ?? null;
            }

            if (! $context->dryRun) {
                DB::table('blog_posts')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'blog_posts', $legacyId, 'blog_posts', $legacyId);
            $context->noteImported('blog_posts');

            $this->syncBlogPostCategory($context, $legacyId, (int) ($row['category_id'] ?? 0));
        }
    }

    private function syncBlogPostCategory(LegacyImportContext $context, int $blogPostId, int $legacyCategoryId): void
    {
        if ($legacyCategoryId <= 0) {
            return;
        }

        $categoryId = $context->resolveId('blog_categories', $legacyCategoryId);
        if ($categoryId === null) {
            $context->noteSkipped('blog_post_category');

            return;
        }

        if ($context->dryRun) {
            $context->noteImported('blog_post_category');

            return;
        }

        DB::table('blog_post_category')->updateOrInsert(
            [
                'blog_post_id' => $blogPostId,
                'blog_category_id' => $categoryId,
            ],
            [],
        );

        $context->noteImported('blog_post_category');
    }

    private function importBlogComments(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('blog_comments') || ! $reader->hasTable('blog_comments')) {
            return;
        }

        foreach ($reader->rows('blog_comments') as $row) {
            $context->notePlanned('blog_comments');

            $legacyId = (int) ($row['id'] ?? 0);
            $legacyPostId = (int) ($row['post_id'] ?? 0);
            $blogPostId = LegacyForeignKeyResolver::blogPostId($context, $legacyPostId);

            if ($legacyId <= 0 || $blogPostId === null) {
                $context->noteSkipped('blog_comments');

                continue;
            }

            $legacyUserId = (int) ($row['user_id'] ?? 0);
            $userId = LegacyForeignKeyResolver::userId($context, $legacyUserId);
            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'blog_post_id' => $blogPostId,
                'user_id' => $userId,
                'name' => $row['name'] ?? null,
                'email' => $row['email'] ?? null,
                'comment' => $row['comment'] ?? '',
                'ip_address' => $row['ip_address'] ?? null,
                'status' => (int) ($row['status'] ?? 0) === 1 ? 'approved' : 'pending',
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (! $context->dryRun) {
                DB::table('blog_comments')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'blog_comments', $legacyId, 'blog_comments', $legacyId);
            $context->noteImported('blog_comments');
        }
    }

    private function importPages(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('pages') || ! $reader->hasTable('pages')) {
            return;
        }

        foreach ($reader->rows('pages') as $row) {
            $context->notePlanned('pages');

            $legacyId = (int) ($row['id'] ?? 0);
            $slug = trim((string) ($row['slug'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($legacyId <= 0 || $slug === '' || $title === '') {
                $context->noteSkipped('pages');

                continue;
            }

            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();
            $langId = (int) ($row['lang_id'] ?? 1);
            $locale = $this->languageCodeForId($langId);

            $payload = [
                'id' => $legacyId,
                'slug' => $slug,
                'title' => $title,
                'content' => LegacyTextNormalizer::restoreLineBreaks($row['page_content'] ?? null),
                'locale' => $locale,
                'is_active' => LegacyValueCoercer::bool($row['visibility'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (Schema::hasColumn('pages', 'location')) {
                $payload['location'] = $row['location'] ?? 'information';
            }
            if (Schema::hasColumn('pages', 'is_custom')) {
                $payload['is_custom'] = LegacyValueCoercer::bool($row['is_custom'] ?? 1);
            }
            if (Schema::hasColumn('pages', 'lang_id')) {
                $payload['lang_id'] = $langId;
            }
            if (Schema::hasColumn('pages', 'page_order')) {
                $payload['page_order'] = (int) ($row['page_order'] ?? 1);
            }
            if (Schema::hasColumn('pages', 'description')) {
                $payload['description'] = LegacyTextNormalizer::restoreLineBreaks($row['description'] ?? null);
            }
            if (Schema::hasColumn('pages', 'keywords')) {
                $payload['keywords'] = $row['keywords'] ?? null;
            }
            if (Schema::hasColumn('pages', 'title_active')) {
                $payload['title_active'] = LegacyValueCoercer::bool($row['title_active'] ?? 1);
            }
            if (Schema::hasColumn('pages', 'page_default_name')) {
                $payload['page_default_name'] = $row['page_default_name'] ?? null;
            }

            if (! $context->dryRun) {
                DB::table('pages')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'pages', $legacyId, 'pages', $legacyId);
            $context->noteImported('pages');
        }
    }

    private function languageCodeForId(int $langId): string
    {
        if (! Schema::hasTable('languages')) {
            return 'en';
        }

        $code = DB::table('languages')->where('id', $langId)->value('code');

        return is_string($code) && $code !== '' ? $code : 'en';
    }
}
