<?php

use App\LegacyImport\Support\LegacyValueCoercer;

test('localized label extracts title from membership plan array rows', function () {
    $serialized = serialize([
        ['lang_id' => '1', 'title' => 'Free Plan'],
    ]);

    expect(LegacyValueCoercer::localizedLabel($serialized, 'Plan 1'))->toBe('Free Plan');
});

test('localized label extracts nested en map', function () {
    expect(LegacyValueCoercer::localizedLabel(['en' => 'Premium'], 'Fallback'))->toBe('Premium');
});

test('first scalar string returns null for empty array', function () {
    expect(LegacyValueCoercer::firstScalarString([]))->toBeNull();
});
