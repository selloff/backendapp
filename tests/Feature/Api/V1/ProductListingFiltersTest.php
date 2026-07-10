<?php

use App\Models\PlatformSetting;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\CustomFieldOption;
use App\Modules\Selloff\Catalog\Models\CustomFieldProduct;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('category product filters returns filterable fields with counts', function () {
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();

    $field = CustomField::query()->where('label', 'Condition')->firstOrFail();

    $likeNew = CustomFieldOption::query()
        ->where('custom_field_id', $field->id)
        ->where('option_key', 'like_new')
        ->firstOrFail();
    $used = CustomFieldOption::query()
        ->where('custom_field_id', $field->id)
        ->where('option_key', 'used')
        ->firstOrFail();

    $productA = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $productB = Product::query()->where('sku', 'DEMO-PHONE-2')->firstOrFail();

    CustomFieldProduct::query()->whereIn('product_id', [$productA->id, $productB->id])->delete();

    CustomFieldProduct::query()->create([
        'product_id' => $productA->id,
        'custom_field_id' => $field->id,
        'custom_field_option_id' => $likeNew->id,
        'product_filter_key' => 'condition',
    ]);
    CustomFieldProduct::query()->create([
        'product_id' => $productB->id,
        'custom_field_id' => $field->id,
        'custom_field_option_id' => $used->id,
        'product_filter_key' => 'condition',
    ]);

    $response = $this->getJson("/api/v1/categories/{$category->id}/product-filters")
        ->assertOk()
        ->assertJsonPath('data.category_id', $category->id);

    $filters = collect($response->json('data.filters'));
    $condition = $filters->firstWhere('key', 'condition');
    expect($condition)->not->toBeNull();
    expect($condition['label'])->toBe('Condition');

    $likeNewOption = collect($condition['options'])->firstWhere('value', 'like_new');
    expect($likeNewOption)->not->toBeNull();
    expect($likeNewOption['count'])->toBeGreaterThanOrEqual(1);
});

test('products index filters by custom field option keys', function () {
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();

    $field = CustomField::query()->create([
        'field_type' => 'single_select',
        'label' => 'RAM',
        'is_required' => false,
        'status' => true,
        'is_product_filter' => true,
        'product_filter_key' => 'ram',
        'field_order' => 2,
    ]);
    $field->categories()->sync([$category->id]);

    $eightGb = CustomFieldOption::query()->create([
        'custom_field_id' => $field->id,
        'option_key' => '8gb',
        'label' => '8GB',
    ]);
    $sixteenGb = CustomFieldOption::query()->create([
        'custom_field_id' => $field->id,
        'option_key' => '16gb',
        'label' => '16GB',
    ]);

    $productA = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $productB = Product::query()->where('sku', 'DEMO-PHONE-2')->firstOrFail();

    CustomFieldProduct::query()->whereIn('product_id', [$productA->id, $productB->id])->delete();

    CustomFieldProduct::query()->create([
        'product_id' => $productA->id,
        'custom_field_id' => $field->id,
        'custom_field_option_id' => $eightGb->id,
        'product_filter_key' => 'ram',
    ]);
    CustomFieldProduct::query()->create([
        'product_id' => $productB->id,
        'custom_field_id' => $field->id,
        'custom_field_option_id' => $sixteenGb->id,
        'product_filter_key' => 'ram',
    ]);

    $this->getJson('/api/v1/products?category_id='.$category->id.'&ram=8gb')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'DEMO-PHONE-1'])
        ->assertJsonMissing(['sku' => 'DEMO-PHONE-2']);

    $this->getJson('/api/v1/products?category_id='.$category->id.'&ram=8gb,16gb')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'DEMO-PHONE-1'])
        ->assertJsonFragment(['sku' => 'DEMO-PHONE-2']);
});

test('products index supports multi brand filter', function () {
    PlatformSetting::query()->updateOrCreate(['key' => 'brand_status'], ['value' => true]);

    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    expect($product->brand_id)->not->toBeNull();

    $brandId = (int) $product->brand_id;

    $this->getJson('/api/v1/products?brand='.$brandId)
        ->assertOk()
        ->assertJsonFragment(['sku' => 'DEMO-PHONE-1']);
});

test('filter options endpoint supports search', function () {
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();

    $field = CustomField::query()->create([
        'field_type' => 'single_select',
        'label' => 'Storage',
        'is_required' => false,
        'status' => true,
        'is_product_filter' => true,
        'product_filter_key' => 'storage',
        'field_order' => 3,
    ]);
    $field->categories()->sync([$category->id]);

    $option = CustomFieldOption::query()->create([
        'custom_field_id' => $field->id,
        'option_key' => '256gb',
        'label' => '256GB SSD',
    ]);

    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    CustomFieldProduct::query()->create([
        'product_id' => $product->id,
        'custom_field_id' => $field->id,
        'custom_field_option_id' => $option->id,
        'product_filter_key' => 'storage',
    ]);

    $this->getJson("/api/v1/categories/{$category->id}/product-filters/storage/options?q=256")
        ->assertOk()
        ->assertJsonPath('data.options.0.value', '256gb');
});
