<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use App\Modules\Selloff\Referral\Services\ReferralProgramSettingsService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\MediaUrl;
use App\Support\PlatformSettingsPublicFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = app(PlatformSettingsService::class)->all();
        $imagePrefix = MediaUrl::prefixForSettings($settings);

        $this->resource->loadMissing('vendorProfile', 'referralProfile');
        $referralProgram = app(ReferralProgramSettingsService::class)->programSettings();
        $referralProfile = $this->resource->referralProfile;

        return [
            'user' => new UserResource($this->resource),
            'is_affiliate' => (int) ($this->resource->is_affiliate ?? 0),
            'referral_code' => $referralProfile?->referral_code,
            'referral_points' => (int) ($referralProfile?->referral_points ?? 0),
            'referral_program_enabled' => (bool) ($referralProgram['status'] ?? false),
            'social_media_data' => $this->resource->vendorProfile?->social_media_data
                ?? $this->resource->social_media_data,
            'cover_path' => $this->resource->vendorProfile?->cover_path,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'platform_settings' => PlatformSettingsPublicFilter::filter($settings),
            'image_url_prefix' => $imagePrefix,
            'is_demo' => (bool) config('app.run_demo_seeder', false),
            'admin_pin_required' => \App\Modules\Selloff\Admin\Support\AdminPinContext::requiresAdminPin($this->resource),
            'admin_pin_verified' => \App\Modules\Selloff\Admin\Support\AdminPinContext::tokenIsVerified(
                $request->user()?->currentAccessToken(),
                $this->resource,
            ),
            'admin_pin_configured' => \App\Modules\Selloff\Admin\Support\AdminPinContext::adminPinConfigured($this->resource),
            'admin_pin_type' => \App\Modules\Selloff\Admin\Support\AdminPinContext::pinType($this->resource),
        ];
    }
}
