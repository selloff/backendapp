<?php

namespace App\Support;

class LegacyTextNormalizer
{
    /**
     * Restore line breaks corrupted when MySQL dump escape sequences (\r\n) were imported as "rn".
     */
    public static function restoreLineBreaks(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $text = str_replace(['\\r\\n', '\\r', '\\n'], ["\n", "\n", "\n"], $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (! str_contains($text, 'rn')) {
            return $text;
        }

        $text = preg_replace('/(?<=\n)\s*rn\s*(?=\n)/', '', $text) ?? $text;
        $text = preg_replace('/^rn\s*\n/', '', $text) ?? $text;
        $text = preg_replace('/\n\s*rn$/', '', $text) ?? $text;
        $text = preg_replace('/^rn$/m', '', $text) ?? $text;
        $text = preg_replace('/(?<=[.!?:;)\]])\s*rn\s*(?=[A-Z0-9\-#])/', "\n", $text) ?? $text;
        $text = preg_replace('/(?<=[a-z0-9\)])\s*rn\s*(?=-\s)/', "\n", $text) ?? $text;
        $text = preg_replace('/\s*rn\s*(?=-\s)/', "\n", $text) ?? $text;

        return $text;
    }

    public static function needsLineBreakRepair(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        $restored = self::restoreLineBreaks($text);

        return $restored !== $text;
    }

    /**
     * Convert legacy HTML descriptions to plain text, using line breaks for block elements.
     */
    public static function htmlToPlainText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        if (! str_contains($text, '<') && ! str_contains($text, '&')) {
            return $text;
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/(p|div|li|tr|td|th|h[1-6]|blockquote|pre)>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = array_map(static fn (string $line): string => trim($line), explode("\n", $text));
        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Normalize text imported from legacy MySQL: strip HTML and repair corrupted line breaks.
     */
    public static function normalizeImportedText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        return self::restoreLineBreaks(self::htmlToPlainText($text));
    }
}
