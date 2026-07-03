<?php

namespace App\Modules\Selloff\Referral\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Referral\Models\ReferralPointTransaction;
use App\Modules\Selloff\Referral\Services\ReferralProgramSettingsService;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReferralController extends Controller
{
    public function showProgram(ReferralProgramSettingsService $settings): JsonResponse
    {
        return ApiResponse::success($settings->programSettings());
    }

    public function updateProgram(Request $request, ReferralProgramSettingsService $settings): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'boolean'],
            'points_per_signup' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'min_points_to_redeem' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'money_per_point' => ['sometimes', 'numeric', 'min:0'],
            'max_redemptions_per_day' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'how_it_works' => ['sometimes', 'string', 'max:10000'],
        ]);

        return ApiResponse::success($settings->updateAdminProgram($data));
    }

    public function index(Request $request): JsonResponse
    {
        $profiles = ReferralProfile::query()
            ->with([
                'user:id,first_name,last_name,email,email_verified_at,created_at',
            ])
            ->whereNotNull('referral_user_id')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        $credited = ReferralPointTransaction::query()
            ->where('type', 'earn')
            ->get(['user_id', 'referred_user_id', 'points', 'created_at'])
            ->keyBy('referred_user_id');

        $referrerIds = $profiles->getCollection()->pluck('referral_user_id')->unique()->filter()->all();
        $referrers = ReferralProfile::query()
            ->with('user:id,first_name,last_name,email')
            ->whereIn('user_id', $referrerIds)
            ->get()
            ->keyBy('user_id');

        $profiles->getCollection()->transform(function (ReferralProfile $profile) use ($credited, $referrers) {
            $referred = $profile->user;
            $referrer = $referrers->get($profile->referral_user_id);
            $earn = $credited->get($profile->user_id);

            return [
                'id' => $profile->id,
                'referred_user' => [
                    'id' => $referred?->id,
                    'name' => $referred?->name,
                    'email' => $referred?->email,
                    'email_verified' => $referred?->email_verified_at !== null,
                    'joined_at' => $referred?->created_at,
                ],
                'referrer' => [
                    'id' => $referrer?->user_id,
                    'name' => $referrer?->user?->name,
                    'email' => $referrer?->user?->email,
                    'referral_code' => $referrer?->referral_code,
                ],
                'referred_by_code' => $profile->referred_by_code,
                'points_awarded' => $earn?->points,
                'points_awarded_at' => $earn?->created_at,
            ];
        });

        return ApiResponse::success($profiles);
    }
}
