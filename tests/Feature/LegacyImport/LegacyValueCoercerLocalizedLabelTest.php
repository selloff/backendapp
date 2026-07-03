<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Support\LegacyValueCoercer;
use Tests\TestCase;

class LegacyValueCoercerLocalizedLabelTest extends TestCase
{
    public function test_localized_label_extracts_title_from_membership_plan_array_rows(): void
    {
        $serialized = serialize([
            ['lang_id' => '1', 'title' => 'Free Plan'],
        ]);

        $this->assertSame('Free Plan', LegacyValueCoercer::localizedLabel($serialized, 'Plan 1'));
    }

    public function test_localized_label_extracts_nested_en_map(): void
    {
        $this->assertSame(
            'Premium',
            LegacyValueCoercer::localizedLabel(['en' => 'Premium'], 'Fallback'),
        );
    }

    public function test_first_scalar_string_returns_null_for_empty_array(): void
    {
        $this->assertNull(LegacyValueCoercer::firstScalarString([]));
    }
}
