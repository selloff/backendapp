<?php

namespace App\Modules\Selloff\Referral\Actions;

use App\Models\User;
use App\Modules\Selloff\Referral\Models\ReferralPointTransaction;
use App\Modules\Selloff\Referral\Services\ReferralProgramSettingsService;
use App\Modules\Selloff\User\Models\ReferralProfile;
use Illuminate\Support\Facades\DB;

class AwardReferralPointsAction
{
    public function __construct(
        private readonly ReferralProgramSettingsService $settings,
    ) {}

    public function execute(User $verifiedUser): ?ReferralPointTransaction
    {
        if (! $verifiedUser->email_verified_at) {
            return null;
        }

        if (! $this->settings->isEnabled()) {
            return null;
        }

        $profile = ReferralProfile::query()->where('user_id', $verifiedUser->id)->first();

        if (! $profile || ! $profile->referral_user_id) {
            return null;
        }

        if ($profile->referral_user_id === $verifiedUser->id) {
            return null;
        }

        $points = $this->settings->programSettings()['points_per_signup'];
        $moneyPerPoint = (float) $this->settings->programSettings()['money_per_point'];

        if ($points <= 0) {
            return null;
        }

        $existing = ReferralPointTransaction::query()
            ->where('type', 'earn')
            ->where('referred_user_id', $verifiedUser->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($profile, $verifiedUser, $points, $moneyPerPoint) {
            $referrerProfile = ReferralProfile::query()
                ->where('user_id', $profile->referral_user_id)
                ->lockForUpdate()
                ->first();

            if (! $referrerProfile) {
                return null;
            }

            $referrerProfile->increment('referral_points', $points);

            return ReferralPointTransaction::query()->create([
                'user_id' => $referrerProfile->user_id,
                'type' => 'earn',
                'points' => $points,
                'money_per_point' => $moneyPerPoint,
                'points_remaining' => $points,
                'referred_user_id' => $verifiedUser->id,
                'description' => 'Referral signup: '.$verifiedUser->name,
            ]);
        });
    }
}
