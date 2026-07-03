<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        $currencies = Currency::query()
            ->where('status', true)
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'name',
                'symbol',
                'exchange_rate',
                'currency_format',
                'symbol_direction',
                'space_money_symbol',
            ]);

        return ApiResponse::success($currencies);
    }
}
