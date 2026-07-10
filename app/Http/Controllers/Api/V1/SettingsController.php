<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePlatformSettingsRequest;
use App\Modules\Selloff\Notification\Services\PlatformMailService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly PlatformMailService $mail,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(UpdatePlatformSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->settings->upsertMany(
            $validated['settings'],
            $validated['group'] ?? 'general',
        );

        return ApiResponse::success([
            'settings' => $this->settings->all(),
            'group' => $validated['group'] ?? 'general',
        ]);
    }

    public function sendTestEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->mail->sendTestEmail($validated['email']);

        return ApiResponse::success(['sent' => true]);
    }
}
