<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\Wishlist;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MdsPreferencesPhase10Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_marketplace_preferences_exposes_mds_storage_mapping(): void
    {
        $this->getJson('/api/v1/preferences/marketplace')
            ->assertOk()
            ->assertJsonPath('data.default_currency', 'NGN')
            ->assertJsonPath('data.storage_keys.selected_currency', 'mds_selected_currency')
            ->assertJsonPath('data.storage_keys.guest_wishlist', 'mds_guest_wishlist')
            ->assertJsonStructure([
                'data' => [
                    'currency_converter_enabled',
                    'session_note',
                ],
            ]);
    }

    public function test_guest_wishlist_preview_returns_active_products(): void
    {
        $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $audio = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

        $this->getJson('/api/v1/wishlist/guest-preview?'.http_build_query([
            'product_ids' => [$phone->id, $audio->id, 999999],
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.product.id', $phone->id);
    }

    public function test_authenticated_user_can_merge_guest_wishlist_product_ids(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $audio = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wishlist/merge-guest', [
            'product_ids' => [$phone->id, $audio->id, $phone->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.merged', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertTrue(
            Wishlist::query()
                ->where('user_id', $buyer->id)
                ->whereIn('product_id', [$phone->id, $audio->id])
                ->count() === 2,
        );

        $this->postJson('/api/v1/wishlist/merge-guest', [
            'product_ids' => [$phone->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.merged', 0)
            ->assertJsonPath('data.skipped', 1);
    }
}
