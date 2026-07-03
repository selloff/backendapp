<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Services\VendorListingPerformanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminListingPerformanceController extends Controller
{
    public function show(Request $request, VendorListingPerformanceService $performance): JsonResponse
    {
        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->string('from'))->startOfDay();
            $to = Carbon::parse($request->string('to'))->endOfDay();
            $periodLabel = $request->string('period_label')->toString();

            if ($periodLabel === '') {
                $periodLabel = $from->format('d/m/Y').' - '.$to->format('d/m/Y');
            }

            return ApiResponse::success($performance->platformSummaryForRange($from, $to, $periodLabel));
        }

        $period = $request->string('period', '7d')->toString();

        return ApiResponse::success($performance->platformSummary($period));
    }
}
