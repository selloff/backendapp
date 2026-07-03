<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\CustomFieldOption;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCustomFieldDepthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_attach_and_detach_custom_field_category(): void
    {
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

        $this->deleteJson("/api/v1/admin/catalog/custom-fields/{$fieldId}/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.category_ids', []);
    }

    public function test_admin_can_manage_custom_field_options_and_sort_settings(): void
    {
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

        $this->deleteJson("/api/v1/admin/catalog/custom-fields/{$fieldId}/options/{$optionId}")
            ->assertOk();

        $this->assertDatabaseMissing('custom_field_options', ['id' => $optionId]);
    }

    public function test_admin_can_search_custom_fields_with_pagination(): void
    {
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

        $this->assertSame(15, $response->json('data.per_page'));
        $this->assertGreaterThanOrEqual(2, $response->json('data.total'));
        $this->assertGreaterThanOrEqual(2, count($response->json('data.data')));
        $this->assertTrue(
            collect($response->json('data.data'))->every(
                fn (array $row) => str_starts_with((string) ($row['label'] ?? ''), 'DepthSearch'),
            ),
        );
    }

    public function test_admin_can_toggle_product_filter_on_select_field(): void
    {
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
    }

    public function test_admin_can_bulk_import_custom_fields_from_csv_payload(): void
    {
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
    }
}
