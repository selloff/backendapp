<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/product-pageviews-import.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('legacy pageviews are imported on products', function () {
    $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

    $popular = DB::table('products')->where('id', 96001)->first();
    expect($popular)->not->toBeNull();
    expect((int) $popular->pageviews)->toBe(1847);

    $newListing = DB::table('products')->where('id', 96002)->first();
    expect($newListing)->not->toBeNull();
    expect((int) $newListing->pageviews)->toBe(0);
});

test('backfill command restores pageviews from dump', function () {
    $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

    DB::table('products')->where('id', 96001)->update(['pageviews' => 0]);

    $this->artisan('selloff:backfill-product-pageviews', ['--source' => $this->fixture])->assertSuccessful();

    expect((int) DB::table('products')->where('id', 96001)->value('pageviews'))->toBe(1847);
});