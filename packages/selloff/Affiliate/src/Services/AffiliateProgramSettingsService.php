<?php

namespace App\Modules\Selloff\Affiliate\Services;

use App\Services\Platform\PlatformSettingsService;
use App\Support\MediaUrl;

class AffiliateProgramSettingsService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function publicProgram(): array
    {
        $settings = $this->programSettings();
        $langId = (int) ($this->settings->all()['site_lang'] ?? 1);
        $localized = $this->localizedForLang($this->stored(), $langId);

        return [
            'enabled' => $settings['status'],
            'type' => $settings['type'],
            'commission_rate' => $settings['commission_rate'],
            'discount_rate' => $settings['discount_rate'],
            'image_url' => $settings['image_url'],
            'description' => $localized['description']['description'] ?: $settings['description'],
            'content' => $localized['content']['content'] ?: $settings['content'],
            'how_it_works' => array_values(array_filter(
                $localized['how_it_works'],
                fn (array $item) => ($item['title'] ?? '') !== '' || ($item['description'] ?? '') !== '',
            )),
            'faq' => array_values(array_filter(
                $localized['faq'],
                fn (array $item) => ($item['q'] ?? '') !== '' || ($item['a'] ?? '') !== '',
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function programSettings(): array
    {
        $stored = $this->stored();

        return [
            'status' => filter_var($stored['status'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'type' => ($stored['type'] ?? 'site_based') === 'seller_based' ? 'seller_based' : 'site_based',
            'commission_rate' => (float) ($stored['commission_rate'] ?? 0),
            'discount_rate' => (float) ($stored['discount_rate'] ?? 0),
            'image_url' => $this->imageUrl($stored),
            'description' => $this->legacyDescriptionFallback($stored),
            'content' => $this->legacyContentFallback($stored),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminProgram(int $langId): array
    {
        $stored = $this->stored();
        $localized = $this->localizedForLang($stored, $langId);

        return [
            'lang_id' => $langId,
            'status' => filter_var($stored['status'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'type' => ($stored['type'] ?? 'site_based') === 'seller_based' ? 'seller_based' : 'site_based',
            'commission_rate' => (float) ($stored['commission_rate'] ?? 0),
            'discount_rate' => (float) ($stored['discount_rate'] ?? 0),
            'image_path' => $this->imagePath($stored),
            'image_storage' => (string) ($stored['image_storage'] ?? ''),
            'image_url' => $this->imageUrl($stored),
            'description' => $localized['description'],
            'content' => $localized['content'],
            'how_it_works' => $localized['how_it_works'],
            'faq' => $localized['faq'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateAdminProgram(int $langId, string $section, array $payload): array
    {
        $stored = $this->stored();

        if ($section === 'settings') {
            foreach (['status', 'type', 'commission_rate', 'discount_rate', 'image_path', 'image_storage', 'image_url'] as $key) {
                if (array_key_exists($key, $payload)) {
                    $stored[$key] = $payload[$key];
                }
            }

            if (array_key_exists('status', $payload)) {
                $stored['status'] = filter_var($payload['status'], FILTER_VALIDATE_BOOLEAN);
            }

            if (array_key_exists('type', $payload)) {
                $stored['type'] = $payload['type'] === 'seller_based' ? 'seller_based' : 'site_based';
            }

            if (array_key_exists('commission_rate', $payload)) {
                $stored['commission_rate'] = (float) $payload['commission_rate'];
            }

            if (array_key_exists('discount_rate', $payload)) {
                $stored['discount_rate'] = (float) $payload['discount_rate'];
            }

            if (array_key_exists('image_path', $payload)) {
                $stored['image_path'] = $payload['image_path'] !== '' && $payload['image_path'] !== null
                    ? (string) $payload['image_path']
                    : null;
            }

            if (array_key_exists('image_storage', $payload)) {
                $stored['image_storage'] = $payload['image_storage'] !== null ? (string) $payload['image_storage'] : '';
            }
        } else {
            $localized = is_array($stored['localized'] ?? null) ? $stored['localized'] : [];
            $langKey = (string) $langId;
            $entry = is_array($localized[$langKey] ?? null) ? $localized[$langKey] : [];

            if ($section === 'description' && isset($payload['description']) && is_array($payload['description'])) {
                $entry['description'] = $this->normalizeDescription($payload['description']);
            } elseif ($section === 'content' && isset($payload['content']) && is_array($payload['content'])) {
                $entry['content'] = $this->normalizeContent($payload['content']);
            } elseif ($section === 'how_it_works' && isset($payload['how_it_works']) && is_array($payload['how_it_works'])) {
                $entry['how_it_works'] = $this->normalizeHowItWorks($payload['how_it_works']);
            } elseif ($section === 'faq' && isset($payload['faq']) && is_array($payload['faq'])) {
                $entry['faq'] = $this->normalizeFaq($payload['faq']);
            }

            $localized[$langKey] = $entry;
            $stored['localized'] = $localized;
        }

        $this->persist($stored);

        return $this->adminProgram($langId);
    }

    /**
     * @param  array<string, mixed>  $affiliateSettings
     * @param  array<int|string, array<string, mixed>>  $localizedRows
     */
    public function importFromLegacy(array $affiliateSettings, array $localizedRows): void
    {
        $stored = $this->stored();

        $stored['status'] = filter_var($affiliateSettings['status'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $stored['type'] = ($affiliateSettings['type'] ?? 'site_based') === 'seller_based' ? 'seller_based' : 'site_based';
        $stored['commission_rate'] = (float) ($affiliateSettings['commission_rate'] ?? 0);
        $stored['discount_rate'] = (float) ($affiliateSettings['discount_rate'] ?? 0);

        $image = $affiliateSettings['image'] ?? null;
        if (is_string($image) && $image !== '' && $image !== '0') {
            $stored['image_path'] = $image;
            $stored['image_storage'] = (string) ($affiliateSettings['storage'] ?? 'local');
        } else {
            $stored['image_path'] = null;
            $stored['image_storage'] = '';
        }

        $localized = [];
        foreach ($localizedRows as $langId => $row) {
            $langKey = (string) $langId;
            $localized[$langKey] = [
                'description' => $this->normalizeDescription($row['description'] ?? []),
                'content' => $this->normalizeContent($row['content'] ?? []),
                'how_it_works' => $this->normalizeHowItWorks($row['how_it_works'] ?? []),
                'faq' => $this->normalizeFaq($row['faq'] ?? []),
            ];
        }

        $stored['localized'] = $localized;
        $this->persist($stored);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $defaults = config('selloff.affiliate_program', []);
        $raw = $this->settings->all()['affiliate_program'] ?? null;

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return array_replace_recursive($defaults, $decoded);
            }
        }

        if (is_array($raw)) {
            return array_replace_recursive($defaults, $raw);
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function persist(array $settings): void
    {
        $this->settings->upsertMany(['affiliate_program' => json_encode($settings)], 'general');
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{description: array{title: string, description: string}, content: array{title: string, content: string}, how_it_works: list<array{title: string, description: string}>, faq: list<array{id: string, o: int, q: string, a: string}>}
     */
    private function localizedForLang(array $stored, int $langId): array
    {
        $localized = is_array($stored['localized'] ?? null) ? $stored['localized'] : [];
        $entry = is_array($localized[(string) $langId] ?? null) ? $localized[(string) $langId] : [];

        return [
            'description' => $this->normalizeDescription($entry['description'] ?? $this->legacyDescriptionBlock($stored)),
            'content' => $this->normalizeContent($entry['content'] ?? $this->legacyContentBlock($stored)),
            'how_it_works' => $this->normalizeHowItWorks($entry['how_it_works'] ?? []),
            'faq' => $this->normalizeFaq($entry['faq'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function legacyDescriptionFallback(array $stored): string
    {
        $block = $this->legacyDescriptionBlock($stored);

        return (string) ($block['description'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function legacyContentFallback(array $stored): string
    {
        $block = $this->legacyContentBlock($stored);

        return (string) ($block['content'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{title: string, description: string}
     */
    private function legacyDescriptionBlock(array $stored): array
    {
        if (is_string($stored['description'] ?? null) && $stored['description'] !== '') {
            return ['title' => '', 'description' => (string) $stored['description']];
        }

        return ['title' => '', 'description' => ''];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{title: string, content: string}
     */
    private function legacyContentBlock(array $stored): array
    {
        if (is_string($stored['content'] ?? null) && $stored['content'] !== '') {
            return ['title' => '', 'content' => (string) $stored['content']];
        }

        return ['title' => '', 'content' => ''];
    }

    /**
     * @param  mixed  $value
     * @return array{title: string, description: string}
     */
    private function normalizeDescription(mixed $value): array
    {
        if (! is_array($value)) {
            return ['title' => '', 'description' => ''];
        }

        return [
            'title' => (string) ($value['title'] ?? ''),
            'description' => (string) ($value['description'] ?? ''),
        ];
    }

    /**
     * @param  mixed  $value
     * @return array{title: string, content: string}
     */
    private function normalizeContent(mixed $value): array
    {
        if (! is_array($value)) {
            return ['title' => '', 'content' => ''];
        }

        return [
            'title' => (string) ($value['title'] ?? ''),
            'content' => (string) ($value['content'] ?? ''),
        ];
    }

    /**
     * @param  mixed  $value
     * @return list<array{title: string, description: string}>
     */
    private function normalizeHowItWorks(mixed $value): array
    {
        $items = [];
        if (is_array($value)) {
            foreach ($value as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $items[] = [
                    'title' => (string) ($row['title'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                ];
            }
        }

        while (count($items) < 3) {
            $items[] = ['title' => '', 'description' => ''];
        }

        return array_slice($items, 0, 3);
    }

    /**
     * @param  mixed  $value
     * @return list<array{id: string, o: int, q: string, a: string}>
     */
    private function normalizeFaq(mixed $value): array
    {
        $items = [];
        if (! is_array($value)) {
            return $items;
        }

        foreach ($value as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $order = (int) ($row['o'] ?? ($index + 1));
            $question = (string) ($row['q'] ?? '');
            $answer = (string) ($row['a'] ?? '');
            $id = (string) ($row['id'] ?? ('faq_'.$order.'_'.$index));

            $items[] = [
                'id' => $id,
                'o' => $order,
                'q' => $question,
                'a' => $answer,
            ];
        }

        usort($items, fn (array $a, array $b) => $a['o'] <=> $b['o']);

        return $items;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function imagePath(array $stored): ?string
    {
        $path = $stored['image_path'] ?? $stored['image'] ?? null;

        if (! is_string($path) || $path === '' || $path === '0') {
            return null;
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function imageUrl(array $stored): ?string
    {
        $path = $this->imagePath($stored);

        return $path ? MediaUrl::resolve($path) : null;
    }
}
