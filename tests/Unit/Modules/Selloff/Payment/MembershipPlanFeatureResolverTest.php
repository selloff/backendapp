<?php

use App\Modules\Selloff\Payment\Services\MembershipPlanFeatureResolver;
use PHPUnit\Framework\Attributes\Test;

beforeEach(function () {
    $this->resolver = new MembershipPlanFeatureResolver;
});

it('decodes legacy features array by language', function () {
    $serialized = serialize([
        [
            'lang_id' => 1,
            'features' => ['Unlimited listings', 'Priority support'],
        ],
        [
            'lang_id' => 2,
            'features' => ['Listados ilimitados'],
        ],
    ]);

    expect($this->resolver->fromLegacyFeaturesArray($serialized, 1))->toBe(['Unlimited listings', 'Priority support']);
});

it('falls back to first legacy language features', function () {
    $serialized = serialize([
        [
            'lang_id' => 2,
            'features' => ['Listados ilimitados'],
        ],
    ]);

    expect($this->resolver->fromLegacyFeaturesArray($serialized))->toBe(['Listados ilimitados']);
});

it('ignores blank legacy features', function () {
    $serialized = serialize([
        [
            'lang_id' => 1,
            'features' => ['', '  ', 'Featured placement'],
        ],
    ]);

    expect($this->resolver->fromLegacyFeaturesArray($serialized, 1))->toBe(['Featured placement']);
});