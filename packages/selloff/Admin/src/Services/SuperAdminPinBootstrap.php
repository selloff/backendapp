<?php

namespace App\Modules\Selloff\Admin\Services;

use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Hash;

class SuperAdminPinBootstrap
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function isConfigured(): bool
    {
        $hash = $this->settings->all()['super_admin_pin_hash'] ?? null;

        return is_string($hash) && $hash !== '';
    }

    /**
     * Set the global Super Admin PIN hash when missing.
     *
     * @return bool True when a hash was written.
     */
    public function ensureConfigured(?string $pin = null): bool
    {
        if ($this->isConfigured()) {
            return false;
        }

        $pin = $pin ?? config('app.super_admin_bootstrap_pin');

        if (! is_string($pin) || ! preg_match('/^\d{6}$/', $pin)) {
            return false;
        }

        $this->settings->upsertMany([
            'super_admin_pin_hash' => Hash::make($pin),
        ], 'security');

        return true;
    }

    /**
     * Force-set the global Super Admin PIN hash (CLI / rotation bootstrap).
     */
    public function forceSet(string $pin): void
    {
        if (! preg_match('/^\d{6}$/', $pin)) {
            throw new \InvalidArgumentException('Super Admin PIN must be exactly 6 digits.');
        }

        $this->settings->upsertMany([
            'super_admin_pin_hash' => Hash::make($pin),
        ], 'security');
    }
}
