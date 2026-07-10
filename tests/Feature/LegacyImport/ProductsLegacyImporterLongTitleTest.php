<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/long-title-product.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('product title longer than 255 chars is truncated', function () {
    $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

    expect(DB::table('users')->where('id', 91001)->first())->not->toBeNull('vendor user missing');
    expect(DB::table('products')->where('id', 9315)->first())->not->toBeNull('product missing');

    $storedTitle = DB::table('product_translations')->where('product_id', 9315)->value('title');
    expect(mb_strlen((string) $storedTitle))->toBe(255);
    expect($storedTitle)->toBe(str_repeat('A', 255));
    expect(DB::table('product_translations')->where('product_id', 9315)->value('description'))->toBe('Full description preserved');
});