<?php

namespace App\Http\Middleware;

use App\Modules\Selloff\Admin\Support\AdminPinContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPinVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! AdminPinContext::requiresAdminPin($user)) {
            return $next($request);
        }

        if ($request->is('api/v1/auth/admin-pin/*')) {
            return $next($request);
        }

        if (AdminPinContext::tokenIsVerified($user->currentAccessToken(), $user)) {
            return $next($request);
        }

        return ApiResponse::error('Admin PIN verification required.', 403, errors: ['code' => 'ADMIN_PIN_REQUIRED']);
    }
}
