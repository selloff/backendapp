<?php

namespace App\Modules\Selloff\Content\Support;

use App\Modules\Selloff\Content\Models\AdSpace;

class AdSpacePresenter
{
    public static function forBuyer(AdSpace $adSpace): array
    {
        $desktop = trim((string) ($adSpace->ad_code_desktop ?? $adSpace->ad_code ?? ''));
        $mobile = trim((string) ($adSpace->ad_code_mobile ?? ''));

        return [
            'id' => $adSpace->id,
            'ad_space_key' => $adSpace->ad_space_key,
            'title' => $adSpace->title,
            'ad_code' => $desktop !== '' ? $desktop : null,
            'ad_code_desktop' => $desktop !== '' ? $desktop : null,
            'ad_code_mobile' => $mobile !== '' ? $mobile : null,
            'desktop_width' => (int) ($adSpace->desktop_width ?? 728),
            'desktop_height' => (int) ($adSpace->desktop_height ?? 90),
            'mobile_width' => (int) ($adSpace->mobile_width ?? 300),
            'mobile_height' => (int) ($adSpace->mobile_height ?? 250),
        ];
    }
}
