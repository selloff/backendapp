<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MeResource;
use App\Modules\Auth\Actions\BuildMeContextAction;
use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Auth\Actions\RegisterUserAction;
use App\Modules\Auth\Actions\ResetUserPasswordAction;
use App\Modules\Auth\Actions\SendPasswordResetLinkAction;
use App\Modules\Auth\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\Api\V1\LoginRequest;
use App\Modules\Auth\Http\Requests\Api\V1\RegisterRequest;
use App\Modules\Auth\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Services\Auth\EmailVerificationService;
use App\Support\ApiResponse;
use App\Support\Gtm\AuthGtmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function login(
        LoginRequest $request,
        LoginUserAction $login,
        BuildMeContextAction $buildMe,
        AuthGtmService $gtm,
    ): JsonResponse {
        $result = $login->execute(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name', 'spa'),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'me' => new MeResource($buildMe->execute($result['user'])),
            'gtm_events' => $gtm->userLogin($result['user'], $request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(message: 'Logged out.');
    }

    public function register(
        RegisterRequest $request,
        RegisterUserAction $register,
        BuildMeContextAction $buildMe,
        AuthGtmService $gtm,
    ): JsonResponse {
        $result = $register->execute(
            $request->validated('first_name'),
            $request->validated('last_name'),
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name', 'spa'),
            $request->validated('phone_number'),
            $request->validated('referral_code'),
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'me' => new MeResource($buildMe->execute($result['user'])),
            'email_verification_required' => $result['email_verification_required'] ?? false,
            'gtm_events' => $gtm->userSignup($result['user'], $request),
        ], 201);
    }

    public function verifyEmail(
        string $token,
        EmailVerificationService $verification,
        BuildMeContextAction $buildMe,
        \App\Modules\Selloff\Referral\Actions\AwardReferralPointsAction $awardReferralPoints,
    ): JsonResponse {
        $user = $verification->verify($token);
        $awardReferralPoints->execute($user);

        return ApiResponse::success([
            'me' => new MeResource($buildMe->execute($user)),
        ], 200, 'Email verified successfully.');
    }

    public function resendVerificationEmail(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return ApiResponse::success(message: 'Email is already verified.');
        }

        $token = $verification->issueToken($user);
        $verification->queueVerificationEmail($user, $token);

        return ApiResponse::success(message: 'Verification email sent.');
    }

    public function forgotPassword(
        ForgotPasswordRequest $request,
        SendPasswordResetLinkAction $sendLink,
    ): JsonResponse {
        $status = $sendLink->execute($request->validated('email'));

        if ($status === SendPasswordResetLinkAction::STATUS_MAIL_FAILED) {
            return ApiResponse::error(
                'We could not send the reset email. Check API mail settings and try again later.',
                503,
            );
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return ApiResponse::error(__($status), 422);
        }

        return ApiResponse::success(message: __($status));
    }

    public function resetPassword(
        ResetPasswordRequest $request,
        ResetUserPasswordAction $reset,
    ): JsonResponse {
        $reset->execute(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('token'),
        );

        return ApiResponse::success(message: 'Password has been reset.');
    }
}
