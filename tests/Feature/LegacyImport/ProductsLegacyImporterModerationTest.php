<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/product-moderation-import.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('published legacy product with verified no is imported as admin approved', function () {
    $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

    $published = DB::table('products')->where('id', 95001)->first();
    expect($published)->not->toBeNull();
    expect($published->status)->toBe('published');
    expect((bool) $published->is_verified)->toBeTrue();

    $pending = DB::table('products')->where('id', 95002)->first();
    expect($pending)->not->toBeNull();
    expect($pending->status)->toBe('pending');
    expect((bool) $pending->is_verified)->toBeFalse();

    $rejected = DB::table('products')->where('id', 95003)->first();
    expect($rejected)->not->toBeNull();
    expect($rejected->status)->toBe('hidden');
    expect((bool) $rejected->is_verified)->toBeFalse();
});