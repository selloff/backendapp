<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipPlanCategoryLimitService;
use App\Modules\Selloff\Payment\Services\MembershipPlanPresenter;
use App\Modules\Selloff\Payment\Support\MembershipPlanEntitlementValidator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class AdminMembershipController extends Controller
{
    public function __construct(
        private readonly MembershipPlanPresenter $presenter,
        private readonly MembershipPlanCategoryLimitService $categoryLimits,
    ) {}

    public function index(): JsonResponse
    {
        $orderColumn = Schema::hasColumn('membership_plans', 'plan_order') ? 'plan_order' : 'title';

        return ApiResponse::success(
            MembershipPlan::query()
                ->with('categoryLimits')
                ->orderBy($orderColumn)
                ->get()
                ->map(fn (MembershipPlan $plan) => $this->presenter->format($plan)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('membership'), 403);

        $data = $this->validatedPlanData($request, false);
        $categoryLimits = $data['category_limits'] ?? null;
        unset($data['category_limits']);

        $plan = MembershipPlan::query()->create($this->planPayload($data));

        $this->categoryLimits->sync($plan, $categoryLimits);
        $plan->load('categoryLimits');

        return ApiResponse::success($this->presenter->format($plan), 201);
    }

    public function update(Request $request, MembershipPlan $membershipPlan): JsonResponse
    {
        abort_unless($request->user()->can('membership'), 403);

        $data = $this->validatedPlanData($request, true);
        $categoryLimits = array_key_exists('category_limits', $data) ? $data['category_limits'] : null;
        unset($data['category_limits']);

        if ($data !== []) {
            $membershipPlan->update($this->planPayload($data, partial: true));
        }

        if ($categoryLimits !== null) {
            $this->categoryLimits->sync($membershipPlan, $categoryLimits);
        }

        $membershipPlan->load('categoryLimits');

        return ApiResponse::success($this->presenter->format($membershipPlan->fresh()));
    }

    public function destroy(MembershipPlan $membershipPlan): JsonResponse
    {
        $membershipPlan->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPlanData(Request $request, bool $partial): array
    {
        $rules = array_merge([
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'plan_order' => ['nullable', 'integer', 'min:1'],
            'is_popular' => ['nullable', 'boolean'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:500'],
        ], MembershipPlanEntitlementValidator::rules($partial));

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validatorInstance): void {
            MembershipPlanEntitlementValidator::validateRootCategories($validatorInstance);
        });
        $validator->validate();

        return $validator->validated();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function planPayload(array $data, bool $partial = false): array
    {
        $payload = [];

        foreach ([
            'title',
            'description',
            'price',
            'currency_code',
            'duration_days',
            'is_active',
            'visibility_multiplier',
            'global_listing_limit',
            'auto_bump_interval_hours',
            'top_credits_per_period',
            'top_badge_label',
            'top_rank_weight',
            'allow_website_link',
            'allow_social_links',
            'allow_whatsapp_link',
            'hide_seller_feedback',
            'is_free',
            'marketing_benefits',
        ] as $field) {
            if ($partial && ! array_key_exists($field, $data)) {
                continue;
            }

            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (! $partial) {
            $payload['currency_code'] ??= 'NGN';
            $payload['duration_days'] ??= 30;
            $payload['is_active'] ??= true;
        }

        if (Schema::hasColumn('membership_plans', 'plan_order') && array_key_exists('plan_order', $data)) {
            $payload['plan_order'] = $data['plan_order'] ?? 1;
        }

        if (Schema::hasColumn('membership_plans', 'is_popular') && array_key_exists('is_popular', $data)) {
            $payload['is_popular'] = $data['is_popular'] ?? false;
        }

        if (Schema::hasColumn('membership_plans', 'features') && array_key_exists('features', $data)) {
            $payload['features'] = $data['features'] ?? [];
        }

        return $payload;
    }
}
