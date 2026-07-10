<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Models\RouteSlug;
use App\Modules\Selloff\Payment\Models\TaxRule;
use App\Modules\Selloff\User\Models\LoginActivity;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('login records activity and admin can list them', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'buyer@selloff.test',
        'password' => 'password',
    ])->assertOk();

    $this->assertDatabaseHas('login_activities', [
        'user_id' => User::query()->where('email', 'buyer@selloff.test')->value('id'),
    ]);

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/login-activities')
        ->assertOk()
        ->assertJsonStructure(['data' => ['data' => [['id', 'user_id', 'login_at']]]]);
});

test('admin can view and update user', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson("/api/v1/users/{$buyer->id}/summary")
        ->assertOk()
        ->assertJsonPath('data.user.email', 'buyer@selloff.test')
        ->assertJsonStructure([
            'data' => [
                'user',
                'display_username',
                'location',
                'default_currency',
                'stats' => ['orders_count', 'products_count', 'wallet_balance', 'number_of_sales', 'commission_debt'],
                'recent_login_activities',
            ],
        ]);

    $this->putJson("/api/v1/users/{$buyer->id}", [
        'first_name' => 'Updated',
        'last_name' => 'Buyer',
    ])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Updated');
});

test('admin can manage payment gateways and tax rules', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/payments/settings')
        ->assertOk()
        ->assertJsonStructure(['data' => ['payment_methods', 'gateway_settings', 'membership_plans']]);

    $this->putJson('/api/v1/admin/payments/gateways', [
        'wallet_enabled' => true,
        'bank_transfer_enabled' => false,
        'cash_on_delivery_enabled' => true,
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.gateway_settings.bank_transfer_enabled', false);

    $this->postJson('/api/v1/admin/tax-rules', [
        'name' => 'Phase 7 VAT',
        'rate' => 7.5,
        'status' => true,
    ], superAdminPinHeaders())->assertCreated();

    $rule = TaxRule::query()->where('name', 'Phase 7 VAT')->firstOrFail();

    $this->putJson("/api/v1/admin/tax-rules/{$rule->id}", [
        'rate' => 8,
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.rate', '8.0000');

    $this->deleteJson("/api/v1/admin/tax-rules/{$rule->id}", [], adminSettingsDeleteHeaders())
        ->assertOk();
});

test('admin can manage route slugs', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/routes')
        ->assertOk()
        ->assertJsonFragment(['route_key' => 'cart'])
        ->assertJsonCount(76, 'data');

    $route = RouteSlug::query()->where('route_key', 'cart')->firstOrFail();

    $this->putJson('/api/v1/admin/routes', [
        'routes' => [
            ['id' => $route->id, 'slug' => 'shopping-cart'],
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonFragment(['slug' => 'shopping-cart']);
});

test('messages support after message id filter', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/messages/send-new-conversation-message', [
        'receiver_id' => $vendor->id,
        'message' => 'Phase 7 polling test',
        'subject' => 'Test',
    ])->assertCreated();

    $conversations = $this->getJson('/api/v1/messages/latest-conversations')->assertOk()->json('data');
    expect($conversations)->not->toBeEmpty();

    $conversationId = $conversations[0]['id'];

    $this->postJson('/api/v1/messages/send-conversation-message', [
        'conversation_id' => $conversationId,
        'message' => 'Follow-up message',
    ])->assertCreated();

    $service = app(\App\Modules\Selloff\Messaging\Services\MessageService::class);
    $messages = $service->messagesForConversation($conversationId, $buyer);
    expect($messages->count())->toBeGreaterThanOrEqual(2);

    $firstId = (int) $messages->first()['id'];
    $filtered = $service->messagesForConversation($conversationId, $buyer, $firstId);
    expect($filtered)->toHaveCount($messages->count() - 1);

    $this->getJson("/api/v1/messages/{$conversationId}?after_message_id={$firstId}")
        ->assertOk()
        ->assertJsonCount($messages->count() - 1, 'data');
});
