<?php

namespace App\Modules\Selloff\Payment\Services;

class PaymentMethodRegistry
{
    public function __construct(
        private readonly PaymentGatewaySettingsService $gatewaySettings,
    ) {}

    /**
     * @return list<array{key: string, label: string, enabled: bool, public_key?: string, instructions?: string, logos?: list<string>}>
     */
    public function available(): array
    {
        $settings = $this->gatewaySettings->all();
        $methods = [];

        if ($settings['wallet_enabled']) {
            $methods[] = [
                'key' => 'wallet_balance',
                'label' => 'Wallet balance',
                'enabled' => true,
            ];
        }

        if ($settings['bank_transfer_enabled']) {
            $methods[] = [
                'key' => 'bank_transfer',
                'label' => 'Bank transfer',
                'enabled' => true,
                'instructions' => $settings['bank_transfer_instructions'],
                'logos' => ['bank'],
            ];
        }

        if ($settings['cash_on_delivery_enabled']) {
            $methods[] = [
                'key' => 'cash_on_delivery',
                'label' => 'Cash on delivery',
                'enabled' => true,
            ];
        }

        if ($settings['stripe_enabled']) {
            $methods[] = [
                'key' => 'stripe',
                'label' => 'Card (Stripe)',
                'enabled' => true,
                'public_key' => $settings['stripe_public_key'],
                'logos' => ['visa', 'mastercard', 'stripe'],
            ];
        }

        foreach ($settings['legacy_gateways'] as $gateway) {
            $nameKey = (string) ($gateway['name_key'] ?? $gateway['nameKey'] ?? '');
            if ($nameKey !== 'paystack' || ! filter_var($gateway['status'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $publicKey = trim((string) ($gateway['public_key'] ?? ''));
            if ($publicKey === '') {
                continue;
            }

            $methods[] = [
                'key' => 'paystack',
                'label' => 'Paystack',
                'enabled' => true,
                'public_key' => $publicKey,
                'logos' => ['paystack'],
            ];
        }

        return $methods;
    }

    /**
     * Buyer cart checkout methods (Paystack-first; Stripe excluded from cart path).
     *
     * @return list<array{key: string, label: string, enabled: bool, public_key?: string, instructions?: string, logos?: list<string>}>
     */
    public function cartMethods(): array
    {
        $allowed = ['wallet_balance', 'bank_transfer', 'paystack'];

        return array_values(array_filter(
            $this->available(),
            fn (array $method) => in_array($method['key'], $allowed, true),
        ));
    }

    /**
     * @return list<array{key: string, label: string, enabled: bool, public_key?: string, instructions?: string, logos?: list<string>}>
     */
    public function serviceMethods(): array
    {
        return array_values(array_filter(
            $this->available(),
            fn (array $method) => $method['key'] !== 'cash_on_delivery',
        ));
    }
}
