<?php

namespace App\Modules\Selloff\User\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Catalog\Services\BrandSettingsService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PreferencesController extends Controller
{
    public function marketplace(PlatformSettingsService $settings, BrandSettingsService $brandSettings): JsonResponse
    {
        $allSettings = $settings->all();
        $defaultCode = trim((string) ($allSettings['default_currency'] ?? ''));
        $defaultCurrency = $defaultCode !== ''
            ? Currency::query()->where('code', $defaultCode)->first()
            : null;
        $defaultCurrency ??= Currency::query()->where('code', 'NGN')->first()
            ?? Currency::query()->where('status', true)->orderBy('code')->first();

        $converterEnabled = $this->platformBool($allSettings, 'currency_converter', false);

        return ApiResponse::success([
            'default_currency' => $defaultCurrency?->code ?? 'NGN',
            'currency_converter_enabled' => $converterEnabled,
            'brand_settings' => $brandSettings->all(),
            'location_search_header' => $this->platformBool($allSettings, 'location_search_header', true),
            'single_country_mode' => $this->platformBool($allSettings, 'single_country_mode', false),
            'single_country_id' => isset($allSettings['single_country_id']) && (int) $allSettings['single_country_id'] > 0
                ? (int) $allSettings['single_country_id']
                : null,
            'storage_keys' => [
                'selected_currency' => 'mds_selected_currency',
                'guest_wishlist' => 'mds_guest_wishlist',
                'guest_cart_token' => 'selloff_guest_cart_token',
                'auth_token' => 'selloff_token',
                'estimated_delivery_location' => 'mds_estimated_delivery_location',
                'cart_has_changed' => 'mds_cart_has_changed',
                'control_panel_lang' => 'mds_control_panel_lang',
            ],
            'session_note' => 'Legacy mds_session PHP cookies are replaced by Sanctum bearer tokens in the SPA.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function platformBool(array $settings, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
