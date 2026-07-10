<?php

use App\Models\User;
use App\Modules\Selloff\User\Models\ReferralProfile;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('oauth redirect returns authorization url json', function () {
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
});

test('oauth redirect browser mode redirects away', function () {
    config([
        'services.google.client_id' => 'test-google-client',
        'services.google.client_secret' => 'test-google-secret',
        'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
    ]);

    $response = $this->get('/api/v1/auth/oauth/google/redirect?browser=1&return_to=/products');

    $response->assertRedirect();
    $this->assertStringContainsString('accounts.google.com', (string) $response->headers->get('Location'));
});

test('platform brand exposes social login flags', function () {
    $response = $this->getJson('/api/v1/public/platform-brand');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'social_login' => ['enabled', 'google', 'facebook', 'vkontakte'],
            ],
        ]);
});

test('platform brand enables google when env credentials are set', function () {
    config([
        'services.google.client_id' => 'env-google-client',
        'services.google.client_secret' => 'env-google-secret',
    ]);

    $response = $this->getJson('/api/v1/public/platform-brand');

    $response->assertOk()
        ->assertJsonPath('data.social_login.google', true);
});

test('google oauth callback creates user and redirects to spa with token', function () {
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
    expect($user->avatar)->toBe('https://example.com/avatar.jpg');
    expect($user->storage_avatar)->toBe('remote');
});

test('google oauth callback links existing user by email', function () {
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
    expect($existing->google_id)->toBe('google-linked-42');
});

test('google oauth rejects invalid referral code before user creation', function () {
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
});

test('google oauth applies valid referral code on signup', function () {
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
    expect((int) $profile->referral_user_id)->toBe($referrer->id);
    expect((int) ReferralProfile::query()->where('user_id', $referrer->id)->value('referral_points'))->toBe(10);
});

test('google oauth callback redirects to mobile scheme when requested', function () {
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
    expect($location)->toStartWith('selloff://auth/oauth/callback?');
    $this->assertStringContainsString('token=', $location);
});
