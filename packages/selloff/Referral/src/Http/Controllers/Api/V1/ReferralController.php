<?php

namespace App\Modules\Selloff\Referral\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Referral\Actions\GetReferralDashboardAction;
use App\Modules\Selloff\Referral\Actions\RedeemReferralPointsAction;
use App\Modules\Selloff\Referral\Models\ReferralPointTransaction;
use App\Modules\Selloff\Referral\Services\ReferralPointLotService;
use App\Modules\Selloff\Referral\Services\ReferralProgramSettingsService;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function show(Request $request, GetReferralDashboardAction $dashboard): JsonResponse
    {
        return ApiResponse::success($dashboard->execute($request->user()));
    }

    public function program(ReferralProgramSettingsService $settings): JsonResponse
    {
        $program = $settings->programSettings();

        return ApiResponse::success([
            'enabled' => $program['status'],
            'title' => $program['title'],
            'description' => $program['description'],
            'how_it_works' => $program['how_it_works'],
            'points_per_signup' => $program['points_per_signup'],
            'min_points_to_redeem' => $program['min_points_to_redeem'],
            'money_per_point' => $program['money_per_point'],
            'min_wallet_amount' => round((int) $program['min_points_to_redeem'] * (float) $program['money_per_point'], 2),
        ]);
    }

    public function referredUsers(Request $request): JsonResponse
    {
        $profiles = ReferralProfile::query()
            ->with('user:id,first_name,last_name,email,email_verified_at,created_at')
            ->where('referral_user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        $creditedIds = ReferralPointTransaction::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'earn')
            ->pluck('referred_user_id')
            ->all();

        $profiles->getCollection()->transform(function (ReferralProfile $profile) use ($creditedIds) {
            $referred = $profile->user;

            return [
                'id' => $profile->id,
                'user_id' => $profile->user_id,
                'name' => $referred?->name,
                'email' => $referred?->email,
                'referred_by_code' => $profile->referred_by_code,
                'email_verified' => $referred?->email_verified_at !== null,
                'points_credited' => in_array($profile->user_id, $creditedIds, true),
                'joined_at' => $referred?->created_at,
            ];
        });

        return ApiResponse::success($profiles);
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = ReferralPointTransaction::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($transactions);
    }

    public function redeem(Request $request, RedeemReferralPointsAction $redeem, ReferralProgramSettingsService $settings): JsonResponse
    {
        $minPoints = (int) $settings->programSettings()['min_points_to_redeem'];

        $data = $request->validate([
            'points' => ['required', 'integer', 'min:'.$minPoints],
        ]);

        $result = $redeem->execute($request->user(), (int) $data['points']);

        return ApiResponse::success($result, 200, 'Points redeemed to wallet.');
    }

    public function redeemPreview(Request $request, ReferralPointLotService $pointLots, ReferralProgramSettingsService $settings): JsonResponse
    {
        $minPoints = (int) $settings->programSettings()['min_points_to_redeem'];

        $data = $request->validate([
            'points' => ['required', 'integer', 'min:'.$minPoints],
        ]);

        $preview = $pointLots->previewRedemption((int) $request->user()->id, (int) $data['points']);

        return ApiResponse::success([
            'points' => (int) $data['points'],
            'wallet_amount' => $preview['wallet_amount'],
            'effective_money_per_point' => (int) $data['points'] > 0
                ? round($preview['wallet_amount'] / (int) $data['points'], 2)
                : 0,
            'allocations' => $preview['allocations'],
        ]);
    }
}
