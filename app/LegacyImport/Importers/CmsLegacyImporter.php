<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use App\Modules\Selloff\Content\Support\AdSpaceSlotRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CmsLegacyImporter extends MultiTableLegacyImporter
{
    private const MOBILE_BANNER_LEGACY_ID_OFFSET = 1_000_000;

    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return ['slider', 'homepage_banners', 'ad_spaces', 'mobile_banner_ads'];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importSliders($context, $reader);
        $this->importHomepageBanners($context, $reader);
        $this->importAdSpaces($context, $reader);
        $this->importMobileBannerAds($context, $reader);
    }

    private function importSliders(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('slider') || ! $reader->hasTable('slider')) {
            return;
        }

        foreach ($reader->rows('slider') as $row) {
            $context->notePlanned('slider');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('slider');

                continue;
            }

            $now = now();
            $payload = [
                'id' => $legacyId,
                'title' => LegacyValueCoercer::stringMax($row['title'] ?? null, 255),
                'description' => LegacyValueCoercer::stringMax($row['description'] ?? null, 1000),
                'image_path' => LegacyValueCoercer::stringMax($row['image'] ?? null, 500),
                'image_mobile_path' => LegacyValueCoercer::stringMax($row['image_mobile'] ?? null, 500),
                'link' => LegacyValueCoercer::stringMax($row['link'] ?? null, 500),
                'sort_order' => (int) ($row['item_order'] ?? 0),
                'is_active' => true,
                'legacy_id' => $legacyId,
                'button_text' => LegacyValueCoercer::stringMax($row['button_text'] ?? null, 255),
                'text_color' => LegacyValueCoercer::stringMax($row['text_color'] ?? '#ffffff', 30, '#ffffff'),
                'button_color' => LegacyValueCoercer::stringMax($row['button_color'] ?? '#222222', 30, '#222222'),
                'button_text_color' => LegacyValueCoercer::stringMax($row['button_text_color'] ?? '#ffffff', 30, '#ffffff'),
                'animation_title' => LegacyValueCoercer::stringMax($row['animation_title'] ?? 'none', 50, 'none'),
                'animation_description' => LegacyValueCoercer::stringMax($row['animation_description'] ?? 'none', 50, 'none'),
                'animation_button' => LegacyValueCoercer::stringMax($row['animation_button'] ?? 'none', 50, 'none'),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('sliders', 'lang_id')) {
                $payload['lang_id'] = (int) ($row['lang_id'] ?? 1);
            }

            if (! $context->dryRun) {
                DB::table('sliders')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'slider', $legacyId, 'sliders', $legacyId);
            $context->noteImported('slider');
        }
    }

    private function importHomepageBanners(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('homepage_banners') || ! $reader->hasTable('homepage_banners')) {
            return;
        }

        foreach ($reader->rows('homepage_banners') as $row) {
            $context->notePlanned('homepage_banners');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('homepage_banners');

                continue;
            }

            $now = now();
            $payload = [
                'id' => $legacyId,
                'title' => null,
                'image_path' => LegacyValueCoercer::stringMax($row['banner_image_path'] ?? null, 500),
                'link' => LegacyValueCoercer::stringMax($row['banner_url'] ?? null, 500),
                'banner_location' => LegacyValueCoercer::stringMax($row['banner_location'] ?? 'featured_products', 64, 'featured_products'),
                'banner_width' => min(100, max(1, (int) round((float) ($row['banner_width'] ?? 50)))),
                'sort_order' => (int) ($row['banner_order'] ?? 1),
                'is_active' => true,
                'legacy_id' => $legacyId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (! $context->dryRun) {
                DB::table('homepage_banners')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'homepage_banners', $legacyId, 'homepage_banners', $legacyId);
            $context->noteImported('homepage_banners');
        }
    }

    private function importAdSpaces(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('ad_spaces') || ! $reader->hasTable('ad_spaces')) {
            return;
        }

        foreach ($reader->rows('ad_spaces') as $row) {
            $context->notePlanned('ad_spaces');

            $legacyId = (int) ($row['id'] ?? 0);
            $adSpaceKey = $this->resolveAdSpaceKey($row['ad_space'] ?? null, $legacyId);
            if ($legacyId <= 0 || $adSpaceKey === '') {
                $context->noteSkipped('ad_spaces');

                continue;
            }

            $now = now();
            $payload = [
                'id' => $legacyId,
                'ad_space_key' => $adSpaceKey,
                'title' => AdSpaceSlotRegistry::LEGACY_SLOTS[$adSpaceKey] ?? $adSpaceKey,
                'ad_code' => LegacyValueCoercer::stringMax($row['ad_code_desktop'] ?? null, 65000),
                'ad_code_desktop' => LegacyValueCoercer::stringMax($row['ad_code_desktop'] ?? null, 65000),
                'ad_code_mobile' => LegacyValueCoercer::stringMax($row['ad_code_mobile'] ?? null, 65000),
                'url' => null,
                'desktop_width' => (int) ($row['desktop_width'] ?? 728),
                'desktop_height' => (int) ($row['desktop_height'] ?? 90),
                'mobile_width' => (int) ($row['mobile_width'] ?? 300),
                'mobile_height' => (int) ($row['mobile_height'] ?? 250),
                'is_active' => true,
                'legacy_id' => $legacyId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (! $context->dryRun) {
                DB::table('ad_spaces')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'ad_spaces', $legacyId, 'ad_spaces', $legacyId);
            $context->noteImported('ad_spaces');
        }
    }

    private function importMobileBannerAds(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('mobile_banner_ads') || ! $reader->hasTable('mobile_banner_ads')) {
            return;
        }

        foreach ($reader->rows('mobile_banner_ads') as $row) {
            $context->notePlanned('mobile_banner_ads');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('mobile_banner_ads');

                continue;
            }

            $placements = $this->mobileBannerPlacements($row);
            if ($placements === []) {
                $placements = ['mobile_other'];
            }

            $firstTargetId = null;

            foreach ($placements as $index => $placement) {
                $targetId = self::MOBILE_BANNER_LEGACY_ID_OFFSET + ($legacyId * 10) + $index;
                $firstTargetId ??= $targetId;
                $now = now();

                $payload = [
                    'id' => $targetId,
                    'title' => 'Mobile banner '.$legacyId,
                    'image_path' => LegacyValueCoercer::stringMax($row['image'] ?? null, 500),
                    'link' => LegacyValueCoercer::stringMax($row['url'] ?? null, 500),
                    'banner_location' => $placement,
                    'banner_width' => 100,
                    'sort_order' => $legacyId,
                    'is_active' => LegacyValueCoercer::bool($row['status'] ?? 1),
                    'legacy_id' => $legacyId,
                    'created_at' => LegacyValueCoercer::date($row['created_at'] ?? $now) ?? $now,
                    'updated_at' => $now,
                ];

                if (! $context->dryRun) {
                    DB::table('homepage_banners')->updateOrInsert(['id' => $targetId], $payload);
                }
            }

            if ($firstTargetId !== null) {
                $this->maps->remember(
                    $context,
                    'mobile_banner_ads',
                    $legacyId,
                    'homepage_banners',
                    $firstTargetId,
                );
            }

            $context->noteImported('mobile_banner_ads');
        }
    }

    private function resolveAdSpaceKey(mixed $adSpace, int $legacyId): string
    {
        $key = trim((string) $adSpace);
        if ($key === '') {
            return 'legacy_ad_space_'.$legacyId;
        }

        $label = LegacyValueCoercer::localizedLabel($adSpace, '');
        if ($label !== '' && $label !== 'Item') {
            $key = $label;
        }

        $key = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($key)) ?? '';
        $key = trim($key, '_');

        return $key !== '' ? $key : 'legacy_ad_space_'.$legacyId;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function mobileBannerPlacements(array $row): array
    {
        $placements = [];

        if (LegacyValueCoercer::bool($row['show_in_home'] ?? 0)) {
            $placements[] = 'mobile_home';
        }

        if (LegacyValueCoercer::bool($row['show_in_categories'] ?? 0)) {
            $placements[] = 'mobile_categories';
        }

        if (LegacyValueCoercer::bool($row['show_in_other'] ?? 0)) {
            $placements[] = 'mobile_other';
        }

        return $placements;
    }
}
