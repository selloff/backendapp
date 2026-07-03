<?php

namespace App\Modules\Selloff\Affiliate\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Affiliate\Services\AffiliateProgramSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAffiliateProgramController extends Controller
{
    public function __construct(
        private readonly AffiliateProgramSettingsService $program,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $langId = max(1, (int) $request->query('lang_id', 1));

        return ApiResponse::success($this->program->adminProgram($langId));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section' => ['required', 'string', Rule::in(['settings', 'description', 'content', 'how_it_works', 'faq'])],
            'lang_id' => ['required_unless:section,settings', 'integer', 'min:1'],
            'status' => ['sometimes', 'boolean'],
            'type' => ['sometimes', 'string', Rule::in(['site_based', 'seller_based'])],
            'commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:99'],
            'discount_rate' => ['sometimes', 'numeric', 'min:0', 'max:99'],
            'image_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'image_storage' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'array'],
            'description.title' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description.description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'content' => ['sometimes', 'array'],
            'content.title' => ['sometimes', 'nullable', 'string', 'max:500'],
            'content.content' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'how_it_works' => ['sometimes', 'array', 'max:3'],
            'how_it_works.*.title' => ['sometimes', 'nullable', 'string', 'max:500'],
            'how_it_works.*.description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'faq' => ['sometimes', 'array'],
            'faq.*.id' => ['sometimes', 'string', 'max:100'],
            'faq.*.o' => ['sometimes', 'integer', 'min:0'],
            'faq.*.q' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'faq.*.a' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $section = (string) $data['section'];
        $langId = max(1, (int) ($data['lang_id'] ?? 1));

        unset($data['section'], $data['lang_id']);

        return ApiResponse::success(
            $this->program->updateAdminProgram($langId, $section, $data),
        );
    }
}
