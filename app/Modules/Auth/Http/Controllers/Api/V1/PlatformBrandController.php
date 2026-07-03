<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialLoginConfig;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use App\Support\MediaUrl;
use App\Support\PlatformSettingsPublicFilter;
use Illuminate\Http\JsonResponse;

class PlatformBrandController extends Controller
{
    public function show(PlatformSettingsService $settings, SocialLoginConfig $socialLogin): JsonResponse
    {
        $platform = $settings->all();

        return ApiResponse::success([
            'platform_settings' => PlatformSettingsPublicFilter::filter($platform),
            'social_login' => $socialLogin->flags(),
            'oauth_redirect_uris' => $socialLogin->redirectUris(),
            'oauth_redirect_warnings' => array_filter([
                'google' => $socialLogin->redirectUriWarning('google'),
            ]),
            'image_url_prefix' => MediaUrl::prefixForSettings($platform),
            'is_demo' => (bool) config('app.run_demo_seeder', false),
        ]);
    }
}
