<?php

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('public categories index is registered', function () {
    $this->getJson('/api/v1/categories?roots_only=1')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'slug', 'name', 'parent_id', 'ads_count', 'image_url'],
            ],
        ]);
});

test('categories include nested children', function () {
    $response = $this->getJson('/api/v1/categories?roots_only=1')->assertOk();
    $roots = collect($response->json('data'));

    $withChildren = $roots->first(fn (array $category) => ! empty($category['children']));
    expect($withChildren)->not->toBeNull('Expected at least one root category with nested children.');
    expect($withChildren['children'][0])->toHaveKey('name');
    expect($withChildren['has_children'])->toBeTrue();
});

test('category children endpoint returns subcategories', function () {
    $parent = collect($this->getJson('/api/v1/categories?roots_only=1')->json('data'))
        ->first(fn (array $category) => ! empty($category['children']));

    expect($parent)->not->toBeNull();

    $this->getJson("/api/v1/categories/{$parent['id']}/children")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'name', 'parent_id', 'has_children'],
            ],
        ]);
});

test('categories include rollup listing counts', function () {
    $response = $this->getJson('/api/v1/categories?roots_only=1')->assertOk();
    $roots = collect($response->json('data'));

    expect($roots->count())->toBeGreaterThan(0);
    expect((int) $roots->first()['ads_count'])->toBeGreaterThan(0);
});
