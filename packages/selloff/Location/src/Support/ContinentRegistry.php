<?php

namespace App\Modules\Selloff\Location\Support;

class ContinentRegistry
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'EU' => 'Europe',
            'AS' => 'Asia',
            'AF' => 'Africa',
            'NA' => 'North America',
            'SA' => 'South America',
            'OC' => 'Oceania',
            'AN' => 'Antarctica',
        ];
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }
}
