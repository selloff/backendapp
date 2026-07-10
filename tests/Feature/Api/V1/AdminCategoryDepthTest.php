<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can read and update category settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/categories/settings')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'sort_categories',
                'sort_parent_categories_by_order',
            ],
        ]);

    $this->putJson('/api/v1/admin/categories/settings', [
        'sort_categories' => 'alphabetically',
        'sort_parent_categories_by_order' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.sort_categories', 'alphabetically')
        ->assertJsonPath('data.sort_parent_categories_by_order', false);

    $this->getJson('/api/v1/admin/categories/settings')
        ->assertOk()
        ->assertJsonPath('data.sort_categories', 'alphabetically')
        ->assertJsonPath('data.sort_parent_categories_by_order', false);
});

test('admin can rebuild category paths', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $rootId = $this->postJson('/api/v1/admin/categories', ['name' => 'Depth Root'])
        ->assertCreated()
        ->json('data.id');

    $childId = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Depth Child',
        'parent_id' => $rootId,
    ])
        ->assertCreated()
        ->json('data.id');

    DB::table('category_paths')->where('category_id', $childId)->delete();

    $this->assertDatabaseMissing('category_paths', [
        'category_id' => $childId,
        'ancestor_id' => $rootId,
    ]);

    $this->postJson('/api/v1/admin/categories/rebuild-paths')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('category_paths', [
        'category_id' => $childId,
        'ancestor_id' => $childId,
        'depth' => 0,
    ]);
    $this->assertDatabaseHas('category_paths', [
        'category_id' => $childId,
        'ancestor_id' => $rootId,
        'depth' => 1,
    ]);
});

test('admin can search categories with pagination', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/categories', ['name' => 'DepthSearchAlpha'])->assertCreated();
    $this->postJson('/api/v1/admin/categories', ['name' => 'DepthSearchBeta'])->assertCreated();

    $response = $this->getJson('/api/v1/admin/categories?q=DepthSearch&per_page=1&page=1')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
                'per_page',
                'current_page',
                'last_page',
            ],
        ]);

    expect($response->json('data.per_page'))->toBe(1);
    expect($response->json('data.current_page'))->toBe(1);
    expect($response->json('data.total'))->toBeGreaterThanOrEqual(2);
    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.name'))->toStartWith('DepthSearch');
});

test('admin show category includes parent chain', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $rootId = $this->postJson('/api/v1/admin/categories', ['name' => 'Depth Chain Root'])
        ->assertCreated()
        ->json('data.id');

    $childId = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Depth Chain Child',
        'parent_id' => $rootId,
    ])
        ->assertCreated()
        ->json('data.id');

    $grandchildId = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Depth Chain Grandchild',
        'parent_id' => $childId,
    ])
        ->assertCreated()
        ->json('data.id');

    $this->getJson("/api/v1/admin/categories/{$grandchildId}")
        ->assertOk()
        ->assertJsonPath('data.id', $grandchildId)
        ->assertJsonPath('data.parent_id', $childId)
        ->assertJsonPath('data.parent_chain', [$rootId, $childId]);
});

test('admin flat categories returns all categories', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $beforeCount = Category::query()->count();

    $this->postJson('/api/v1/admin/categories', ['name' => 'Depth Flat One'])->assertCreated();
    $this->postJson('/api/v1/admin/categories', ['name' => 'Depth Flat Two'])->assertCreated();

    $response = $this->getJson('/api/v1/admin/categories?flat=1')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('data'))->toHaveCount($beforeCount + 2);
    expect(collect($response->json('data'))->pluck('name')->all())->toContain('Depth Flat One');
    expect(collect($response->json('data'))->pluck('name')->all())->toContain('Depth Flat Two');
});

test('admin can save category image and seo meta', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $categoryId = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Depth SEO Cat',
        'image_path' => 'category/202604/category_test.webp',
        'storage' => 'public',
        'meta_title' => 'SEO Title',
        'meta_description' => 'SEO Description',
        'meta_keywords' => 'seo, category',
    ])
        ->assertCreated()
        ->assertJsonPath('data.meta_title', 'SEO Title')
        ->assertJsonPath('data.image_path', 'category/202604/category_test.webp')
        ->json('data.id');

    $this->putJson("/api/v1/admin/categories/{$categoryId}", [
        'meta_title' => 'Updated SEO Title',
        'image_path' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Updated SEO Title')
        ->assertJsonPath('data.image_path', null);

    $this->assertDatabaseHas('category_translations', [
        'category_id' => $categoryId,
        'locale' => 'en',
        'meta_title' => 'Updated SEO Title',
    ]);
});

test('admin can reorder sibling categories', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $firstId = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Depth Reorder A',
        'category_order' => 1,
    ])->assertCreated()->json('data.id');

    $secondId = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Depth Reorder B',
        'category_order' => 2,
    ])->assertCreated()->json('data.id');

    $this->putJson('/api/v1/admin/categories/reorder', [
        'parent_id' => null,
        'category_ids' => [$secondId, $firstId],
    ])
        ->assertOk()
        ->assertJsonPath('data.reordered', 2);

    $this->assertDatabaseHas('categories', ['id' => $secondId, 'category_order' => 1]);
    $this->assertDatabaseHas('categories', ['id' => $firstId, 'category_order' => 2]);
});
