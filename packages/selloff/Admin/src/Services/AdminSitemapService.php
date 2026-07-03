<?php

namespace App\Modules\Selloff\Admin\Services;

use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Content\Models\BlogPost;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\File;

class AdminSitemapService
{
    private const MAX_URLS_PER_FILE = 49999;

    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    /**
     * @return list<array{filename: string, url: string, updated_at: string}>
     */
    public function listFiles(): array
    {
        return collect(File::glob(public_path('*.xml')))
            ->filter(fn (string $path): bool => str_contains(strtolower(basename($path)), 'sitemap'))
            ->map(fn (string $path) => [
                'filename' => basename($path),
                'url' => url(basename($path)),
                'updated_at' => date('c', filemtime($path)),
            ])
            ->sortByDesc('updated_at')
            ->values()
            ->all();
    }

    /**
     * @return array{filename: string, url: string}
     */
    public function generate(?string $frequency = null, ?string $lastModification = null, ?string $priority = null): array
    {
        if ($frequency !== null || $lastModification !== null || $priority !== null) {
            $this->settings->upsertMany(array_filter([
                'sitemap_frequency' => $frequency,
                'sitemap_last_modification' => $lastModification,
                'sitemap_priority' => $priority,
            ]), 'product');
        }

        $this->deleteOldSitemaps();

        $prefs = $this->sitemapPreferences();
        $urls = $this->collectUrls();
        $filename = 'sitemap.xml';
        File::put(public_path($filename), $this->buildSitemapXml($urls, $prefs));

        return [
            'filename' => $filename,
            'url' => url($filename),
        ];
    }

    public function deleteFile(string $filename): void
    {
        abort_unless(str_contains(strtolower($filename), 'sitemap') && str_ends_with($filename, '.xml'), 422, 'Invalid sitemap file.');

        $path = public_path($filename);
        abort_unless(File::exists($path), 404, 'Sitemap file not found.');

        File::delete($path);
    }

    public function downloadPath(string $filename): string
    {
        abort_unless(str_contains(strtolower($filename), 'sitemap') && str_ends_with($filename, '.xml'), 422, 'Invalid sitemap file.');

        $path = public_path($filename);
        abort_unless(File::exists($path), 404, 'Sitemap file not found.');

        return $path;
    }

    private function deleteOldSitemaps(): void
    {
        foreach (File::glob(public_path('*.xml')) as $path) {
            if (str_contains(strtolower(basename($path)), 'sitemap')) {
                File::delete($path);
            }
        }
    }

    /**
     * @return array{frequency: bool, last_modification: bool, priority: bool}
     */
    private function sitemapPreferences(): array
    {
        $all = $this->settings->all();

        return [
            'frequency' => ($all['sitemap_frequency'] ?? 'auto') === 'auto',
            'last_modification' => ($all['sitemap_last_modification'] ?? 'auto') === 'auto',
            'priority' => ($all['sitemap_priority'] ?? 'auto') === 'auto',
        ];
    }

    /**
     * @return list<array{loc: string, lastmod?: string|null, changefreq?: string|null, priority?: string|null}>
     */
    private function collectUrls(): array
    {
        $urls = [
            [
                'loc' => url('/'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
        ];

        Product::query()
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->whereNotNull('slug')
            ->orderByDesc('id')
            ->limit(self::MAX_URLS_PER_FILE)
            ->get(['slug', 'updated_at'])
            ->each(function (Product $product) use (&$urls): void {
                $urls[] = [
                    'loc' => url('/products/'.$product->slug),
                    'lastmod' => $product->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

        Category::query()
            ->where('status', true)
            ->whereNotNull('slug')
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['slug', 'updated_at'])
            ->each(function (Category $category) use (&$urls): void {
                $urls[] = [
                    'loc' => url('/categories/'.$category->slug),
                    'lastmod' => $category->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ];
            });

        BlogPost::query()
            ->where('is_published', true)
            ->whereNotNull('slug')
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['slug', 'updated_at'])
            ->each(function (BlogPost $post) use (&$urls): void {
                $urls[] = [
                    'loc' => url('/blog/'.$post->slug),
                    'lastmod' => $post->updated_at?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ];
            });

        return $urls;
    }

    /**
     * @param  list<array{loc: string, lastmod?: string|null, changefreq?: string|null, priority?: string|null}>  $urls
     * @param  array{frequency: bool, last_modification: bool, priority: bool}  $prefs
     */
    private function buildSitemapXml(array $urls, array $prefs): string
    {
        $entries = '';
        foreach ($urls as $url) {
            $entries .= '<url>';
            $entries .= '<loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';
            if ($prefs['last_modification'] && ! empty($url['lastmod'])) {
                $entries .= '<lastmod>'.htmlspecialchars($url['lastmod'], ENT_XML1).'</lastmod>';
            }
            if ($prefs['frequency'] && ! empty($url['changefreq'])) {
                $entries .= '<changefreq>'.htmlspecialchars($url['changefreq'], ENT_XML1).'</changefreq>';
            }
            if ($prefs['priority'] && ! empty($url['priority'])) {
                $entries .= '<priority>'.htmlspecialchars($url['priority'], ENT_XML1).'</priority>';
            }
            $entries .= '</url>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$entries
            .'</urlset>';
    }
}
