<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Content\Models\AdSpace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerAdSpacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_ad_space_returns_desktop_and_mobile_codes(): void
    {
        AdSpace::query()->create([
            'ad_space_key' => 'index_1',
            'title' => 'Homepage slot',
            'ad_code_desktop' => '<div id="desktop-ad">desktop</div>',
            'ad_code_mobile' => '<div id="mobile-ad">mobile</div>',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/ad-spaces/index_1');

        $response->assertOk()
            ->assertJsonPath('data.ad_code_desktop', '<div id="desktop-ad">desktop</div>')
            ->assertJsonPath('data.ad_code_mobile', '<div id="mobile-ad">mobile</div>')
            ->assertJsonPath('data.ad_code', '<div id="desktop-ad">desktop</div>');
    }

    public function test_buyer_ad_space_falls_back_to_legacy_ad_code(): void
    {
        AdSpace::query()->create([
            'ad_space_key' => 'products_1',
            'title' => 'Products slot',
            'ad_code' => '<div id="legacy-ad">legacy</div>',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/ad-spaces/products_1');

        $response->assertOk()
            ->assertJsonPath('data.ad_code_desktop', '<div id="legacy-ad">legacy</div>')
            ->assertJsonPath('data.ad_code', '<div id="legacy-ad">legacy</div>');
    }
}
