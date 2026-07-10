<?php

use App\Modules\Selloff\Admin\Models\Language;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('public languages endpoint returns active languages without auth', function () {
    $this->getJson('/api/v1/languages')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Language::query()->where('status', true)->count())->toBeGreaterThanOrEqual(1);
});
