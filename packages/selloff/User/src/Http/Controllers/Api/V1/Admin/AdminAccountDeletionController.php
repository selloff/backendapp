<?php

namespace App\Modules\Selloff\User\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserAdminResource;
use App\Models\User;
use App\Services\Mobile\MobileUserCompatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAccountDeletionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->input('show') ?: $request->integer('per_page', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $users = User::query()
            ->with('roles')
            ->whereNotNull('account_delete_requested_at')
            ->orderByDesc('account_delete_requested_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $users->through(fn (User $user) => new UserAdminResource($user));

        return ApiResponse::success($users);
    }

    public function cancel(User $user): JsonResponse
    {
        abort_unless($user->account_delete_requested_at !== null, 422, 'User has no pending deletion request.');

        $user->update(['account_delete_requested_at' => null]);

        return ApiResponse::success(new UserAdminResource($user->fresh()->load('roles')));
    }

    public function destroy(User $user, MobileUserCompatService $users): JsonResponse
    {
        abort_unless($user->account_delete_requested_at !== null, 422, 'User has no pending deletion request.');
        abort_if($user->hasRole('super-admin'), 422, 'Cannot delete the super-admin account.');

        $users->deleteAccount($user);
        $user->update(['account_delete_requested_at' => null]);

        return ApiResponse::success(message: 'Account deleted.');
    }
}
