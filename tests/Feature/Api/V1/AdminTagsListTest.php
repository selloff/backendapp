<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Tag;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin tags list supports language filter and prefix search', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    Tag::query()->create(['tag' => 'alpha-widget', 'lang_id' => 1]);
    Tag::query()->create(['tag' => 'beta-widget', 'lang_id' => 1]);
    Tag::query()->create(['tag' => 'alpha-gadget', 'lang_id' => 1]);

    $this->getJson('/api/v1/admin/tags?q=alpha&lang_id=1')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.data')
        ->assertJsonPath('data.data.0.tag', 'alpha-gadget');
});
