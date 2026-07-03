<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payment\Http\Requests\Api\V1\PurchaseMembershipRequest;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipActivationService;
use App\Modules\Selloff\Payment\Services\MembershipEntitlementService;
use App\Modules\Selloff\Payment\Services\MembershipPlanPresenter;
use App\Modules\Selloff\Payment\Services\MembershipPurchaseService;
use App\Modules\Selloff\Payment\Services\MembershipQuoteService;
use App\Modules\Selloff\Payment\Services\MembershipStatusService;
use App\Support\ApiResponse;
use App\Support\Gtm\ServicePaymentGtmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(
        private readonly MembershipPurchaseService $purchaseService,
        private readonly MembershipStatusService $membershipStatus,
        private readonly MembershipQuoteService $quoteService,
        private readonly MembershipPlanPresenter $presenter,
        private readonly MembershipActivationService $activationService,
        private readonly MembershipEntitlementService $entitlementService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $plans = MembershipPlan::query()
            ->with('categoryLimits')
            ->where('is_active', true)
            ->orderBy('plan_order')
            ->orderBy('price')
            ->get()
            ->map(fn (MembershipPlan $plan) => $this->presenter->format($plan));

        return ApiResponse::success([
            'plans' => $plans,
            'term_discounts' => $this->quoteService->catalogTermDiscounts(),
            'current_membership' => $request->user()
                ? $this->membershipStatus->forUser($request->user())
                : null,
        ]);
    }

    public function quote(Request $request, MembershipPlan $membershipPlan): JsonResponse
    {
        $data = $request->validate([
            'months' => ['required', 'integer', 'in:1,3,6,12'],
        ]);

        return ApiResponse::success(
            $this->quoteService->quote(
                $request->user(),
                $membershipPlan,
                (int) $data['months'],
            ),
        );
    }

    public function subscribe(Request $request, MembershipPlan $membershipPlan): JsonResponse
    {
        abort_unless($membershipPlan->is_active, 422, 'Plan is not active.');

        $months = max(1, (int) ceil(((int) ($membershipPlan->duration_days ?? 30)) / 30));
        $subscription = $this->activationService->activate(
            $request->user(),
            $membershipPlan->load('categoryLimits'),
            'new',
            $months,
            0,
        );

        return ApiResponse::success($subscription->load('membershipPlan'), 201);
    }

    public function purchase(
        PurchaseMembershipRequest $request,
        MembershipPlan $membershipPlan,
        ServicePaymentGtmService $gtm,
    ): JsonResponse {
        $result = $this->purchaseService->purchase(
            $request->user(),
            $membershipPlan,
            (int) $request->integer('months'),
            $request->string('payment_method')->toString(),
        );

        return ApiResponse::success($gtm->attachMembershipCheckoutGtm($result, $request->user()), 201);
    }

    public function status(Request $request): JsonResponse
    {
        return ApiResponse::success($this->membershipStatus->forUser($request->user()));
    }

    public function entitlements(Request $request): JsonResponse
    {
        return ApiResponse::success($this->entitlementService->vendorEntitlementsPayload($request->user()));
    }

    public function myPlan(Request $request): JsonResponse
    {
        $subscription = UserMembershipPlan::query()
            ->with('membershipPlan')
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        return ApiResponse::success($subscription);
    }
}
