<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payment\Models\MembershipTermDiscount;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminMembershipTermDiscountController extends Controller
{
    public function index(): JsonResponse
    {
        if (! Schema::hasTable('membership_term_discounts')) {
            return ApiResponse::success([]);
        }

        $discounts = MembershipTermDiscount::query()
            ->orderBy('months')
            ->get()
            ->map(fn (MembershipTermDiscount $discount) => $this->formatDiscount($discount));

        return ApiResponse::success($discounts);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('membership'), 403);

        $data = $request->validate([
            'discounts' => ['required', 'array', 'min:1'],
            'discounts.*.months' => ['required', 'integer', Rule::in([1, 3, 6, 12])],
            'discounts.*.discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'discounts.*.is_active' => ['nullable', 'boolean'],
        ]);

        $updated = [];
        foreach ($data['discounts'] as $entry) {
            $discount = MembershipTermDiscount::query()->updateOrCreate(
                ['months' => (int) $entry['months']],
                [
                    'discount_percent' => $entry['discount_percent'],
                    'is_active' => $entry['is_active'] ?? true,
                ],
            );
            $updated[] = $this->formatDiscount($discount);
        }

        usort($updated, fn (array $a, array $b) => $a['months'] <=> $b['months']);

        return ApiResponse::success($updated);
    }

    private function formatDiscount(MembershipTermDiscount $discount): array
    {
        return [
            'id' => $discount->id,
            'months' => (int) $discount->months,
            'discount_percent' => $discount->discount_percent,
            'is_active' => (bool) $discount->is_active,
        ];
    }
}
