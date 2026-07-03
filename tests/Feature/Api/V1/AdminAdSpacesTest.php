<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Content\Models\AdSpace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAdSpacesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_load_legacy_slot_and_auto_create_missing_row(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        AdSpace::query()->where('ad_space_key', 'blog_2')->delete();

        $this->getJson('/api/v1/admin/cms/ad-spaces/by-key/blog_2')
            ->assertOk()
            ->assertJsonPath('data.ad_space_key', 'blog_2')
            ->assertJsonPath('data.desktop_width', 728)
            ->assertJsonPath('data.mobile_height', 250);

        $this->assertDatabaseHas('ad_spaces', ['ad_space_key' => 'blog_2']);
    }

    public function test_admin_can_update_ad_space_dimensions_and_codes(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $adSpace = AdSpace::query()->where('ad_space_key', 'index_1')->firstOrFail();

        $this->putJson("/api/v1/admin/cms/ad-spaces/{$adSpace->id}", [
            'ad_code_desktop' => '<div>Desktop ad</div>',
            'ad_code_mobile' => '<div>Mobile ad</div>',
            'desktop_width' => 970,
            'desktop_height' => 120,
            'mobile_width' => 320,
            'mobile_height' => 100,
        ])
            ->assertOk()
            ->assertJsonPath('data.ad_code_desktop', '<div>Desktop ad</div>')
            ->assertJsonPath('data.desktop_width', 970)
            ->assertJsonPath('data.mobile_height', 100);
    }

    public function test_admin_can_upload_banner_images_to_generate_ad_html(): void
    {
        Storage::fake('public');

        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $adSpace = AdSpace::query()->where('ad_space_key', 'index_2')->firstOrFail();

        $this->post("/api/v1/admin/cms/ad-spaces/{$adSpace->id}?_method=PUT", [
            'ad_code_desktop' => '<div>old</div>',
            'ad_code_mobile' => '<div>old mobile</div>',
            'desktop_width' => 728,
            'desktop_height' => 90,
            'mobile_width' => 300,
            'mobile_height' => 250,
            'url_ad_code_desktop' => 'https://example.com/desktop',
            'url_ad_code_mobile' => 'https://example.com/mobile',
            'file_ad_code_desktop' => UploadedFile::fake()->image('desktop-banner.jpg', 728, 90),
            'file_ad_code_mobile' => UploadedFile::fake()->image('mobile-banner.jpg', 300, 250),
        ])
            ->assertOk()
            ->assertJsonPath('data.desktop_width', 728);

        $fresh = AdSpace::query()->findOrFail($adSpace->id);
        $this->assertStringContainsString('https://example.com/desktop', (string) $fresh->ad_code_desktop);
        $this->assertStringContainsString('class="lazyload"', (string) $fresh->ad_code_desktop);
        $this->assertStringContainsString('width="728"', (string) $fresh->ad_code_desktop);
        $this->assertStringContainsString('https://example.com/mobile', (string) $fresh->ad_code_mobile);
    }

    public function test_admin_can_update_google_adsense_code_with_ad_spaces_permission(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/cms/ad-spaces/google-adsense', [
            'google_adsense_code' => '<script>adsense</script>',
        ])
            ->assertOk()
            ->assertJsonPath('data.google_adsense_code', '<script>adsense</script>');
    }

    public function test_buyer_ad_space_includes_dimensions(): void
    {
        $adSpace = AdSpace::query()->where('ad_space_key', 'index_1')->firstOrFail();
        $adSpace->update([
            'ad_code_desktop' => '<div>desktop</div>',
            'desktop_width' => 600,
            'desktop_height' => 80,
        ]);

        $this->getJson('/api/v1/ad-spaces/index_1')
            ->assertOk()
            ->assertJsonPath('data.desktop_width', 600)
            ->assertJsonPath('data.desktop_height', 80)
            ->assertJsonPath('data.id', $adSpace->id);
    }
}
