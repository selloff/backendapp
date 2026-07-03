<?php

namespace App\Modules\Selloff\Referral\Services;

use App\Services\Platform\PlatformSettingsService;

class ReferralProgramSettingsService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function programSettings(): array
    {
        $stored = $this->stored();

        return [
            'status' => filter_var($stored['status'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'points_per_signup' => max(0, (int) ($stored['points_per_signup'] ?? 10)),
            'min_points_to_redeem' => max(1, (int) ($stored['min_points_to_redeem'] ?? 100)),
            'money_per_point' => max(0, (float) ($stored['money_per_point'] ?? 10)),
            'max_redemptions_per_day' => max(1, (int) ($stored['max_redemptions_per_day'] ?? 3)),
            'title' => (string) ($stored['title'] ?? 'Invite Friends'),
            'description' => (string) ($stored['description'] ?? 'Share your referral code and earn points when friends verify their email.'),
            'how_it_works' => (string) ($stored['how_it_works'] ?? ''),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->programSettings()['status'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateAdminProgram(array $payload): array
    {
        $stored = $this->stored();

        foreach ([
            'status',
            'points_per_signup',
            'min_points_to_redeem',
            'money_per_point',
            'max_redemptions_per_day',
            'title',
            'description',
            'how_it_works',
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                $stored[$key] = $payload[$key];
            }
        }

        if (array_key_exists('status', $payload)) {
            $stored['status'] = filter_var($payload['status'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('points_per_signup', $payload)) {
            $stored['points_per_signup'] = max(0, (int) $payload['points_per_signup']);
        }

        if (array_key_exists('min_points_to_redeem', $payload)) {
            $stored['min_points_to_redeem'] = max(1, (int) $payload['min_points_to_redeem']);
        }

        if (array_key_exists('money_per_point', $payload)) {
            $stored['money_per_point'] = max(0, (float) $payload['money_per_point']);
        }

        if (array_key_exists('max_redemptions_per_day', $payload)) {
            $stored['max_redemptions_per_day'] = max(1, (int) $payload['max_redemptions_per_day']);
        }

        $this->settings->upsertMany(['referral_program' => $stored], 'referral');

        return $this->programSettings();
    }

    public function walletAmountForPoints(int $points): float
    {
        $moneyPerPoint = (float) $this->programSettings()['money_per_point'];

        return round(max(0, $points) * $moneyPerPoint, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $defaults = config('selloff.referral_program', []);
        $all = $this->settings->all();
        $stored = $all['referral_program'] ?? [];

        if (! is_array($stored)) {
            $stored = [];
        }

        return array_merge($defaults, $stored);
    }
}
