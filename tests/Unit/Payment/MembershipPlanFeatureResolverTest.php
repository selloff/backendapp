<?php

namespace Tests\Unit\Payment;

use App\Modules\Selloff\Payment\Services\MembershipPlanFeatureResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipPlanFeatureResolverTest extends TestCase
{
    private MembershipPlanFeatureResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new MembershipPlanFeatureResolver;
    }

    #[Test]
    public function it_decodes_legacy_features_array_by_language(): void
    {
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

        $this->assertSame(
            ['Unlimited listings', 'Priority support'],
            $this->resolver->fromLegacyFeaturesArray($serialized, 1),
        );
    }

    #[Test]
    public function it_falls_back_to_first_legacy_language_features(): void
    {
        $serialized = serialize([
            [
                'lang_id' => 2,
                'features' => ['Listados ilimitados'],
            ],
        ]);

        $this->assertSame(
            ['Listados ilimitados'],
            $this->resolver->fromLegacyFeaturesArray($serialized),
        );
    }

    #[Test]
    public function it_ignores_blank_legacy_features(): void
    {
        $serialized = serialize([
            [
                'lang_id' => 1,
                'features' => ['', '  ', 'Featured placement'],
            ],
        ]);

        $this->assertSame(
            ['Featured placement'],
            $this->resolver->fromLegacyFeaturesArray($serialized, 1),
        );
    }
}
