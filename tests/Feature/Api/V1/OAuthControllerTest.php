<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\User\Models\ReferralProfile;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class OAuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_oauth_redirect_returns_authorization_url_json(): void
    {
        config([
            'services.google.client_id' => 'test-google-client',
            'services.google.client_secret' => 'test-google-secret',
            'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
        ]);

        $response = $this->getJson('/api/v1/auth/oauth/google/redirect');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['url']]);

        $this->assertStringContainsString('accounts.google.com', (string) $response->json('data.url'));
    }

    public function test_oauth_redirect_browser_mode_redirects_away(): void
    {
        config([
            'services.google.client_id' => 'test-google-client',
            'services.google.client_secret' => 'test-google-secret',
            'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
        ]);

        $response = $this->get('/api/v1/auth/oauth/google/redirect?browser=1&return_to=/products');

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', (string) $response->headers->get('Location'));
    }

    public function test_platform_brand_exposes_social_login_flags(): void
    {
        $response = $this->getJson('/api/v1/public/platform-brand');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'social_login' => ['enabled', 'google', 'facebook', 'vkontakte'],
                ],
            ]);
    }

    public function test_platform_brand_enables_google_when_env_credentials_are_set(): void
    {
        config([
            'services.google.client_id' => 'env-google-client',
            'services.google.client_secret' => 'env-google-secret',
        ]);

        $response = $this->getJson('/api/v1/public/platform-brand');

        $response->assertOk()
            ->assertJsonPath('data.social_login.google', true);
    }

    public function test_google_oauth_callback_creates_user_and_redirects_to_spa_with_token(): void
    {
        config([
            'services.google.client_id' => 'test-google-client',
            'services.google.client_secret' => 'test-google-secret',
            'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
            'selloff.spa_url' => 'http://localhost:5173',
        ]);

        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-oauth-test-1',
            'name' => 'Google Tester',
            'email' => 'google.tester@selloff.test',
            'avatar' => 'https://example.com/avatar.jpg',
        ]));

        $state = base64_encode(json_encode([
            'return_to' => '/products',
            'nonce' => 'test-nonce',
        ]));

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->get("/api/v1/auth/oauth/google/callback?state={$state}");

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('http://localhost:5173/auth/oauth/callback', $location);
        $this->assertStringContainsString('token=', $location);
        $this->assertStringContainsString('return_to=%2Fproducts', $location);

        $this->assertDatabaseHas('users', [
            'email' => 'google.tester@selloff.test',
            'google_id' => 'google-oauth-test-1',
        ]);

        $user = User::query()->where('email', 'google.tester@selloff.test')->firstOrFail();
        $this->assertSame('https://example.com/avatar.jpg', $user->avatar);
        $this->assertSame('remote', $user->storage_avatar);
    }

    public function test_google_oauth_callback_links_existing_user_by_email(): void
    {
        config([
            'services.google.client_id' => 'test-google-client',
            'services.google.client_secret' => 'test-google-secret',
            'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
            'selloff.spa_url' => 'http://localhost:5173',
        ]);

        $existing = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-linked-42',
            'name' => $existing->first_name.' '.$existing->last_name,
            'email' => $existing->email,
        ]));

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->get('/api/v1/auth/oauth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('token=', (string) $response->headers->get('Location'));

        $existing->refresh();
        $this->assertSame('google-linked-42', $existing->google_id);
    }

    public function test_google_oauth_rejects_invalid_referral_code_before_user_creation(): void
    {
        config([
            'services.google.client_id' => 'test-google-client',
            'services.google.client_secret' => 'test-google-secret',
            'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
            'selloff.spa_url' => 'http://localhost:5173',
        ]);

        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-oauth-invalid-ref',
            'name' => 'Invalid Ref',
            'email' => 'invalid.ref.oauth@selloff.test',
        ]));

        $state = base64_encode(json_encode([
            'referral_code' => 'NOTREAL99',
            'nonce' => 'test-nonce',
        ]));

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->get("/api/v1/auth/oauth/google/callback?state={$state}");

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('error=oauth_failed', $location);

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid.ref.oauth@selloff.test',
        ]);
    }

    public function test_google_oauth_applies_valid_referral_code_on_signup(): void
    {
        config([
            'services.google.client_id' => 'test-google-client',
            'services.google.client_secret' => 'test-google-secret',
            'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
            'selloff.spa_url' => 'http://localhost:5173',
        ]);

        $referrer = User::factory()->create();
        ReferralProfile::query()->firstOrCreate(
            ['user_id' => $referrer->id],
            ['referral_code' => 'OAUTHREF', 'referral_points' => 0],
        );

        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-oauth-valid-ref',
            'name' => 'OAuth Referral',
            'email' => 'oauth.referral@selloff.test',
        ]));

        $state = base64_encode(json_encode([
            'referral_code' => 'OAUTHREF',
            'nonce' => 'test-nonce',
        ]));

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->get("/api/v1/auth/oauth/google/callback?state={$state}");

        $response->assertRedirect();

        $referred = User::query()->where('email', 'oauth.referral@selloff.test')->firstOrFail();
        $profile = ReferralProfile::query()->where('user_id', $referred->id)->firstOrFail();
        $this->assertSame($referrer->id, (int) $profile->referral_user_id);
        $this->assertSame(10, (int) ReferralProfile::query()->where('user_id', $referrer->id)->value('referral_points'));
    }

    public function test_google_oauth_callback_redirects_to_mobile_scheme_when_requested(): void
    {
        config([
            'services.google.client_id' => 'test-google-client',
            'services.google.client_secret' => 'test-google-secret',
            'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
            'selloff.spa_url' => 'http://localhost:5173',
            'selloff.mobile_oauth_redirect_scheme' => 'selloff',
        ]);

        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-mobile-oauth',
            'name' => 'Mobile OAuth',
            'email' => 'mobile.oauth@selloff.test',
        ]));

        $state = base64_encode(json_encode([
            'mobile' => true,
            'nonce' => 'test-nonce',
        ]));

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->get("/api/v1/auth/oauth/google/callback?state={$state}");

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('selloff://auth/oauth/callback?', $location);
        $this->assertStringContainsString('token=', $location);
    }
}
