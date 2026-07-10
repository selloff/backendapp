<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\CustomFieldOption;
use App\Modules\Selloff\Catalog\Models\Product;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('category children returns subcategories for phones and tablets', function () {
    $parent = Category::query()->where('slug', 'phones-and-tablets')->firstOrFail();

    $this->getJson("/api/v1/categories/{$parent->id}/children")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data');

    $slugs = collect($this->getJson("/api/v1/categories/{$parent->id}/children")->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('smartphones');
    expect($slugs)->toContain('tablets');
});

test('custom fields by category returns condition field for smartphones', function () {
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();

    $this->getJson("/api/v1/customfields/custom-fields-by-category-all-data-new/{$category->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.label', 'Condition')
        ->assertJsonPath('data.0.field_type', 'single_select');
});

test('custom fields by category includes parent category fields', function () {
    $parent = Category::query()->where('slug', 'phones-and-tablets')->firstOrFail();
    $child = Category::query()->where('slug', 'smartphones')->firstOrFail();

    $parentField = CustomField::query()->create([
        'field_type' => 'multi_select',
        'label' => 'Parent only feature',
        'is_required' => false,
        'status' => true,
        'field_order' => 99,
    ]);
    $parentField->categories()->sync([$parent->id]);

    $option = $parentField->options()->create([
        'option_key' => 'feature-a',
        'label' => 'Feature A',
    ]);

    $response = $this->getJson("/api/v1/customfields/custom-fields-by-category-all-data-new/{$child->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $labels = collect($response->json('data'))->pluck('label')->all();
    expect($labels)->toContain('Parent only feature');

    $field = collect($response->json('data'))->firstWhere('label', 'Parent only feature');
    expect($field['field_type'])->toBe('multi_select');
    expect($field['field_options'][0]['id'])->toBe($option->id);
});

test('vendor can save custom fields on product update', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $field = CustomField::query()->where('label', 'Condition')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'custom_fields' => [
            ['custom_field_id' => $field->id, 'field_value' => 'Like new'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->getJson("/api/v1/vendor/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.custom_fields.0.custom_field_id', $field->id)
        ->assertJsonPath('data.custom_fields.0.field_value', 'Like new');
});

test('public product detail returns custom field values', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $field = CustomField::query()->where('label', 'Condition')->firstOrFail();
    $field->update(['where_to_display' => 2]);
    $likeNew = CustomFieldOption::query()
        ->where('custom_field_id', $field->id)
        ->where('option_key', 'like_new')
        ->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'custom_fields' => [
            ['custom_field_id' => $field->id, 'custom_field_option_id' => $likeNew->id],
        ],
    ])->assertOk();

    $this->getJson('/api/v1/products/'.$product->slug)
        ->assertOk()
        ->assertJsonPath('data.custom_field_values.0.name', 'Condition')
        ->assertJsonPath('data.custom_field_values.0.value', 'Like new')
        ->assertJsonPath('data.custom_field_values.0.where_to_display', 2);
});

test('ai writer returns stub text for vendor with permission', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany(['ai_writer_status' => true]);
    \Spatie\Permission\Models\Permission::findOrCreate('ai_writer', 'web');
    $vendor->givePermissionTo('ai_writer');

    $this->postJson('/api/v1/ai-writer/generate', [
        'topic' => 'Samsung Galaxy A54',
        'content_type' => 'product',
        'tone' => 'professional',
        'length' => 'medium',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.source', 'stub')
        ->assertJsonStructure(['data' => ['text', 'source']]);
});

test('homepage phone section resolves production slug', function () {
    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('success', true);

    $phones = collect($this->getJson('/api/v1/homepage')->json('data.sections'))->firstWhere('key', 'phones');
    expect($phones)->not->toBeNull();
    expect($phones['title'])->toBe('Latest Smartphones & Tablets');
    expect($phones['products'])->not->toBeEmpty();

    $parent = Category::query()->where('slug', 'phones-and-tablets')->firstOrFail();
    expect($phones['category_id'])->toBe($parent->id);
});
