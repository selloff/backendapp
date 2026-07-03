<?php

namespace App\Http\Middleware;

use App\Modules\Selloff\Admin\Services\AdminPinService;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdminPinForSettings
{
    /** @var list<string> */
    private const SETTINGS_GROUPS = [
        'general',
        'product_listing',
        'email',
        'social_login',
        'visual',
        'font',
        'payment',
    ];

    public function __construct(
        private readonly AdminPinService $adminPin,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->requiresSuperAdminPin($request)) {
            return $next($request);
        }

        if ($this->requiresSuperAdminRole($request)) {
            abort_unless($request->user()?->hasRole('super-admin'), 403);
        }

        $pin = (string) $request->header(AdminPinContext::HEADER_SUPER_ADMIN_PIN, '');

        if (! preg_match('/^\d{6}$/', $pin)) {
            return ApiResponse::error('Super Admin PIN is required for this action.', 422, errors: ['code' => 'SUPER_ADMIN_PIN_REQUIRED']);
        }

        try {
            $this->adminPin->verifySuperAdminPin($pin);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                422,
                errors: $exception->errors(),
            );
        }

        return $next($request);
    }

    private function requiresSuperAdminPin(Request $request): bool
    {
        $path = $request->path();
        $method = $request->method();

        if ($method === 'PUT' && $path === 'api/v1/settings') {
            return in_array((string) $request->input('group'), self::SETTINGS_GROUPS, true);
        }

        if ($method === 'POST' && $path === 'api/v1/admin/email/test') {
            return true;
        }

        if ($method === 'PUT' && (
            str_starts_with($path, 'api/v1/admin/payments/gateways')
            || $path === 'api/v1/admin/featured-pricing'
        )) {
            return true;
        }

        if (str_starts_with($path, 'api/v1/admin/tax-rules') && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return true;
        }

        if (str_starts_with($path, 'api/v1/admin/currencies') && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return true;
        }

        if ($method === 'POST' && $path === 'api/v1/admin/currencies/refresh-rates') {
            return true;
        }

        if (str_starts_with($path, 'api/v1/admin/languages') && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return true;
        }

        if ($method === 'PUT' && $path === 'api/v1/admin/routes') {
            return true;
        }

        if ($method === 'PUT' && $path === 'api/v1/admin/platform/cache') {
            return true;
        }

        if ($method === 'POST' && $path === 'api/v1/admin/platform/cache/reset') {
            return true;
        }

        if ($method === 'PUT' && $path === 'api/v1/admin/platform/preferences') {
            return true;
        }

        if ($method === 'PUT' && $path === 'api/v1/admin/platform/ai-writer') {
            return true;
        }

        if ($method === 'PUT' && $path === 'api/v1/admin/platform/storage') {
            return true;
        }

        if ($method === 'PUT' && $path === 'api/v1/admin/seo') {
            return true;
        }

        if ($method === 'POST' && $path === 'api/v1/admin/seo/sitemap/generate') {
            return true;
        }

        if ($method === 'PUT' && $path === 'api/v1/admin/theme') {
            return true;
        }

        if ($method === 'GET' && $path === 'api/v1/admin/database/backup') {
            return true;
        }

        return false;
    }

    private function requiresSuperAdminRole(Request $request): bool
    {
        return $request->method() === 'GET' && $request->path() === 'api/v1/admin/database/backup';
    }
}
