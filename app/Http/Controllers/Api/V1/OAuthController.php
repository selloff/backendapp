<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MeResource;
use App\Models\User;
use App\Modules\Auth\Actions\BuildMeContextAction;
use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Selloff\Referral\Actions\ApplyReferralCodeOnRegisterAction;
use App\Modules\Selloff\Referral\Actions\AwardReferralPointsAction;
use App\Modules\Selloff\Referral\Actions\EnsureReferralProfileAction;
use App\Services\Auth\SocialLoginConfig;
use App\Services\Auth\WelcomeEmailService;
use App\Support\ApiResponse;
use App\Support\Gtm\AuthGtmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OAuthController extends Controller
{
    private const PROVIDERS = ['facebook', 'google', 'vkontakte'];

    public function redirect(
        string $provider,
        Request $request,
        SocialLoginConfig $socialLogin,
    ): JsonResponse|RedirectResponse {
        $this->assertProvider($provider);
        abort_unless($socialLogin->isEnabled(), Response::HTTP_FORBIDDEN, 'Social login is disabled.');
        $socialLogin->assertProviderConfigured($provider);
        $socialLogin->applyProviderConfig($provider);

        $state = $this->encodeOAuthState(
            $request->string('return_to')->toString(),
            $request->string('referral_code')->toString() ?: null,
            $request->boolean('mobile'),
        );

        $url = Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        if ($request->boolean('browser')) {
            return redirect()->away($url);
        }

        return ApiResponse::success(['url' => $url]);
    }

    public function callback(
        string $provider,
        Request $request,
        SocialLoginConfig $socialLogin,
        LoginUserAction $login,
        BuildMeContextAction $buildMe,
        AuthGtmService $gtm,
        EnsureReferralProfileAction $ensureReferralProfile,
        ApplyReferralCodeOnRegisterAction $applyReferralCode,
        AwardReferralPointsAction $awardReferralPoints,
        WelcomeEmailService $welcomeEmail,
    ): JsonResponse|RedirectResponse {
        $this->assertProvider($provider);
        abort_unless($socialLogin->isEnabled(), Response::HTTP_FORBIDDEN, 'Social login is disabled.');

        $state = $request->string('state')->toString();
        $returnTo = $this->decodeOAuthReturnTo($state);
        $referralCode = $this->decodeOAuthReferralCode($state);
        $mobile = $this->decodeOAuthMobile($state);

        try {
            $socialLogin->assertProviderConfigured($provider);
            $socialLogin->applyProviderConfig($provider);

            $socialUser = Socialite::driver($provider)->stateless()->user();
            $column = $this->providerColumn($provider);

            $user = User::query()->where($column, $socialUser->getId())->first();

            if ($user === null && $socialUser->getEmail()) {
                $user = User::query()->where('email', $socialUser->getEmail())->first();
            }

            $isNewUser = $user === null;

            if ($user === null) {
                $applyReferralCode->validateForNewRegistration($referralCode);
                $user = $this->createSocialUser($provider, $socialUser, $column);
                $ensureReferralProfile->execute($user);
                $applyReferralCode->execute($user, $referralCode);
                $awardReferralPoints->execute($user->fresh());
                $welcomeEmail->queue($user->fresh());
            }

            if ($user->is_disable || $user->is_banned) {
                throw ValidationException::withMessages([
                    'email' => ['This account is disabled.'],
                ]);
            }

            $user->forceFill([
                $column => $socialUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if ($isNewUser && filled($socialUser->getAvatar())) {
                $user->forceFill([
                    'avatar' => (string) $socialUser->getAvatar(),
                    'storage_avatar' => 'remote',
                ])->save();
            }

            $result = $login->issueToken($user->fresh(), 'spa', $request->ip(), $request->userAgent());
            $gtmEvents = $isNewUser
                ? $gtm->userSignup($result['user'], $request, $provider)
                : $gtm->userLogin($result['user'], $request, $provider);
            if ($mobile) {
                return redirect()->away($this->mobileOAuthCallbackUrl(array_filter([
                    'token' => $result['token'],
                    'return_to' => $returnTo,
                    'auth_gtm_event' => $isNewUser ? 'user_signup' : 'user_login',
                    'auth_channel' => $provider,
                ])));
            }

            $spaUrl = $this->spaUrl();

            if ($spaUrl !== null) {
                return redirect()->away($this->spaOAuthCallbackUrl($spaUrl, array_filter([
                    'token' => $result['token'],
                    'return_to' => $returnTo,
                    'auth_gtm_event' => $isNewUser ? 'user_signup' : 'user_login',
                    'auth_channel' => $provider,
                ])));
            }

            return ApiResponse::success([
                'token' => $result['token'],
                'me' => new MeResource($buildMe->execute($result['user'])),
                'return_to' => $returnTo,
                'gtm_events' => $gtmEvents,
            ]);
        } catch (Throwable $exception) {
            $failureMessage = $exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first()
                : ($exception->getMessage() !== '' ? $exception->getMessage() : 'Social login failed.');

            if ($mobile) {
                return redirect()->away($this->mobileOAuthCallbackUrl(array_filter([
                    'error' => 'oauth_failed',
                    'message' => $failureMessage,
                    'return_to' => $returnTo,
                ])));
            }

            $spaUrl = $this->spaUrl();
            if ($spaUrl !== null) {
                return redirect()->away($this->spaOAuthCallbackUrl($spaUrl, array_filter([
                    'error' => 'oauth_failed',
                    'message' => $failureMessage,
                    'return_to' => $returnTo,
                ])));
            }

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            return ApiResponse::error('Social login failed.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function createSocialUser(string $provider, $socialUser, string $column): User
    {
        $email = $socialUser->getEmail();

        if (! is_string($email) || $email === '') {
            throw ValidationException::withMessages([
                'email' => ['Social provider did not return an email address.'],
            ]);
        }

        $name = $this->splitName($socialUser->getName() ?: 'Member User');
        $slugBase = Str::slug(trim("{$name['first_name']}-{$name['last_name']}")) ?: Str::slug(Str::before($email, '@'));
        $slug = $slugBase;
        $suffix = 1;

        while (User::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-{$suffix}";
            $suffix++;
        }

        $user = User::query()->create([
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'slug' => $slug,
            'email' => $email,
            'password' => Hash::make(Str::password(24)),
            'is_enable_login' => true,
            'is_disable' => false,
            'email_verified_at' => now(),
            $column => $socialUser->getId(),
        ]);

        Role::findOrCreate('member', 'web');
        $user->assignRole('member');

        return $user->fresh();
    }

    private function spaUrl(): ?string
    {
        $spaUrl = config('selloff.spa_url');

        return is_string($spaUrl) && $spaUrl !== '' ? rtrim($spaUrl, '/') : null;
    }

    /**
     * @param  array<string, string|null>  $params
     */
    private function spaOAuthCallbackUrl(string $spaUrl, array $params): string
    {
        $query = http_build_query(array_filter($params, static fn ($value) => is_string($value) && $value !== ''));

        return "{$spaUrl}/auth/oauth/callback?{$query}";
    }

    /**
     * @param  array<string, string|null>  $params
     */
    private function mobileOAuthCallbackUrl(array $params): string
    {
        $scheme = config('selloff.mobile_oauth_redirect_scheme', 'selloff');
        $query = http_build_query(array_filter($params, static fn ($value) => is_string($value) && $value !== ''));

        return "{$scheme}://auth/oauth/callback?{$query}";
    }

    private function assertProvider(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404, 'Unsupported OAuth provider.');
    }

    private function providerColumn(string $provider): string
    {
        return match ($provider) {
            'facebook' => 'facebook_id',
            'google' => 'google_id',
            'vkontakte' => 'vk_id',
        };
    }

    /**
     * @return array{first_name: string, last_name: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: ['Member', 'User'];

        return [
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? 'User',
        ];
    }

    private function encodeOAuthState(
        ?string $returnTo,
        ?string $referralCode = null,
        bool $mobile = false,
    ): string {
        return base64_encode(json_encode([
            'return_to' => $this->sanitizeReturnTo($returnTo),
            'referral_code' => $this->normalizeReferralCode($referralCode),
            'mobile' => $mobile,
            'nonce' => Str::random(32),
        ], JSON_THROW_ON_ERROR));
    }

    private function decodeOAuthReferralCode(?string $state): ?string
    {
        if (! filled($state)) {
            return null;
        }

        try {
            $decoded = json_decode(base64_decode($state, true) ?: '', true, 512, JSON_THROW_ON_ERROR);

            return $this->normalizeReferralCode(is_array($decoded) ? ($decoded['referral_code'] ?? null) : null);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeReferralCode(mixed $referralCode): ?string
    {
        if (! is_string($referralCode) || trim($referralCode) === '') {
            return null;
        }

        return strtoupper(trim($referralCode));
    }

    private function decodeOAuthReturnTo(?string $state): ?string
    {
        if (! filled($state)) {
            return null;
        }

        try {
            $decoded = json_decode(base64_decode($state, true) ?: '', true, 512, JSON_THROW_ON_ERROR);

            return $this->sanitizeReturnTo(is_array($decoded) ? ($decoded['return_to'] ?? null) : null);
        } catch (\Throwable) {
            return null;
        }
    }

    private function decodeOAuthMobile(?string $state): bool
    {
        if (! filled($state)) {
            return false;
        }

        try {
            $decoded = json_decode(base64_decode($state, true) ?: '', true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) && ($decoded['mobile'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function sanitizeReturnTo(mixed $returnTo): ?string
    {
        if (! is_string($returnTo) || $returnTo === '') {
            return null;
        }

        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return null;
        }

        return $returnTo;
    }
}
