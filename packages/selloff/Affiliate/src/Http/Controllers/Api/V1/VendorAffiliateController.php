<?php

namespace App\Modules\Selloff\Affiliate\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Affiliate\Models\AffiliateEarning;
use App\Modules\Selloff\Affiliate\Models\AffiliateLink;
use App\Modules\Selloff\Affiliate\Services\VendorAffiliateProgramService;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorAffiliateController extends Controller
{
    public function __construct(
        private readonly VendorAffiliateProgramService $affiliateProgram,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $profile = $this->referralProfile($request);

        return ApiResponse::success($this->serializeProgram($request, $profile));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_affiliate_status' => ['sometimes', 'integer', Rule::in([0, 1, 2])],
            'affiliate_commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'affiliate_discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $profile = $this->referralProfile($request);

        if (array_key_exists('vendor_affiliate_status', $data)) {
            $profile->vendor_affiliate_status = (int) $data['vendor_affiliate_status'];
        }

        $profile->affiliate_commission_rate = $data['affiliate_commission_rate'];
        $profile->affiliate_discount_rate = $data['affiliate_discount_rate'] ?? 0;
        $profile->save();

        return ApiResponse::success($this->serializeProgram($request, $profile->fresh()));
    }

    public function links(Request $request): JsonResponse
    {
        $links = AffiliateLink::query()
            ->with(['product.translations', 'referrer'])
            ->where('seller_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($links);
    }

    public function earnings(Request $request): JsonResponse
    {
        $earnings = AffiliateEarning::query()
            ->with(['product.translations', 'referrer'])
            ->where('seller_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($earnings);
    }

    private function referralProfile(Request $request): ReferralProfile
    {
        return ReferralProfile::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['referral_code' => strtoupper(Str::random(8))],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProgram(Request $request, ReferralProfile $profile): array
    {
        $program = $this->affiliateProgram->programSettings();

        return [
            'referral_code' => $profile->referral_code,
            'affiliate_commission_rate' => $profile->affiliate_commission_rate,
            'affiliate_discount_rate' => $profile->affiliate_discount_rate,
            'vendor_affiliate_status' => (int) $profile->vendor_affiliate_status,
            'program_enabled' => $this->affiliateProgram->programEnabled(),
            'program_type' => (string) ($program['type'] ?? 'site_based'),
            'can_manage_product_affiliate' => $this->affiliateProgram->canManageProductAffiliate($profile),
            'links_count' => AffiliateLink::query()->where('seller_id', $request->user()->id)->count(),
            'earnings_total' => AffiliateEarning::query()->where('seller_id', $request->user()->id)->sum('earned_amount'),
        ];
    }
}
