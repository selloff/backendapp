<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Actions\BuildMeContextAction;
use App\Modules\Selloff\Admin\Services\AdminPinService;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use App\Services\Admin\AdminUserManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPinController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'required' => AdminPinContext::requiresAdminPin($user),
            'verified' => AdminPinContext::tokenIsVerified($user->currentAccessToken(), $user),
            'configured' => AdminPinContext::adminPinConfigured($user),
            'pin_type' => AdminPinContext::pinType($user),
        ]);
    }

    public function verifyLogin(
        Request $request,
        AdminPinService $adminPin,
        BuildMeContextAction $buildMe,
    ): JsonResponse {
        $data = $request->validate([
            'pin' => ['required', 'digits:6'],
        ]);

        $user = $request->user();
        $adminPin->verifyLoginPin($user, $data['pin']);

        $token = $user->currentAccessToken();
        if ($token !== null) {
            $token->forceFill(['abilities' => [AdminPinContext::ABILITY_VERIFIED]])->save();
        }

        return ApiResponse::success([
            'verified' => true,
            'me' => new \App\Http\Resources\Api\V1\MeResource($buildMe->execute($user->fresh())),
        ]);
    }

    public function setUserPin(
        Request $request,
        User $user,
        AdminPinService $adminPin,
        AdminUserManagementService $userManagement,
    ): JsonResponse {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $userManagement->guardSuperAdminTarget($user, $request->user());

        $data = $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed'],
        ]);

        $adminPin->setAdminPin($user, $data['pin']);

        return ApiResponse::success([
            'configured' => true,
            'revoked' => false,
        ]);
    }

    public function revokeUserPin(
        Request $request,
        User $user,
        AdminPinService $adminPin,
        AdminUserManagementService $userManagement,
    ): JsonResponse {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $userManagement->guardSuperAdminTarget($user, $request->user());

        if ($user->hasRole('super-admin')) {
            return ApiResponse::error('Super admin accounts use the global Super Admin PIN.', 422);
        }

        $adminPin->revokeAdminPin($user);

        return ApiResponse::success([
            'configured' => false,
            'revoked' => true,
        ]);
    }

    public function rotateSuperAdminPin(Request $request, AdminPinService $adminPin): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $data = $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed'],
            'current_pin' => ['required', 'digits:6'],
        ]);

        try {
            $adminPin->verifySuperAdminPin($data['current_pin']);
        } catch (\Illuminate\Validation\ValidationException) {
            return ApiResponse::error('Invalid current Super Admin PIN.', 422);
        }

        $adminPin->rotateSuperAdminPin($data['pin']);

        return ApiResponse::success(message: 'Super Admin PIN updated.');
    }

    public function showUserPinStatus(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        return ApiResponse::success([
            'configured' => $user->admin_pin_hash !== null && $user->admin_pin_revoked_at === null,
            'revoked' => $user->admin_pin_revoked_at !== null,
            'set_at' => $user->admin_pin_set_at?->toIso8601String(),
        ]);
    }
}
