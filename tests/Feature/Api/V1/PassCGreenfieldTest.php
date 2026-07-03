<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Content\Models\Page;
use App\Modules\Selloff\Support\Models\ContactMessage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassCGreenfieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_public_can_view_cms_page_by_slug(): void
    {
        $this->getJson('/api/v1/pages/about-us')
            ->assertOk()
            ->assertJsonPath('data.slug', 'about-us')
            ->assertJsonPath('data.title', 'About Selloff');
    }

    public function test_public_can_submit_contact_form(): void
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'Ada Buyer',
            'email' => 'ada@example.com',
            'subject' => 'Order question',
            'message' => 'When will my order ship?',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'ada@example.com',
            'subject' => 'Order question',
            'status' => 'pending',
        ]);
    }

    public function test_public_can_list_vendor_shops_directory(): void
    {
        $this->getJson('/api/v1/vendors')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data']]);

        $count = count($this->getJson('/api/v1/vendors')->json('data.data'));
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_vendor_can_save_wallet_payout_account(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->putJson('/api/v1/wallet/payout-account', [
            'bank_name' => 'Demo Bank',
            'account_name' => 'Demo Vendor Shop',
            'account_number' => '0123456789',
            'swift_code' => 'DEMOXX',
        ])
            ->assertOk()
            ->assertJsonPath('data.payout_account.account_number', '0123456789');

        $this->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.payout_account.bank_name', 'Demo Bank');
    }

    public function test_inactive_cms_page_is_not_public(): void
    {
        Page::query()->where('slug', 'about-us')->update(['is_active' => false]);

        $this->getJson('/api/v1/pages/about-us')->assertNotFound();
    }
}
