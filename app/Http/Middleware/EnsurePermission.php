<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $permissions = array_map('trim', explode('|', $permission));
        $allowed = collect($permissions)->contains(fn (string $slug) => $user->can($slug));
        abort_unless($allowed, 403, 'Forbidden.');

        return $next($request);
    }
}
