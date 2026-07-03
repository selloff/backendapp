<?php

namespace App\Support;

final class LegacyEditorHtml
{
    /** @var list<string> */
    private const RICH_TEXT_SETTING_KEYS = [
        'about_footer',
    ];

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function normalizeSettings(array $settings): array
    {
        foreach (self::RICH_TEXT_SETTING_KEYS as $key) {
            if (! array_key_exists($key, $settings) || ! is_string($settings[$key])) {
                continue;
            }

            $settings[$key] = self::clean($settings[$key]);
        }

        return $settings;
    }

    public static function clean(string $html): string
    {
        $cleaned = trim($html);
        if ($cleaned === '') {
            return '';
        }

        $cleaned = (string) preg_replace('/<wt-ignore\b[^>]*>(.*?)<\/wt-ignore>/is', '$1', $cleaned);
        $cleaned = (string) preg_replace('/<\/?wt-ignore\b[^>]*>/i', '', $cleaned);
        $cleaned = (string) preg_replace('/\sdata-(?:wt-guid|pm-slice|private)="[^"]*"/i', '', $cleaned);
        $cleaned = (string) preg_replace('/\sdata-(?:wt-guid|pm-slice|private)=\'[^\']*\'/i', '', $cleaned);
        $cleaned = (string) preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $cleaned);
        $cleaned = (string) preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $cleaned);

        return trim($cleaned);
    }
}
