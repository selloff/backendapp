<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\User\Models\VendorProfile;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can mark bank transfer order paid and update line status', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $order = placeVendorOrder_in_PassPhase10Backlog();
    $item = $order->items()->firstOrFail();

    $order->update([
        'payment_method' => 'bank_transfer',
        'payment_status' => 'awaiting_payment',
        'status' => 'pending',
    ]);
    $item->update(['order_status' => 'pending']);

    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/orders/{$order->id}/mark-paid")
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'payment_received');

    $this->patchJson("/api/v1/admin/orders/{$order->id}/items/{$item->id}", [
        'order_status' => 'shipped',
    ])
        ->assertOk()
        ->assertJsonPath('data.order_status', 'shipped');
});

test('admin can reject payout and view seller account', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    VendorProfile::query()->where('user_id', $vendor->id)->update([
        'payout_info' => [
            'bank_name' => 'Demo Bank',
            'account_number' => '0123456789',
        ],
    ]);

    $payout = PayoutRequest::query()->where('seller_id', $vendor->id)->where('status', 'pending')->firstOrFail();

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/payouts?status=pending')
        ->assertOk()
        ->assertJsonFragment(['id' => $payout->id])
        ->assertJsonPath('data.data.0.seller_payout_account.bank_name', 'Demo Bank');

    $this->postJson("/api/v1/admin/payouts/{$payout->id}/reject", [
        'reason' => 'Invalid account details',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

test('admin can manage category hierarchy', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $parentId = $this->postJson('/api/v1/admin/categories', ['name' => 'Phase 10 Parent'])
        ->assertCreated()
        ->json('data.id');

    $childId = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Phase 10 Child',
        'parent_id' => $parentId,
    ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parentId)
        ->json('data.id');

    $this->putJson("/api/v1/admin/categories/{$childId}", [
        'name' => 'Phase 10 Child Updated',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Phase 10 Child Updated');

    $this->deleteJson("/api/v1/admin/categories/{$parentId}", [], adminPinHeaders())
        ->assertStatus(422);

    $this->deleteJson("/api/v1/admin/categories/{$childId}", [], adminPinHeaders())->assertOk();
    $this->deleteJson("/api/v1/admin/categories/{$parentId}", [], adminPinHeaders())->assertOk();
});

test('admin can manage location cities', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $state = State::query()->firstOrFail();

    $cityId = $this->postJson('/api/v1/admin/locations/cities', [
        'state_id' => $state->id,
        'name' => 'Phase 10 City',
    ])
        ->assertCreated()
        ->json('data.id');

    $this->putJson("/api/v1/admin/locations/cities/{$cityId}", [
        'name' => 'Phase 10 City Updated',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Phase 10 City Updated');

    $this->deleteJson("/api/v1/admin/locations/cities/{$cityId}", [], adminPinHeaders())->assertOk();
    $this->assertDatabaseMissing('cities', ['id' => $cityId]);
});

test('admin can create user with roles', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/users', [
        'first_name' => 'Phase',
        'last_name' => 'Ten',
        'email' => 'phase10-admin-created@selloff.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => ['vendor'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'phase10-admin-created@selloff.test');

    $created = User::query()->where('email', 'phase10-admin-created@selloff.test')->firstOrFail();
    expect($created->hasRole('vendor'))->toBeTrue();
});

function placeVendorOrder_in_PassPhase10Backlog(): Order
{
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $product = test()->postJson('/api/v1/products', [
        'title' => 'Phase 10 Order Item',
        'price' => 2500,
        'stock' => 2,
    ])->assertCreated()->json('data');

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    test()->postJson('/api/v1/cart/items', ['product_id' => $product['id'], 'quantity' => 1])->assertCreated();
    $checkout = test()->postJson('/api/v1/checkout', ['payment_method' => 'wallet_balance'])->assertCreated()->json('data');
    $placed = test()->postJson('/api/v1/checkout/wallet', ['checkout_token' => $checkout['checkout_token']])
        ->assertCreated()
        ->json('data');

    return Order::query()->findOrFail($placed['id']);
}
