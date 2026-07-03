<?php

namespace App\LegacyImport\Support;

use App\LegacyImport\MySqlDumpReader;

final class LegacyLanguageLocaleResolver
{
    /**
     * @return array<int, string> legacy lang id => locale code
     */
    public static function index(MySqlDumpReader $reader): array
    {
        if (! $reader->hasTable('languages')) {
            return [1 => 'en'];
        }

        $index = [];

        foreach ($reader->rows('languages') as $row) {
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }

            $index[$legacyId] = self::localeFromLanguageRow($row, $legacyId);
        }

        return $index === [] ? [1 => 'en'] : $index;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function localeFromLanguageRow(array $row, int $legacyId): string
    {
        $shortForm = trim((string) ($row['short_form'] ?? $row['code'] ?? ''));
        if ($shortForm !== '') {
            return strtolower(substr($shortForm, 0, 5));
        }

        $languageCode = trim((string) ($row['language_code'] ?? ''));
        if ($languageCode !== '' && str_contains($languageCode, '-')) {
            return strtolower(explode('-', $languageCode, 2)[0]);
        }

        if ($languageCode !== '') {
            return strtolower(substr($languageCode, 0, 5));
        }

        return $legacyId === 1 ? 'en' : 'lang-'.$legacyId;
    }
}
