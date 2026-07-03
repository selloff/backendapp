<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Services\AdminReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportsController extends Controller
{
    public function show(Request $request, string $type, AdminReportService $reports): JsonResponse
    {
        abort_unless(in_array($type, AdminReportService::TYPES, true), 404);

        return ApiResponse::success($reports->build($request, $type));
    }

    public function export(Request $request, string $type, AdminReportService $reports): StreamedResponse
    {
        abort_unless(in_array($type, AdminReportService::TYPES, true), 404);

        return $reports->exportCsv($request, $type);
    }
}
