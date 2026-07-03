<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Services\AdminSitemapService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSeoController extends Controller
{
    /** @var list<string> */
    private const SEO_KEYS = [
        'homepage_title',
        'keywords',
        'site_description',
        'google_analytics',
        'facebook_pixel',
    ];

    /** @var list<string> */
    private const SITEMAP_KEYS = [
        'sitemap_frequency',
        'sitemap_last_modification',
        'sitemap_priority',
    ];

    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly AdminSitemapService $sitemaps,
    ) {}

    public function show(): JsonResponse
    {
        $all = $this->settings->all();
        $seo = [];
        foreach ([...self::SEO_KEYS, ...self::SITEMAP_KEYS] as $key) {
            $value = $all[$key] ?? '';
            if (in_array($key, self::SITEMAP_KEYS, true)) {
                $value = $this->normalizeSitemapPref($value);
            }
            $seo[$key] = $value;
        }

        return ApiResponse::success($seo);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'homepage_title' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'site_description' => ['nullable', 'string', 'max:2000'],
            'google_analytics' => ['nullable', 'string', 'max:10000'],
            'facebook_pixel' => ['nullable', 'string', 'max:100'],
            'sitemap_frequency' => ['nullable', 'string', 'in:auto,none'],
            'sitemap_last_modification' => ['nullable', 'string', 'in:auto,none'],
            'sitemap_priority' => ['nullable', 'string', 'in:auto,none'],
        ]);

        $seoKeys = array_intersect_key($data, array_flip(self::SEO_KEYS));
        $sitemapKeys = array_intersect_key($data, array_flip(self::SITEMAP_KEYS));

        if ($seoKeys !== []) {
            $this->settings->upsertMany($seoKeys, 'seo');
        }

        if ($sitemapKeys !== []) {
            $this->settings->upsertMany($sitemapKeys, 'product');
        }

        return $this->show();
    }

    public function sitemaps(): JsonResponse
    {
        return ApiResponse::success($this->sitemaps->listFiles());
    }

    public function generateSitemap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'frequency' => ['nullable', 'string', 'in:auto,none'],
            'last_modification' => ['nullable', 'string', 'in:auto,none'],
            'priority' => ['nullable', 'string', 'in:auto,none'],
        ]);

        $result = $this->sitemaps->generate(
            $data['frequency'] ?? null,
            $data['last_modification'] ?? null,
            $data['priority'] ?? null,
        );

        return ApiResponse::success($result, message: 'Sitemap generated.');
    }

    public function destroySitemap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
        ]);

        $this->sitemaps->deleteFile($data['filename']);

        return ApiResponse::success(['deleted' => true]);
    }

    private function normalizeSitemapPref(mixed $value): string
    {
        return $value === 'auto' ? 'auto' : 'none';
    }
}
