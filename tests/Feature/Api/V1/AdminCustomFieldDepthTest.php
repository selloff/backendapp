<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\CustomFieldOption;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can attach and detach custom field category', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();
    Sanctum::actingAs($admin);

    $fieldId = $this->postJson('/api/v1/admin/catalog/custom-fields', [
        'field_type' => 'text',
        'label' => 'Depth CF Attach',
    ])
        ->assertCreated()
        ->json('data.id');

    $this->postJson("/api/v1/admin/catalog/custom-fields/{$fieldId}/categories/attach", [
        'category_id' => $category->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.category_ids', [$category->id]);

    $this->deleteJson("/api/v1/admin/catalog/custom-fields/{$fieldId}/categories/{$category->id}", [], adminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.category_ids', []);
});

test('admin can manage custom field options and sort settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $fieldId = $this->postJson('/api/v1/admin/catalog/custom-fields', [
        'field_type' => 'single_select',
        'label' => 'Depth CF Options',
    ])
        ->assertCreated()
        ->json('data.id');

    $optionId = $this->postJson("/api/v1/admin/catalog/custom-fields/{$fieldId}/options", [
        'option_key' => 'red',
        'label' => 'Red',
    ])
        ->assertCreated()
        ->json('data.id');

    $this->putJson("/api/v1/admin/catalog/custom-fields/{$fieldId}/options/{$optionId}", [
        'label' => 'Crimson',
        'option_key' => 'crimson',
    ])
        ->assertOk()
        ->assertJsonPath('data.label', 'Crimson');

    $this->putJson("/api/v1/admin/catalog/custom-fields/{$fieldId}", [
        'sort_options' => 'date_desc',
    ])
        ->assertOk()
        ->assertJsonPath('data.sort_options', 'date_desc');

    $this->deleteJson("/api/v1/admin/catalog/custom-fields/{$fieldId}/options/{$optionId}", [], adminPinHeaders())
        ->assertOk();

    $this->assertDatabaseMissing('custom_field_options', ['id' => $optionId]);
});

test('admin can search custom fields with pagination', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    CustomField::query()->create([
        'field_type' => 'text',
        'label' => 'DepthSearchAlpha',
        'field_order' => 1,
    ]);
    CustomField::query()->create([
        'field_type' => 'text',
        'label' => 'DepthSearchBeta',
        'field_order' => 2,
    ]);

    $response = $this->getJson('/api/v1/admin/catalog/custom-fields?q=DepthSearch&per_page=15&page=1')
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

    expect($response->json('data.per_page'))->toBe(15);
    expect($response->json('data.total'))->toBeGreaterThanOrEqual(2);
    expect(count($response->json('data.data')))->toBeGreaterThanOrEqual(2);
    expect(collect($response->json('data.data'))->every(
        fn (array $row) => str_starts_with((string) ($row['label'] ?? ''), 'DepthSearch'),
    ))->toBeTrue();
});

test('admin can toggle product filter on select field', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $fieldId = $this->postJson('/api/v1/admin/catalog/custom-fields', [
        'field_type' => 'multi_select',
        'label' => 'Depth CF Filter',
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_product_filter', false)
        ->json('data.id');

    $this->postJson("/api/v1/admin/catalog/custom-fields/{$fieldId}/toggle-product-filter")
        ->assertOk()
        ->assertJsonPath('data.is_product_filter', true);

    $textFieldId = $this->postJson('/api/v1/admin/catalog/custom-fields', [
        'field_type' => 'text',
        'label' => 'Depth CF Text',
    ])->json('data.id');

    $this->postJson("/api/v1/admin/catalog/custom-fields/{$textFieldId}/toggle-product-filter")
        ->assertStatus(422);
});

test('admin can bulk import custom fields from csv payload', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/admin/catalog/custom-fields/bulk', [
        'custom_fields' => [
            [
                'label' => 'Bulk Condition',
                'field_type' => 'single_select',
                'is_required' => true,
                'status' => true,
                'field_order' => 1,
                'is_product_filter' => true,
                'category_ids' => [$category->id],
                'options' => ['New', 'Used'],
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.created_count', 1);

    $fieldId = $response->json('data.created.0.id');
    $this->assertDatabaseHas('custom_fields', ['id' => $fieldId, 'label' => 'Bulk Condition']);
    $this->assertDatabaseHas('custom_field_options', ['custom_field_id' => $fieldId, 'label' => 'New']);
});
