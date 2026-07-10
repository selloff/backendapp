<?php

use App\Support\BrandColor;

test('normalizes legacy default token', function () {
    expect(BrandColor::normalize('default'))->toBe(BrandColor::DEFAULT_PRIMARY);
});

test('keeps valid hex', function () {
    expect(BrandColor::normalize('#ff5500'))->toBe('#ff5500');
});

test('rejects invalid values', function () {
    expect(BrandColor::normalize('0'))->toBe(BrandColor::DEFAULT_PRIMARY);
    expect(BrandColor::normalize('not-a-color'))->toBe(BrandColor::DEFAULT_PRIMARY);
});

test('rejects achromatic legacy grey', function () {
    expect(BrandColor::normalize('#222222'))->toBe(BrandColor::DEFAULT_PRIMARY);
    expect(BrandColor::normalize('#1C1C1C'))->toBe(BrandColor::DEFAULT_PRIMARY);
});
