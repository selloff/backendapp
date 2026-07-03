<?php

namespace App\Support;

final class Decimal
{
    public static function multiply(string|float|int $left, string|float|int $right, int $scale = 2): string
    {
        if (function_exists('bcmul')) {
            return bcmul((string) $left, (string) $right, $scale);
        }

        return number_format((float) $left * (float) $right, $scale, '.', '');
    }
}
