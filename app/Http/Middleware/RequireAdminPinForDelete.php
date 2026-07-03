<?php

namespace App\Http\Middleware;

use App\Modules\Selloff\Admin\Services\AdminPinService;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminPinForDelete
{
    public function __construct(
        private readonly AdminPinService $adminPin,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->requiresDeletePin($request)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $pin = (string) $request->header(AdminPinContext::HEADER_ADMIN_PIN, '');

        if (! preg_match('/^\d{6}$/', $pin)) {
            return ApiResponse::error('Admin PIN is required for this action.', 422, errors: ['code' => 'ADMIN_PIN_REQUIRED']);
        }

        try {
            $this->adminPin->verifyDeletePin($user, $pin);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                422,
                errors: $exception->errors(),
            );
        }

        return $next($request);
    }

    private function requiresDeletePin(Request $request): bool
    {
        if (! $this->isAdminScopedPath($request)) {
            return false;
        }

        $path = $request->path();

        if ($request->isMethod('DELETE')) {
            if ($this->isPinManagementPath($path)) {
                return false;
            }

            return true;
        }

        if (! $request->isMethod('POST')) {
            return false;
        }

        if (str_ends_with($path, 'admin/reviews/bulk-delete')) {
            return true;
        }

        if (str_ends_with($path, 'admin/products/bulk')) {
            return in_array($request->input('action'), ['delete', 'delete_permanently'], true);
        }

        if (str_ends_with($path, 'admin/comments/bulk') || str_ends_with($path, 'admin/cms/blog/comments/bulk')) {
            return $request->input('action') === 'delete';
        }

        return false;
    }

    private function isPinManagementPath(string $path): bool
    {
        return preg_match('#admin/users/\d+/admin-pin$#', $path) === 1
            || str_ends_with($path, 'admin/security/super-admin-pin');
    }

    private function isAdminScopedPath(Request $request): bool
    {
        return $request->is(
            'api/v1/admin/*',
            'api/v1/users/*',
            'api/v1/roles/*',
        );
    }
}
