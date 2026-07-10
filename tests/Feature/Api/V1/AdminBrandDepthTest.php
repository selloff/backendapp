<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Brand;
use App\Modules\Selloff\Catalog\Models\Category;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can show brand with categories and logo', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();
    Sanctum::actingAs($admin);

    $brandId = $this->postJson('/api/v1/admin/brands', [
        'name' => 'Depth Brand Show',
        'image_path' => 'blocks/202604/brand_test.webp',
        'storage' => 'public',
        'show_on_slider' => true,
        'category_ids' => [$category->id],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Depth Brand Show')
        ->assertJsonPath('data.image_path', 'blocks/202604/brand_test.webp')
        ->json('data.id');

    $this->getJson("/api/v1/admin/brands/{$brandId}")
        ->assertOk()
        ->assertJsonPath('data.category_ids', [$category->id])
        ->assertJsonPath('data.categories.0.id', $category->id);
});

test('admin can search brands with pagination', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    Brand::query()->create(['name' => 'DepthSearchAlpha Brand']);
    Brand::query()->create(['name' => 'DepthSearchBeta Brand']);

    $response = $this->getJson('/api/v1/admin/brands?q=DepthSearch&per_page=15&page=1')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
                'per_page',
                'current_page',
                'last_page',
            ],
        ]);

    expect($response->json('data.total'))->toBeGreaterThanOrEqual(2);
    expect(collect($response->json('data.data'))->every(
        fn (array $row) => str_contains((string) ($row['name'] ?? ''), 'DepthSearch'),
    ))->toBeTrue();
});

test('admin can update brand categories and clear logo', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $phones = Category::query()->where('slug', 'smartphones')->firstOrFail();
    $parentId = $phones->parent_id;
    $secondCategory = $parentId
        ? Category::query()->findOrFail($parentId)
        : Category::query()->where('id', '!=', $phones->id)->whereNull('parent_id')->firstOrFail();
    Sanctum::actingAs($admin);

    $brandId = $this->postJson('/api/v1/admin/brands', [
        'name' => 'Depth Brand Update',
        'image_path' => 'blocks/logo.webp',
        'category_ids' => [$phones->id],
    ])->json('data.id');

    $this->putJson("/api/v1/admin/brands/{$brandId}", [
        'category_ids' => [$phones->id, $secondCategory->id],
        'image_path' => null,
        'show_on_slider' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.image_path', null)
        ->assertJsonPath('data.show_on_slider', false)
        ->assertJsonCount(2, 'data.category_ids');

    $this->assertDatabaseHas('brand_category', [
        'brand_id' => $brandId,
        'category_id' => $secondCategory->id,
    ]);
});

test('admin can read and update brand settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/brands/settings')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['brand_status', 'is_brand_optional', 'brand_where_to_display'],
        ]);

    $this->putJson('/api/v1/admin/brands/settings', [
        'brand_status' => true,
        'is_brand_optional' => false,
        'brand_where_to_display' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.brand_status', true)
        ->assertJsonPath('data.is_brand_optional', false)
        ->assertJsonPath('data.brand_where_to_display', 1);
});
