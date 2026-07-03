<?php

namespace App\Modules\Selloff\Referral\Actions;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\WalletTransaction;
use App\Modules\Selloff\Referral\Models\ReferralPointTransaction;
use App\Modules\Selloff\Referral\Services\ReferralPointLotService;
use App\Modules\Selloff\Referral\Services\ReferralProgramSettingsService;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemReferralPointsAction
{
    public function __construct(
        private readonly ReferralProgramSettingsService $referralSettings,
        private readonly ReferralPointLotService $pointLots,
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    /**
     * @return array{points_redeemed: int, wallet_amount: float, wallet_balance: float, effective_money_per_point: float}
     */
    public function execute(User $user, int $points): array
    {
        if (! $this->referralSettings->isEnabled()) {
            throw ValidationException::withMessages([
                'points' => ['Referral program is currently disabled.'],
            ]);
        }

        $settings = $this->referralSettings->programSettings();
        $minPoints = (int) $settings['min_points_to_redeem'];
        $maxPerDay = (int) $settings['max_redemptions_per_day'];

        if ($points < $minPoints) {
            throw ValidationException::withMessages([
                'points' => ["Minimum redemption is {$minPoints} points."],
            ]);
        }

        $platform = $this->platformSettings->all();
        if (! filter_var($platform['wallet_status'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'points' => ['Wallet is not enabled.'],
            ]);
        }

        $redemptionsToday = ReferralPointTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'redeem')
            ->whereDate('created_at', today())
            ->count();

        if ($redemptionsToday >= $maxPerDay) {
            throw ValidationException::withMessages([
                'points' => ['Daily redemption limit reached.'],
            ]);
        }

        return DB::transaction(function () use ($user, $points) {
            $profile = ReferralProfile::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $profile || (int) $profile->referral_points < $points) {
                throw ValidationException::withMessages([
                    'points' => ['Insufficient referral points.'],
                ]);
            }

            $allocation = $this->pointLots->consumeFifo((int) $user->id, $points);
            $walletAmount = (float) $allocation['wallet_amount'];

            if ($walletAmount <= 0) {
                throw ValidationException::withMessages([
                    'points' => ['Redemption amount must be greater than zero.'],
                ]);
            }

            $effectiveRate = round($walletAmount / $points, 2);

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $newBalance = round((float) $lockedUser->wallet_balance + $walletAmount, 2);

            $profile->decrement('referral_points', $points);
            $lockedUser->update(['wallet_balance' => $newBalance]);

            ReferralPointTransaction::query()->create([
                'user_id' => $user->id,
                'type' => 'redeem',
                'points' => $points,
                'money_per_point' => $effectiveRate,
                'wallet_amount' => $walletAmount,
                'description' => 'Redeemed referral points to wallet',
            ]);

            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'type' => 'referral_redeem',
                'amount' => $walletAmount,
                'balance_after' => $newBalance,
                'description' => 'Referral points redemption',
            ]);

            return [
                'points_redeemed' => $points,
                'wallet_amount' => $walletAmount,
                'wallet_balance' => $newBalance,
                'effective_money_per_point' => $effectiveRate,
            ];
        });
    }
}
