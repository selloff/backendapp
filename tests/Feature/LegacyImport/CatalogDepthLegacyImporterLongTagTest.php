<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/long-tag.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('tag longer than 255 chars is truncated', function () {
    $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

    $storedTag = DB::table('tags')->where('id', 4938)->value('tag');
    expect(mb_strlen((string) $storedTag))->toBe(255);
    expect(DB::table('product_tag')->where('product_id', 9315)->where('tag_id', 4938)->count())->toBe(1);
});