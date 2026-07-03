<?php

namespace App\Modules\Selloff\Referral\Actions;

use App\Models\User;
use App\Modules\Selloff\Referral\Models\ReferralPointTransaction;
use App\Modules\Selloff\Referral\Services\ReferralPointLotService;
use App\Modules\Selloff\Referral\Services\ReferralProgramSettingsService;
use App\Modules\Selloff\User\Models\ReferralProfile;

class GetReferralDashboardAction
{
    public function __construct(
        private readonly EnsureReferralProfileAction $ensureProfile,
        private readonly ReferralProgramSettingsService $settings,
        private readonly ReferralPointLotService $pointLots,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(User $user): array
    {
        $profile = $this->ensureProfile->execute($user);
        $program = $this->settings->programSettings();

        $referredCount = ReferralProfile::query()
            ->where('referral_user_id', $user->id)
            ->count();

        $creditedCount = ReferralPointTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'earn')
            ->count();

        $locked = $this->pointLots->lockedBalanceValue((int) $user->id);
        $points = (int) $profile->referral_points;
        $moneyPerPoint = (float) $program['money_per_point'];
        $minPoints = (int) $program['min_points_to_redeem'];

        $minWalletAmount = 0.0;
        $canRedeem = false;

        if ($points >= $minPoints) {
            try {
                $minPreview = $this->pointLots->previewRedemption((int) $user->id, $minPoints);
                $minWalletAmount = (float) $minPreview['wallet_amount'];
                $canRedeem = $minWalletAmount > 0;
            } catch (\Throwable) {
                $canRedeem = false;
            }
        }

        return [
            'referral_code' => $profile->referral_code,
            'share_url' => rtrim((string) config('selloff.spa_url', config('app.url')), '/').'/register?ref='.$profile->referral_code,
            'points_balance' => $points,
            'referred_users_count' => $referredCount,
            'credited_referrals_count' => $creditedCount,
            'program' => [
                'enabled' => $program['status'],
                'points_per_signup' => $program['points_per_signup'],
                'min_points_to_redeem' => $minPoints,
                'money_per_point' => $moneyPerPoint,
                'min_wallet_amount' => $minWalletAmount,
                'redemption_value_preview' => $locked['wallet_amount'],
                'can_redeem' => $canRedeem,
            ],
        ];
    }
}
