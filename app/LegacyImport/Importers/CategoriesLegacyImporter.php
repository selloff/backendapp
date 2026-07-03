<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyLanguageLocaleResolver;
use App\LegacyImport\Support\LegacyValueCoercer;
use App\Modules\Selloff\Catalog\Models\CategoryTranslation;
use Illuminate\Support\Facades\DB;

class CategoriesLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'categories';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('categories')) {
            return;
        }

        $rows = $reader->rows('categories');
        usort($rows, static function (array $a, array $b): int {
            $aParent = empty($a['parent_id']) ? 0 : 1;
            $bParent = empty($b['parent_id']) ? 0 : 1;

            return $aParent <=> $bParent;
        });

        $translations = $this->translationIndex($reader);

        foreach ($rows as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $parentLegacyId = isset($row['parent_id']) && $row['parent_id'] !== '' && (int) $row['parent_id'] > 0
                ? (int) $row['parent_id']
                : null;

            $payload = [
                'id' => $legacyId,
                'parent_id' => $parentLegacyId ? $context->resolveId('categories', $parentLegacyId) : null,
                'slug' => $row['slug'] ?? ('category-'.$legacyId),
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'category_order' => isset($row['category_order']) ? (int) $row['category_order'] : 0,
                'featured_order' => isset($row['featured_order']) ? (int) $row['featured_order'] : 0,
                'homepage_order' => isset($row['homepage_order']) ? (int) $row['homepage_order'] : 0,
                'is_featured' => LegacyValueCoercer::bool($row['is_featured'] ?? 0),
                'show_on_main_menu' => LegacyValueCoercer::bool($row['show_on_main_menu'] ?? 1),
                'show_products_on_index' => LegacyValueCoercer::bool($row['show_products_on_index'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()),
            ];

            if (! $context->dryRun) {
                DB::table('categories')->updateOrInsert(['id' => $legacyId], $payload);

                $categoryTranslations = $translations[$legacyId] ?? [];
                if ($categoryTranslations === []) {
                    $name = ucfirst(str_replace('-', ' ', (string) $payload['slug']));
                    CategoryTranslation::query()->updateOrCreate(
                        ['category_id' => $legacyId, 'locale' => 'en'],
                        [
                            'name' => $name,
                            'meta_title' => null,
                            'meta_description' => null,
                            'meta_keywords' => null,
                        ],
                    );
                } else {
                    foreach ($categoryTranslations as $locale => $translation) {
                        CategoryTranslation::query()->updateOrCreate(
                            ['category_id' => $legacyId, 'locale' => $locale],
                            [
                                'name' => $translation['name'],
                                'meta_title' => $translation['meta_title'] ?? null,
                                'meta_description' => $translation['meta_description'] ?? null,
                                'meta_keywords' => $translation['meta_keywords'] ?? null,
                            ],
                        );
                    }
                }
            }

            $this->maps->remember($context, 'categories', $legacyId, 'categories', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }

    /**
     * @return array<int, array<string, array{name: string, meta_title: ?string, meta_description: ?string, meta_keywords: ?string}>>
     */
    private function translationIndex(MySqlDumpReader $reader): array
    {
        if (! $reader->hasTable('category_lang')) {
            return [];
        }

        $locales = LegacyLanguageLocaleResolver::index($reader);
        $index = [];

        foreach ($reader->rows('category_lang') as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            $langId = (int) ($row['lang_id'] ?? 1);
            if ($categoryId <= 0 || empty($row['name'])) {
                continue;
            }

            $locale = $locales[$langId] ?? ($langId === 1 ? 'en' : 'lang-'.$langId);

            $index[$categoryId][$locale] = [
                'name' => (string) $row['name'],
                'meta_title' => ! empty($row['meta_title']) ? (string) $row['meta_title'] : null,
                'meta_description' => ! empty($row['meta_description']) ? (string) $row['meta_description'] : null,
                'meta_keywords' => ! empty($row['meta_keywords']) ? (string) $row['meta_keywords'] : null,
            ];
        }

        return $index;
    }
}
