<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Services\AiWriterService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiWriterController extends Controller
{
    public function generate(
        Request $request,
        AiWriterService $aiWriter,
        PlatformSettingsService $platformSettings,
    ): JsonResponse {
        abort_unless($request->user()?->can('ai_writer'), 403);
        abort_unless($this->isEnabled($platformSettings), 403);

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'content_type' => ['sometimes', 'string', 'in:product,page,blog'],
            'tone' => ['sometimes', 'string', 'max:50'],
            'length' => ['sometimes', 'string', 'max:50'],
            'model' => ['sometimes', 'string', 'max:50'],
        ]);

        $result = $aiWriter->generate($data);

        return ApiResponse::success($result);
    }

    private function isEnabled(PlatformSettingsService $platformSettings): bool
    {
        $value = $platformSettings->all()['ai_writer_status'] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
