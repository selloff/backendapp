<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Admin\Models\Language;
use Tests\TestCase;

class PublicLanguagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_public_languages_endpoint_returns_active_languages_without_auth(): void
    {
        $this->getJson('/api/v1/languages')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(1, Language::query()->where('status', true)->count());
    }
}
