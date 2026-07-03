<?php

namespace App\Modules\Selloff\Referral\Actions;

use App\Models\User;
use App\Modules\Selloff\Referral\Services\ReferralProgramSettingsService;
use App\Modules\Selloff\User\Models\ReferralProfile;
use Illuminate\Validation\ValidationException;

class ApplyReferralCodeOnRegisterAction
{
    public function __construct(
        private readonly EnsureReferralProfileAction $ensureProfile,
        private readonly ReferralProgramSettingsService $settings,
    ) {}

    /**
     * Validate referral code before creating a new account (registration / OAuth).
     *
     * @throws ValidationException
     */
    public function validateForNewRegistration(?string $referralCode): void
    {
        if ($referralCode === null || trim($referralCode) === '') {
            return;
        }

        if (! $this->settings->isEnabled()) {
            throw ValidationException::withMessages([
                'referral_code' => ['Referral program is currently disabled.'],
            ]);
        }

        $normalized = strtoupper(trim($referralCode));

        if (! ReferralProfile::query()->where('referral_code', $normalized)->exists()) {
            throw ValidationException::withMessages([
                'referral_code' => ['Invalid referral code.'],
            ]);
        }
    }

    public function execute(User $user, ?string $referralCode): void
    {
        $this->ensureProfile->execute($user);

        if ($referralCode === null || trim($referralCode) === '') {
            return;
        }

        if (! $this->settings->isEnabled()) {
            throw ValidationException::withMessages([
                'referral_code' => ['Referral program is currently disabled.'],
            ]);
        }

        $normalized = strtoupper(trim($referralCode));

        $existingProfile = ReferralProfile::query()->where('user_id', $user->id)->first();

        if ($existingProfile?->referral_user_id) {
            throw ValidationException::withMessages([
                'referral_code' => ['A referral code has already been applied to this account.'],
            ]);
        }

        $referrerProfile = ReferralProfile::query()
            ->where('referral_code', $normalized)
            ->first();

        if (! $referrerProfile) {
            throw ValidationException::withMessages([
                'referral_code' => ['Invalid referral code.'],
            ]);
        }

        if ($referrerProfile->user_id === $user->id) {
            throw ValidationException::withMessages([
                'referral_code' => ['You cannot use your own referral code.'],
            ]);
        }

        ReferralProfile::query()
            ->where('user_id', $user->id)
            ->whereNull('referral_user_id')
            ->update([
                'referral_user_id' => $referrerProfile->user_id,
                'referred_by_code' => $normalized,
            ]);
    }
}
