<?php

namespace App\Modules\Selloff\User\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\User\Models\LoginActivity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLoginActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->input('show') ?: $request->integer('per_page', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $search = trim((string) ($request->input('q') ?: $request->input('search', '')));

        $activities = LoginActivity::query()
            ->with('user:id,first_name,last_name,email,slug,username')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('ip_address', 'like', $term)
                        ->orWhere('user_agent', 'like', $term)
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('email', 'like', $term)
                                ->orWhere('username', 'like', $term);
                        });
                });
            })
            ->orderByDesc('login_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $activities->getCollection()->transform(fn (LoginActivity $activity) => [
            'id' => $activity->id,
            'user_id' => $activity->user_id,
            'ip_address' => $activity->ip_address,
            'user_agent' => $activity->user_agent,
            'login_at' => $activity->login_at?->toIso8601String(),
            'user' => $activity->user ? [
                'id' => $activity->user->id,
                'first_name' => $activity->user->first_name,
                'last_name' => $activity->user->last_name,
                'email' => $activity->user->email,
                'slug' => $activity->user->slug,
                'username' => $activity->user->username,
            ] : null,
        ]);

        return ApiResponse::success($activities);
    }
}
