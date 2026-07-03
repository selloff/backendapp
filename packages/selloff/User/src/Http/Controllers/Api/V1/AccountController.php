<?php

namespace App\Modules\Selloff\User\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\User\Http\Requests\Api\V1\DeleteAccountRequest;
use App\Services\Mobile\MobileUserCompatService;
use App\Support\ApiResponse;
use App\Support\Gtm\AuthGtmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function requestDeletion(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->update(['account_delete_requested_at' => now()]);

        return ApiResponse::success(message: 'Account deletion request submitted.');
    }

    public function delete(DeleteAccountRequest $request, MobileUserCompatService $users, AuthGtmService $gtm): JsonResponse
    {
        $user = $request->user();
        $gtmEvents = $gtm->userChurn($user, $request);

        $users->deleteAccount($user, $request->validated('password'));

        return ApiResponse::success([
            'gtm_events' => $gtmEvents,
        ], message: 'Account deleted.');
    }
}
