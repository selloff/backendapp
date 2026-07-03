<?php

namespace App\Modules\Selloff\Payment\Gateways;

use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PaystackGateway
{
    public function __construct(
        private readonly PaymentGatewaySettingsService $gatewaySettings,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function enabledConfig(): ?array
    {
        foreach ($this->gatewaySettings->all()['legacy_gateways'] as $gateway) {
            if (($gateway['name_key'] ?? $gateway['nameKey'] ?? null) !== 'paystack') {
                continue;
            }

            if (! filter_var($gateway['status'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return null;
            }

            $secretKey = trim((string) ($gateway['secret_key'] ?? ''));
            $publicKey = trim((string) ($gateway['public_key'] ?? ''));

            if ($secretKey === '' || $publicKey === '') {
                return null;
            }

            return [
                'public_key' => $publicKey,
                'secret_key' => $secretKey,
            ];
        }

        return null;
    }

    public function isEnabled(): bool
    {
        return $this->enabledConfig() !== null;
    }

    /**
     * @return object{amount: int, currency: string, reference: string}
     */
    public function verify(string $reference): object
    {
        $config = $this->enabledConfig();
        if ($config === null) {
            throw ValidationException::withMessages([
                'payment_method' => ['Paystack is not configured.'],
            ]);
        }

        $response = Http::withToken($config['secret_key'])
            ->acceptJson()
            ->get('https://api.paystack.co/transaction/verify/'.rawurlencode($reference));

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Unable to verify Paystack payment.'],
            ]);
        }

        $payload = $response->object();
        if (! ($payload->status ?? false) || ($payload->data->status ?? '') !== 'success') {
            throw ValidationException::withMessages([
                'payment_reference' => ['Paystack payment was not successful.'],
            ]);
        }

        return $payload->data;
    }
}
