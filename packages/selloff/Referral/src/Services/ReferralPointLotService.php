<?php

namespace App\Modules\Selloff\Referral\Services;

use App\Modules\Selloff\Referral\Models\ReferralPointTransaction;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReferralPointLotService
{
    /**
     * @return array{wallet_amount: float, points: int}
     */
    public function lockedBalanceValue(int $userId): array
    {
        $lots = $this->openLots($userId);
        $points = 0;
        $walletAmount = 0.0;

        foreach ($lots as $lot) {
            $remaining = (int) $lot->points_remaining;
            $rate = (float) $lot->money_per_point;
            $points += $remaining;
            $walletAmount += $remaining * $rate;
        }

        return [
            'points' => $points,
            'wallet_amount' => round($walletAmount, 2),
        ];
    }

    /**
     * @return array{wallet_amount: float, allocations: list<array{earn_id: int, points: int, money_per_point: float, wallet_amount: float}>}
     */
    public function previewRedemption(int $userId, int $points): array
    {
        return $this->allocateFifo($userId, $points, false);
    }

    /**
     * @return array{wallet_amount: float, allocations: list<array{earn_id: int, points: int, money_per_point: float, wallet_amount: float}>}
     */
    public function consumeFifo(int $userId, int $points): array
    {
        return $this->allocateFifo($userId, $points, true);
    }

    /**
     * @return Collection<int, ReferralPointTransaction>
     */
    private function openLots(int $userId): Collection
    {
        return ReferralPointTransaction::query()
            ->where('user_id', $userId)
            ->where('type', 'earn')
            ->where('points_remaining', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @return array{wallet_amount: float, allocations: list<array{earn_id: int, points: int, money_per_point: float, wallet_amount: float}>}
     */
    private function allocateFifo(int $userId, int $points, bool $mutate): array
    {
        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => ['Redemption amount must be greater than zero.'],
            ]);
        }

        $lots = $mutate
            ? $this->openLots($userId)
            : ReferralPointTransaction::query()
                ->where('user_id', $userId)
                ->where('type', 'earn')
                ->where('points_remaining', '>', 0)
                ->orderBy('id')
                ->get();

        $remaining = $points;
        $walletAmount = 0.0;
        $allocations = [];

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $available = (int) $lot->points_remaining;

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            $rate = (float) $lot->money_per_point;
            $chunkAmount = round($take * $rate, 2);

            $allocations[] = [
                'earn_id' => (int) $lot->id,
                'points' => $take,
                'money_per_point' => $rate,
                'wallet_amount' => $chunkAmount,
            ];

            $walletAmount += $chunkAmount;
            $remaining -= $take;

            if ($mutate) {
                $lot->decrement('points_remaining', $take);
            }
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'points' => ['Insufficient referral points.'],
            ]);
        }

        return [
            'wallet_amount' => round($walletAmount, 2),
            'allocations' => $allocations,
        ];
    }
}
