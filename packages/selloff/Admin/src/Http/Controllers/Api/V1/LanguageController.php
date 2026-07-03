<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Models\Language;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LanguageController extends Controller
{
    public function index(): JsonResponse
    {
        $languages = Language::query()
            ->where('status', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_default']);

        return ApiResponse::success($languages);
    }
}
