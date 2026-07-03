<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Language;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotedAdminPhase13Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_save_currency_converter_settings(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/settings', [
            'group' => 'payment',
            'settings' => [
                'default_currency' => 'NGN',
                'currency_converter' => true,
                'auto_update_exchange_rates' => true,
                'currency_converter_api' => 'fixer',
                'currency_converter_api_key' => 'test-key',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.currency_converter', true)
            ->assertJsonPath('data.settings.default_currency', 'NGN');

        $this->getJson('/api/v1/preferences/marketplace')
            ->assertOk()
            ->assertJsonPath('data.default_currency', 'NGN')
            ->assertJsonPath('data.currency_converter_enabled', true);
    }

    public function test_admin_can_manage_languages_and_translations(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $english = Language::query()->where('code', 'en')->firstOrFail();

        $this->getJson('/api/v1/admin/languages')
            ->assertOk()
            ->assertJsonFragment(['code' => 'en']);

        $this->postJson('/api/v1/admin/languages', [
            'name' => 'Hausa',
            'code' => 'ha',
            'is_default' => false,
            'status' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ha');

        $this->postJson("/api/v1/admin/languages/{$english->id}/translations", [
            'label' => 'welcome_message',
            'translation' => 'Welcome to Selloff',
        ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'welcome_message');

        $this->getJson("/api/v1/admin/languages/{$english->id}/translations")
            ->assertOk()
            ->assertJsonFragment(['label' => 'welcome_message', 'translation' => 'Welcome to Selloff']);
    }
}
