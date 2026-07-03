<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Mobile\MobileRegisterRequest;
use App\Http\Resources\Api\V1\Mobile\MobileUserResource;
use App\Modules\Auth\Actions\BuildMeContextAction;
use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Auth\Actions\RegisterUserAction;
use App\Modules\Auth\Actions\SendPasswordResetLinkAction;
use App\Modules\Auth\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\Api\V1\LoginRequest;
use App\Services\Mobile\MobileUserCompatService;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(LoginRequest $request, LoginUserAction $login): JsonResponse
    {
        try {
            $result = $login->execute(
                $request->validated('email'),
                $request->validated('password'),
                $request->validated('device_name', 'mobile'),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (ValidationException $exception) {
            return MobileResponse::error(
                collect($exception->errors())->flatten()->first() ?? 'Invalid credentials.',
                401,
                $exception->errors(),
            );
        }

        return MobileResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new MobileUserResource($result['user']),
        ], 200, 'Login successful.');
    }

    public function register(
        MobileRegisterRequest $request,
        RegisterUserAction $register,
    ): JsonResponse {
        $names = $request->nameParts();

        $result = $register->execute(
            $names['first_name'],
            $names['last_name'],
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name', 'mobile'),
            $request->validated('phone_number'),
            $request->validated('referral_code') ?? $request->validated('referred_by_code'),
        );

        if ($request->filled('phone_number')) {
            $result['user']->update(['phone_number' => $request->validated('phone_number')]);
        }

        return MobileResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new MobileUserResource($result['user']->fresh()),
        ], 201, 'Signup successful.');
    }

    public function forgotPassword(
        ForgotPasswordRequest $request,
        SendPasswordResetLinkAction $sendLink,
    ): JsonResponse {
        $status = $sendLink->execute($request->validated('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return MobileResponse::error(__($status), 422);
        }

        return MobileResponse::success([], 200, __($status));
    }

    public function profile(Request $request, BuildMeContextAction $buildMe): JsonResponse
    {
        return MobileResponse::success([
            'user' => new MobileUserResource($buildMe->execute($request->user())),
        ], 200, 'Profile fetched.');
    }

    public function updateProfile(Request $request, MobileUserCompatService $users, BuildMeContextAction $buildMe): JsonResponse
    {
        try {
            $data = $request->validate([
                'fullname' => ['sometimes', 'string', 'max:255'],
                'first_name' => ['sometimes', 'string', 'max:100'],
                'last_name' => ['sometimes', 'string', 'max:100'],
                'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
                'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
                'avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
            ]);

            $user = $users->updateProfile($request->user(), $data);
        } catch (ValidationException $exception) {
            return MobileResponse::error(
                collect($exception->errors())->flatten()->first() ?? 'Validation failed.',
                422,
                $exception->errors(),
            );
        }

        return MobileResponse::success([
            'user' => new MobileUserResource($buildMe->execute($user)),
        ], 200, 'Profile updated.');
    }

    public function deleteAccount(Request $request, MobileUserCompatService $users): JsonResponse
    {
        try {
            $data = $request->validate([
                'password' => ['nullable', 'string'],
            ]);

            $users->deleteAccount($request->user(), $data['password'] ?? null);
        } catch (ValidationException $exception) {
            return MobileResponse::error(
                collect($exception->errors())->flatten()->first() ?? 'Could not delete account.',
                422,
                $exception->errors(),
            );
        }

        return MobileResponse::success([], 200, 'Account deleted.');
    }
}
