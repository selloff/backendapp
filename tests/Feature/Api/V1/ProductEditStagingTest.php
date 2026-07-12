<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor moderated edit stages pending changes and keeps approved live content', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'approve_after_editing' => 2,
    ]);

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createPublishedProductForEditStaging($vendor, [
        'price' => 25000,
        'title' => 'Approved phone listing',
        'description' => 'Approved description',
    ]);

    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'title' => 'Scam replacement title',
        'description' => 'Scam replacement description',
        'price' => 18000,
    ])
        ->assertOk()
        ->assertJsonPath('data.is_edited', true)
        ->assertJsonPath('data.has_pending_changes', true)
        ->assertJsonPath('data.title', 'Approved phone listing')
        ->assertJsonPath('data.price', '25000.00')
        ->assertJsonPath('data.pending_changes.title', 'Scam replacement title')
        ->assertJsonPath('data.pending_changes.price', '18000.00');

    $product->refresh();

    expect($product->is_edited)->toBeTrue()
        ->and($product->status)->toBe('published')
        ->and($product->is_active)->toBeTrue()
        ->and((string) $product->price)->toBe('25000.00')
        ->and($product->pending_changes['title'] ?? null)->toBe('Scam replacement title');

    $translation = ProductTranslation::query()
        ->where('product_id', $product->id)
        ->where('locale', 'en')
        ->firstOrFail();

    expect($translation->title)->toBe('Approved phone listing');
});

test('edited product remains publicly visible with approved content under approve after editing mode 2', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'approve_after_editing' => 2,
    ]);

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createPublishedProductForEditStaging($vendor, [
        'slug' => 'public-approved-edit-test',
        'price' => 30000,
        'title' => 'Public approved title',
    ]);

    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'title' => 'Hidden pending title',
        'price' => 100,
    ])->assertOk();

    $this->getJson('/api/v1/products/public-approved-edit-test')
        ->assertOk()
        ->assertJsonPath('data.title', 'Public approved title')
        ->assertJsonPath('data.price', '30000.00');
});

test('admin approve merges pending changes into live listing and clears pending state', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'approve_after_editing' => 2,
    ]);

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $product = createPublishedProductForEditStaging($vendor, [
        'slug' => 'approve-pending-edit-test',
        'price' => 25000,
        'title' => 'Original approved title',
    ]);

    Sanctum::actingAs($vendor);
    $this->putJson("/api/v1/products/{$product->id}", [
        'title' => 'Updated approved title',
        'price' => 28000,
    ])->assertOk();

    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/products/{$product->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated approved title')
        ->assertJsonPath('data.price', '28000.00')
        ->assertJsonPath('data.is_edited', false)
        ->assertJsonPath('data.has_pending_changes', false);

    $product->refresh();

    expect($product->pending_changes)->toBeNull()
        ->and($product->approved_snapshot['title'] ?? null)->toBe('Updated approved title')
        ->and((string) $product->price)->toBe('28000.00');
});

test('admin reject edited changes keeps listing live and queues vendor notification email', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'approve_after_editing' => 2,
    ]);

    EmailJob::query()->delete();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $product = createPublishedProductForEditStaging($vendor, [
        'slug' => 'reject-pending-edit-test',
        'price' => 25000,
        'title' => 'Keep this title',
    ]);

    Sanctum::actingAs($vendor);
    $this->putJson("/api/v1/products/{$product->id}", [
        'title' => 'Reject this title',
        'price' => 500,
    ])->assertOk();

    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/products/{$product->id}/reject", [
        'reason' => 'Price change is not acceptable.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_edited', false)
        ->assertJsonPath('data.title', 'Keep this title')
        ->assertJsonPath('data.price', '25000.00');

    $product->refresh();

    expect($product->pending_changes)->toBeNull()
        ->and($product->reject_reason)->toBeNull()
        ->and($product->last_edit_reject_reason)->toBe('Price change is not acceptable.');

    expect(
        EmailJob::query()
            ->where('email_type', TransactionalEmailType::PRODUCT_REJECTED)
            ->where('to_email', $vendor->email)
            ->exists(),
    )->toBeTrue();
});

test('vendor inbox includes edit rejected notification after admin rejects pending changes', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'approve_after_editing' => 2,
    ]);

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $product = createPublishedProductForEditStaging($vendor, [
        'price' => 25000,
        'title' => 'Notification reject title',
    ]);

    Sanctum::actingAs($vendor);
    $this->putJson("/api/v1/products/{$product->id}", [
        'title' => 'Rejected notification title',
    ])->assertOk();

    Sanctum::actingAs($admin);
    $this->postJson("/api/v1/admin/products/{$product->id}/reject", [
        'reason' => 'Title change is misleading.',
    ])->assertOk();

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/notifications/inbox')
        ->assertOk()
        ->assertJsonFragment(['type' => 'product_edit_rejected'])
        ->assertJsonFragment(['key' => "product_edit_rejected:{$product->id}"]);
});

test('admin product detail exposes moderation diff for edited listings', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'approve_after_editing' => 2,
    ]);

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $product = createPublishedProductForEditStaging($vendor, [
        'price' => 25000,
        'title' => 'Before title',
        'description' => 'Before description',
    ]);

    Sanctum::actingAs($vendor);
    $this->putJson("/api/v1/products/{$product->id}", [
        'title' => 'After title',
        'description' => 'After description',
        'price' => 18000,
    ])->assertOk();

    Sanctum::actingAs($admin);

    $this->getJson("/api/v1/admin/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.moderation_diff.approved.title', 'Before title')
        ->assertJsonPath('data.moderation_diff.pending.title', 'After title')
        ->assertJsonFragment(['changed_fields' => ['title', 'description', 'price']]);
});

test('backfill product approved snapshots command seeds published verified products', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createPublishedProductForEditStaging($vendor, [
        'price' => 41000,
        'title' => 'Backfill title',
        'description' => 'Backfill description',
    ]);

    expect($product->approved_snapshot)->toBeNull();

    $this->artisan('selloff:backfill-product-approved-snapshots')->assertSuccessful();

    $product->refresh();

    expect($product->approved_snapshot['title'] ?? null)->toBe('Backfill title')
        ->and($product->approved_snapshot['price'] ?? null)->toBe('41000.00');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function createPublishedProductForEditStaging(User $vendor, array $overrides = []): Product
{
    $title = (string) ($overrides['title'] ?? 'Edit staging test item');
    $description = (string) ($overrides['description'] ?? 'Edit staging description');
    unset($overrides['title'], $overrides['description']);

    /** @var Product $product */
    $product = Product::query()->create(array_merge([
        'vendor_id' => $vendor->id,
        'category_id' => Product::query()->value('category_id'),
        'slug' => 'edit-staging-'.uniqid(),
        'sku' => 'EDIT-STAGING-'.uniqid(),
        'type' => 'physical',
        'listing_type' => 'ordinary_listing',
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'is_sold' => false,
        'price' => 25000,
        'currency_code' => 'NGN',
        'stock' => 5,
    ], $overrides));

    ProductTranslation::query()->create([
        'product_id' => $product->id,
        'locale' => 'en',
        'title' => $title,
        'description' => $description,
    ]);

    return $product->fresh(['translations']);
}
