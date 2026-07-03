<?php

namespace App\LegacyImport\Support;

use Carbon\Carbon;

class LegacyValueCoercer
{
    public static function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function decimal(mixed $value, int $scale = 2): string
    {
        if ($value === null || $value === '') {
            return number_format(0, $scale, '.', '');
        }

        return number_format((float) $value, $scale, '.', '');
    }

    public static function stringMax(mixed $value, int $maxLength = 255, ?string $fallback = null): ?string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (is_array($value)) {
            $value = self::firstScalarString($value);
            if ($value === null || $value === '') {
                return $fallback;
            }
        }

        if (! is_scalar($value)) {
            return $fallback;
        }

        $string = (string) $value;

        if (mb_strlen($string) <= $maxLength) {
            return $string;
        }

        return mb_substr($string, 0, $maxLength);
    }

    public static function visibility(mixed $value): string
    {
        if (is_numeric($value)) {
            return (int) $value === 1 ? 'visible' : 'hidden';
        }

        return match (strtolower((string) $value)) {
            'visible', 'hidden' => strtolower((string) $value),
            '1', 'true', 'yes', 'on' => 'visible',
            '0', 'false', 'no', 'off' => 'hidden',
            default => 'visible',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function json(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string|int, mixed>|null
     */
    public static function phpSerializedArray(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = @unserialize((string) $value, ['allowed_classes' => false]);

        return is_array($decoded) ? $decoded : null;
    }

    public static function localizedLabel(mixed $serializedOrJson, string $fallback = 'Item'): string
    {
        if (is_array($serializedOrJson)) {
            $label = self::firstScalarString($serializedOrJson);

            return self::stringMax($label, 255, $fallback) ?? self::stringMax($fallback, 255, 'Item');
        }

        if (is_string($serializedOrJson) && $serializedOrJson !== '') {
            $label = self::firstScalarString(self::json($serializedOrJson))
                ?? self::firstScalarString(self::phpSerializedArray($serializedOrJson));

            if ($label !== null) {
                return self::stringMax($label, 255, $fallback) ?? self::stringMax($fallback, 255, 'Item');
            }

            return self::stringMax($serializedOrJson, 255, $fallback) ?? self::stringMax($fallback, 255, 'Item');
        }

        if (is_scalar($serializedOrJson) && (string) $serializedOrJson !== '') {
            return self::stringMax($serializedOrJson, 255, $fallback) ?? self::stringMax($fallback, 255, 'Item');
        }

        return self::stringMax($fallback, 255, 'Item');
    }

    public static function firstScalarString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach (['title', 'name', 'label', 'value', 'text'] as $key) {
            if (isset($value[$key]) && is_scalar($value[$key]) && (string) $value[$key] !== '') {
                return (string) $value[$key];
            }
        }

        foreach (['en', '1', 'en-US'] as $key) {
            if (isset($value[$key])) {
                $nested = self::firstScalarString($value[$key]);
                if ($nested !== null && $nested !== '') {
                    return $nested;
                }
            }
        }

        foreach ($value as $item) {
            $nested = self::firstScalarString($item);
            if ($nested !== null && $nested !== '') {
                return $nested;
            }
        }

        return null;
    }

    public static function jsonb(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && json_decode($trimmed) !== null) {
                return $trimmed;
            }
        }

        $array = is_array($value) ? $value : self::phpSerializedArray($value);

        return $array !== null ? json_encode($array) : json_encode($value);
    }

    public static function servicePaymentStatus(mixed $value): string
    {
        $normalized = strtolower(trim((string) ($value ?? 'pending')));

        return match ($normalized) {
            '', 'pending', 'pending_payment' => 'pending',
            'success', 'paid', 'payment_received', 'completed' => 'completed',
            default => $normalized,
        };
    }
}
