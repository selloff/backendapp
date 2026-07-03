<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payment\Models\TaxRule;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTaxRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rules = TaxRule::query()
            ->when($request->has('status'), fn ($q) => $q->where('status', $request->boolean('status')))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::success($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'status' => ['nullable', 'boolean'],
        ]);

        $rule = TaxRule::query()->create([
            ...$data,
            'status' => $data['status'] ?? true,
        ]);

        return ApiResponse::success($rule, 201);
    }

    public function update(Request $request, TaxRule $taxRule): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'status' => ['sometimes', 'boolean'],
        ]);

        $taxRule->update($data);

        return ApiResponse::success($taxRule->fresh());
    }

    public function destroy(TaxRule $taxRule): JsonResponse
    {
        $taxRule->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
