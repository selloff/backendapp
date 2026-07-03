<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\Product;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Wave2SellerDepthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_category_children_returns_subcategories_for_phones_and_tablets(): void
    {
        $parent = Category::query()->where('slug', 'phones-and-tablets')->firstOrFail();

        $this->getJson("/api/v1/categories/{$parent->id}/children")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $slugs = collect($this->getJson("/api/v1/categories/{$parent->id}/children")->json('data'))->pluck('slug')->all();
        $this->assertContains('smartphones', $slugs);
        $this->assertContains('tablets', $slugs);
    }

    public function test_custom_fields_by_category_returns_condition_field_for_smartphones(): void
    {
        $category = Category::query()->where('slug', 'smartphones')->firstOrFail();

        $this->getJson("/api/v1/customfields/custom-fields-by-category-all-data-new/{$category->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.label', 'Condition')
            ->assertJsonPath('data.0.field_type', 'text');
    }

    public function test_custom_fields_by_category_includes_parent_category_fields(): void
    {
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
        $this->assertContains('Parent only feature', $labels);

        $field = collect($response->json('data'))->firstWhere('label', 'Parent only feature');
        $this->assertSame('multi_select', $field['field_type']);
        $this->assertSame($option->id, $field['field_options'][0]['id']);
    }

    public function test_vendor_can_save_custom_fields_on_product_update(): void
    {
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
    }

    public function test_public_product_detail_returns_custom_field_values(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $field = CustomField::query()->where('label', 'Condition')->firstOrFail();
        $field->update(['where_to_display' => 2]);
        Sanctum::actingAs($vendor);

        $this->putJson("/api/v1/products/{$product->id}", [
            'custom_fields' => [
                ['custom_field_id' => $field->id, 'field_value' => 'Like new'],
            ],
        ])->assertOk();

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.custom_field_values.0.name', 'Condition')
            ->assertJsonPath('data.custom_field_values.0.value', 'Like new')
            ->assertJsonPath('data.custom_field_values.0.where_to_display', 2);
    }

    public function test_ai_writer_returns_stub_text_for_vendor_with_permission(): void
    {
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
    }

    public function test_homepage_phone_section_resolves_production_slug(): void
    {
        $this->getJson('/api/v1/homepage')
            ->assertOk()
            ->assertJsonPath('success', true);

        $phones = collect($this->getJson('/api/v1/homepage')->json('data.sections'))->firstWhere('key', 'phones');
        $this->assertNotNull($phones);
        $this->assertSame('Latest Smartphones & Tablets', $phones['title']);
        $this->assertNotEmpty($phones['products']);

        $parent = Category::query()->where('slug', 'phones-and-tablets')->firstOrFail();
        $this->assertSame($parent->id, $phones['category_id']);
    }
}
