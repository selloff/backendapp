<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class CategoryCatalogRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_public_categories_index_is_registered(): void
    {
        $this->getJson('/api/v1/categories?roots_only=1')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'slug', 'name', 'parent_id', 'ads_count', 'image_url'],
                ],
            ]);
    }

    public function test_categories_include_nested_children(): void
    {
        $response = $this->getJson('/api/v1/categories?roots_only=1')->assertOk();
        $roots = collect($response->json('data'));

        $withChildren = $roots->first(fn (array $category) => ! empty($category['children']));
        $this->assertNotNull($withChildren, 'Expected at least one root category with nested children.');
        $this->assertArrayHasKey('name', $withChildren['children'][0]);
        $this->assertTrue($withChildren['has_children']);
    }

    public function test_category_children_endpoint_returns_subcategories(): void
    {
        $parent = collect($this->getJson('/api/v1/categories?roots_only=1')->json('data'))
            ->first(fn (array $category) => ! empty($category['children']));

        $this->assertNotNull($parent);

        $this->getJson("/api/v1/categories/{$parent['id']}/children")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'name', 'parent_id', 'has_children'],
                ],
            ]);
    }

    public function test_categories_include_rollup_listing_counts(): void
    {
        $response = $this->getJson('/api/v1/categories?roots_only=1')->assertOk();
        $roots = collect($response->json('data'));

        $this->assertGreaterThan(0, $roots->count());
        $this->assertGreaterThan(0, (int) $roots->first()['ads_count']);
    }
}
