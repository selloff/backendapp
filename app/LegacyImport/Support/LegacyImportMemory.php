<?php

namespace App\LegacyImport\Support;

class LegacyImportMemory
{
    public static function applyConfiguredLimit(): void
    {
        $memoryLimit = config('selloff.legacy_import.memory_limit', '1G');
        if ($memoryLimit !== null && $memoryLimit !== '') {
            ini_set('memory_limit', (string) $memoryLimit);
        }
    }

    public static function raiseForLargeDump(string $dumpPath): ?string
    {
        if (! is_readable($dumpPath)) {
            return null;
        }

        $size = filesize($dumpPath);
        if ($size === false || $size < 50 * 1024 * 1024) {
            return null;
        }

        $minimumBytes = 1024 * 1024 * 1024;
        $currentBytes = self::bytesFromIniLimit((string) ini_get('memory_limit'));

        if ($currentBytes >= $minimumBytes) {
            return null;
        }

        ini_set('memory_limit', '2G');

        return '2G';
    }

    public static function bytesFromIniLimit(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (float) $limit;

        return match ($unit) {
            'g' => (int) ($value * 1024 * 1024 * 1024),
            'm' => (int) ($value * 1024 * 1024),
            'k' => (int) ($value * 1024),
            default => (int) $value,
        };
    }
}
