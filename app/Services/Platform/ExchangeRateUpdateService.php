<?php

namespace App\Services\Platform;

use App\Modules\Selloff\Admin\Models\Currency;
use Illuminate\Support\Facades\Http;

class ExchangeRateUpdateService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    /**
     * @return array{updated: int, skipped: bool, message: string}
     */
    public function update(?string $base = null, ?string $service = null, ?string $apiKey = null): array
    {
        $stored = $this->settings->all();

        if (! $this->boolSetting($stored, 'currency_converter')) {
            return ['updated' => 0, 'skipped' => true, 'message' => 'Currency converter is disabled.'];
        }

        $base = $base ?: (string) ($stored['default_currency'] ?? 'NGN');
        $service = $service ?: (string) ($stored['currency_converter_api'] ?? 'fixer');
        $apiKey = $apiKey ?: (string) ($stored['currency_converter_api_key'] ?? '');

        if ($apiKey === '') {
            return ['updated' => 0, 'skipped' => true, 'message' => 'Currency converter API key is not configured.'];
        }

        $rates = match ($service) {
            'currencyapi' => $this->fetchCurrencyApiRates($base, $apiKey),
            'openexchangerates' => $this->fetchOpenExchangeRates($base, $apiKey),
            default => $this->fetchFixerRates($base, $apiKey),
        };

        if ($rates === []) {
            return ['updated' => 0, 'skipped' => true, 'message' => 'No exchange rates returned from provider.'];
        }

        $updated = 0;
        foreach ($rates as $row) {
            $code = (string) ($row['currency'] ?? '');
            $rate = (float) ($row['rate'] ?? 0);
            if ($code === '' || $rate <= 0) {
                continue;
            }

            $count = Currency::query()->where('code', $code)->update(['exchange_rate' => $rate]);
            $updated += $count;
        }

        return [
            'updated' => $updated,
            'skipped' => false,
            'message' => "Updated {$updated} currency exchange rates.",
        ];
    }

    public function shouldAutoUpdate(): bool
    {
        $stored = $this->settings->all();

        return $this->boolSetting($stored, 'currency_converter')
            && $this->boolSetting($stored, 'auto_update_exchange_rates');
    }

    /**
     * @return list<array{currency: string, rate: float}>
     */
    private function fetchFixerRates(string $base, string $apiKey): array
    {
        $response = Http::timeout(20)->get('https://data.fixer.io/api/latest', [
            'access_key' => $apiKey,
        ]);

        return $this->normalizeRates($response->json(), $base);
    }

    /**
     * @return list<array{currency: string, rate: float}>
     */
    private function fetchCurrencyApiRates(string $base, string $apiKey): array
    {
        $response = Http::timeout(20)->get('https://currencyapi.net/api/v1/rates', [
            'key' => $apiKey,
            'base' => 'USD',
        ]);

        return $this->normalizeRates($response->json(), $base);
    }

    /**
     * @return list<array{currency: string, rate: float}>
     */
    private function fetchOpenExchangeRates(string $base, string $apiKey): array
    {
        $response = Http::timeout(20)->get('https://openexchangerates.org/api/latest.json', [
            'app_id' => $apiKey,
            'base' => 'USD',
        ]);

        return $this->normalizeRates($response->json(), $base);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return list<array{currency: string, rate: float}>
     */
    private function normalizeRates(?array $payload, string $base): array
    {
        $rates = data_get($payload, 'rates');
        if (! is_array($rates) || ! isset($rates[$base])) {
            return [];
        }

        $baseRate = (float) $rates[$base];
        if ($baseRate <= 0) {
            return [];
        }

        $normalized = [];
        foreach ($rates as $code => $value) {
            $rate = (float) $value / $baseRate;
            if ($rate <= 0) {
                $rate = 1;
            }

            $normalized[] = [
                'currency' => (string) $code,
                'rate' => round($rate, 8),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function boolSetting(array $stored, string $key): bool
    {
        if (! array_key_exists($key, $stored)) {
            return false;
        }

        $value = $stored[$key];

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
