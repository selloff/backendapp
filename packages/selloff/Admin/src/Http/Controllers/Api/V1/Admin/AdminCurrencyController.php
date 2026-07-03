<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Services\Platform\ExchangeRateUpdateService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCurrencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 50), 200);

        return ApiResponse::success(Currency::query()->orderBy('code')->paginate($perPage));
    }

    public function show(Currency $currency): JsonResponse
    {
        return ApiResponse::success($currency);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'currency_format' => ['nullable', 'string', 'max:30'],
            'symbol_direction' => ['nullable', 'string', 'max:30'],
            'space_money_symbol' => ['nullable', 'boolean'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        $currency = Currency::query()->create([
            ...$data,
            'currency_format' => $data['currency_format'] ?? 'us',
            'symbol_direction' => $data['symbol_direction'] ?? 'left',
            'space_money_symbol' => $data['space_money_symbol'] ?? false,
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'status' => $data['status'] ?? false,
        ]);

        return ApiResponse::success($currency, 201);
    }

    public function update(Request $request, Currency $currency): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:10'],
            'name' => ['sometimes', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'currency_format' => ['nullable', 'string', 'max:30'],
            'symbol_direction' => ['nullable', 'string', 'max:30'],
            'space_money_symbol' => ['nullable', 'boolean'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        $currency->update($data);

        return ApiResponse::success($currency->fresh());
    }

    public function destroy(Currency $currency): JsonResponse
    {
        $currency->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function refreshRates(ExchangeRateUpdateService $service): JsonResponse
    {
        $result = $service->update();

        return ApiResponse::success($result);
    }
}
