<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Services\Platform\PlatformSettingsService;

class PaymentGatewaySettingsService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = $this->settings->all();

        return [
            'wallet_enabled' => $this->bool($stored, 'payment_wallet_enabled', config('selloff_payments.wallet.enabled', true)),
            'wallet_deposit_enabled' => $this->bool($stored, 'payment_wallet_deposit_enabled', true),
            'wallet_min_deposit' => (float) ($stored['payment_wallet_min_deposit'] ?? 0),
            'bank_transfer_enabled' => $this->bool($stored, 'payment_bank_transfer_enabled', config('selloff_payments.bank_transfer.enabled', true)),
            'bank_transfer_instructions' => (string) ($stored['payment_bank_transfer_instructions'] ?? config('selloff_payments.bank_transfer.instructions')),
            'cash_on_delivery_enabled' => $this->bool($stored, 'payment_cod_enabled', config('selloff_payments.cash_on_delivery.enabled', true)),
            'cash_on_delivery_debt_limit' => (float) ($stored['cash_on_delivery_debt_limit'] ?? 0),
            'stripe_enabled' => $this->bool($stored, 'payment_stripe_enabled', config('selloff_payments.stripe.enabled', false)),
            'stripe_public_key' => (string) ($stored['payment_stripe_public_key'] ?? config('selloff_payments.stripe.public_key')),
            'commission_rate' => (float) ($stored['commission_rate'] ?? 0),
            'vat_status' => $this->bool($stored, 'vat_status', false),
            'cart_location_selection' => $this->bool($stored, 'cart_location_selection', true),
            'additional_invoice_info' => $this->invoiceInfo($stored),
            'legacy_gateways' => $this->legacyGateways($stored),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): array
    {
        $map = [];

        if (array_key_exists('wallet_enabled', $values)) {
            $map['payment_wallet_enabled'] = (bool) $values['wallet_enabled'];
        }
        if (array_key_exists('wallet_deposit_enabled', $values)) {
            $map['payment_wallet_deposit_enabled'] = (bool) $values['wallet_deposit_enabled'];
        }
        if (array_key_exists('wallet_min_deposit', $values)) {
            $map['payment_wallet_min_deposit'] = (float) $values['wallet_min_deposit'];
        }
        if (array_key_exists('bank_transfer_enabled', $values)) {
            $map['payment_bank_transfer_enabled'] = (bool) $values['bank_transfer_enabled'];
        }
        if (array_key_exists('bank_transfer_instructions', $values)) {
            $map['payment_bank_transfer_instructions'] = (string) $values['bank_transfer_instructions'];
        }
        if (array_key_exists('cash_on_delivery_enabled', $values)) {
            $map['payment_cod_enabled'] = (bool) $values['cash_on_delivery_enabled'];
        }
        if (array_key_exists('stripe_enabled', $values)) {
            $map['payment_stripe_enabled'] = (bool) $values['stripe_enabled'];
        }
        if (array_key_exists('stripe_public_key', $values)) {
            $map['payment_stripe_public_key'] = (string) $values['stripe_public_key'];
        }
        if (array_key_exists('cash_on_delivery_debt_limit', $values)) {
            $map['cash_on_delivery_debt_limit'] = (float) $values['cash_on_delivery_debt_limit'];
        }
        if (array_key_exists('commission_rate', $values)) {
            $map['commission_rate'] = (float) $values['commission_rate'];
        }
        if (array_key_exists('vat_status', $values)) {
            $map['vat_status'] = (bool) $values['vat_status'];
        }
        if (array_key_exists('cart_location_selection', $values)) {
            $map['cart_location_selection'] = (bool) $values['cart_location_selection'];
        }
        if (array_key_exists('additional_invoice_info', $values)) {
            $map['additional_invoice_info'] = json_encode($values['additional_invoice_info'] ?? []);
        }

        if ($map !== []) {
            $this->settings->upsertMany($map, 'payment');
        }

        return $this->all();
    }

    /**
     * @param  array<string, mixed>  $gateway
     * @return array<string, mixed>
     */
    public function updateLegacyGateway(string $nameKey, array $gateway): array
    {
        $stored = $this->settings->all();
        $gateways = $this->legacyGateways($stored);
        $index = null;

        foreach ($gateways as $i => $row) {
            if (($row['name_key'] ?? $row['nameKey'] ?? null) === $nameKey) {
                $index = $i;
                break;
            }
        }

        $payload = array_merge($index !== null ? $gateways[$index] : ['name_key' => $nameKey], $gateway);

        if ($index !== null) {
            $gateways[$index] = $payload;
        } else {
            $gateways[] = $payload;
        }

        $this->settings->upsertMany(['legacy_payment_gateways' => json_encode(array_values($gateways))], 'payment');

        return $this->all();
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, string>
     */
    private function invoiceInfo(array $stored): array
    {
        $raw = $stored['additional_invoice_info'] ?? null;

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_map(static fn ($value) => (string) $value, $decoded);
            }

            $legacy = @unserialize($raw, ['allowed_classes' => false]);
            if (is_array($legacy)) {
                return array_map(static fn ($value) => (string) $value, $legacy);
            }
        }

        if (is_array($raw)) {
            return array_map(static fn ($value) => (string) $value, $raw);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return list<array<string, mixed>>
     */
    private function legacyGateways(array $stored): array
    {
        $raw = $stored['legacy_payment_gateways'] ?? null;

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        if (is_array($raw)) {
            return array_values($raw);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function bool(array $stored, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $stored)) {
            return $default;
        }

        $value = $stored[$key];

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
