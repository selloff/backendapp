<?php

namespace App\Modules\Selloff\Affiliate\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Affiliate\Actions\JoinAffiliateProgramAction;
use App\Modules\Selloff\Affiliate\Models\AffiliateEarning;
use App\Modules\Selloff\Affiliate\Models\AffiliateLink;
use App\Modules\Selloff\Affiliate\Services\AffiliateProgramSettingsService;
use App\Modules\Selloff\Affiliate\Services\AffiliateRateService;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AffiliateLinkController extends Controller
{
    public function program(AffiliateProgramSettingsService $settings): JsonResponse
    {
        $program = $settings->publicProgram();

        return ApiResponse::success($program);
    }

    public function join(Request $request, JoinAffiliateProgramAction $join): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'integer', 'min:1'],
            'state_id' => ['sometimes', 'nullable', 'integer'],
            'city_id' => ['sometimes', 'nullable', 'integer'],
            'address' => ['sometimes', 'nullable', 'string', 'max:490'],
            'zip_code' => ['sometimes', 'nullable', 'string', 'max:90'],
            'terms' => ['accepted'],
        ]);

        $user = $join->execute($request->user(), $data);

        return ApiResponse::success([
            'is_affiliate' => (int) $user->is_affiliate,
        ], 200, 'Joined affiliate program.');
    }

    public function resolve(string $shortCode): JsonResponse
    {
        $link = AffiliateLink::query()
            ->with(['product.translations'])
            ->where('link_short', $shortCode)
            ->first();

        if (! $link || ! $link->product) {
            return ApiResponse::error('Affiliate link not found.', 404);
        }

        return ApiResponse::success([
            'id' => $link->id,
            'link_short' => $link->link_short,
            'product_id' => $link->product_id,
            'product_slug' => $link->product->slug,
            'product_title' => $link->product->translations->first()?->title,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $links = AffiliateLink::query()
            ->with(['product.translations', 'seller'])
            ->where('referrer_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($links);
    }

    public function store(Request $request, AffiliateRateService $rates): JsonResponse
    {
        if ((int) ($request->user()->is_affiliate ?? 0) !== 1) {
            throw ValidationException::withMessages([
                'affiliate' => ['You must join the affiliate program first.'],
            ]);
        }

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);

        if (! $rates->canPromoteProduct($product, $request->user())) {
            throw ValidationException::withMessages([
                'product_id' => ['This product is not eligible for affiliate promotion.'],
            ]);
        }

        $link = AffiliateLink::query()->firstOrCreate(
            [
                'referrer_id' => $request->user()->id,
                'product_id' => $product->id,
            ],
            [
                'seller_id' => $product->vendor_id,
                'link_short' => strtoupper(Str::random(8)),
            ],
        );

        return ApiResponse::success($link->load(['product.translations', 'seller']), 201);
    }

    public function destroy(Request $request, AffiliateLink $link): JsonResponse
    {
        if ((int) $link->referrer_id !== (int) $request->user()->id) {
            abort(403);
        }

        $link->delete();

        return ApiResponse::success(message: 'Affiliate link deleted.');
    }

    public function earnings(Request $request): JsonResponse
    {
        $this->ensureReferralProfile($request);

        $earnings = AffiliateEarning::query()
            ->with(['product.translations', 'seller'])
            ->where('referrer_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($earnings);
    }

    private function ensureReferralProfile(Request $request): ReferralProfile
    {
        return ReferralProfile::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['referral_code' => strtoupper(Str::random(8))],
        );
    }
}
