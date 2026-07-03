<?php

namespace App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Content\Actions\UpdateAdSpaceAction;
use App\Modules\Selloff\Content\Models\AdSpace;
use App\Modules\Selloff\Content\Support\AdSpaceSlotRegistry;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAdSpaceController extends Controller
{
    public function __construct(
        private readonly UpdateAdSpaceAction $updateAdSpace,
        private readonly PlatformSettingsService $settings,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(AdSpace::query()->orderBy('ad_space_key')->get());
    }

    public function showByKey(string $key): JsonResponse
    {
        if (! AdSpaceSlotRegistry::hasLegacySlot($key)) {
            return ApiResponse::error('Unknown ad space.', 404);
        }

        $adSpace = AdSpace::query()->firstOrCreate(
            ['ad_space_key' => $key],
            AdSpaceSlotRegistry::createDefaults($key),
        );

        return ApiResponse::success($adSpace);
    }

    public function adsense(): JsonResponse
    {
        return ApiResponse::success([
            'google_adsense_code' => (string) ($this->settings->get('google_adsense_code') ?? ''),
        ]);
    }

    public function updateAdsense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'google_adsense_code' => ['nullable', 'string'],
        ]);

        $this->settings->upsertMany([
            'google_adsense_code' => $validated['google_adsense_code'] ?? '',
        ], 'general');

        return ApiResponse::success([
            'google_adsense_code' => $validated['google_adsense_code'] ?? '',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ad_space_key' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'ad_code' => ['nullable', 'string'],
            'ad_code_desktop' => ['nullable', 'string'],
            'ad_code_mobile' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'desktop_width' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'desktop_height' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'mobile_width' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'mobile_height' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $defaults = AdSpaceSlotRegistry::createDefaults($data['ad_space_key']);

        $adSpace = AdSpace::query()->create([
            ...$defaults,
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ApiResponse::success($adSpace, 201);
    }

    public function update(Request $request, AdSpace $adSpace): JsonResponse
    {
        if ($request->hasFile('file_ad_code_desktop') || $request->hasFile('file_ad_code_mobile')) {
            $validated = $request->validate([
                'ad_code_desktop' => ['nullable', 'string'],
                'ad_code_mobile' => ['nullable', 'string'],
                'desktop_width' => ['nullable', 'integer', 'min:1', 'max:5000'],
                'desktop_height' => ['nullable', 'integer', 'min:1', 'max:5000'],
                'mobile_width' => ['nullable', 'integer', 'min:1', 'max:5000'],
                'mobile_height' => ['nullable', 'integer', 'min:1', 'max:5000'],
                'url_ad_code_desktop' => ['nullable', 'string', 'max:500'],
                'url_ad_code_mobile' => ['nullable', 'string', 'max:500'],
                'file_ad_code_desktop' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
                'file_ad_code_mobile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            ]);

            $adSpace = $this->updateAdSpace->execute($adSpace, [
                ...$validated,
                'file_ad_code_desktop' => $request->file('file_ad_code_desktop'),
                'file_ad_code_mobile' => $request->file('file_ad_code_mobile'),
            ]);

            return ApiResponse::success($adSpace);
        }

        $data = $request->validate([
            'ad_space_key' => ['sometimes', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'ad_code' => ['nullable', 'string'],
            'ad_code_desktop' => ['nullable', 'string'],
            'ad_code_mobile' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'desktop_width' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'desktop_height' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'mobile_width' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'mobile_height' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        if (array_key_exists('ad_code_desktop', $data)) {
            $data['ad_code'] = $data['ad_code_desktop'];
        }

        $adSpace->update($data);

        return ApiResponse::success($adSpace->fresh());
    }

    public function destroy(AdSpace $adSpace): JsonResponse
    {
        $adSpace->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
