<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Language;
use App\Modules\Selloff\Admin\Models\LanguageTranslation;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLanguagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_list_languages_with_legacy_fields(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'superadmin@selloff.test')->firstOrFail());

        $this->getJson('/api/v1/admin/languages')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'code', 'language_code', 'text_direction', 'language_order', 'text_editor_lang', 'is_default', 'status'],
                ],
            ]);
    }

    public function test_admin_can_create_language_and_clone_default_translations(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'superadmin@selloff.test')->firstOrFail());

        $english = Language::query()->where('code', 'en')->firstOrFail();
        LanguageTranslation::query()->firstOrCreate(
            ['language_id' => $english->id, 'label' => 'welcome'],
            ['translation' => 'Welcome'],
        );

        $this->postJson('/api/v1/admin/languages', [
            'name' => 'French',
            'code' => 'fr',
            'language_code' => 'fr-FR',
            'text_direction' => 'ltr',
            'language_order' => 2,
            'text_editor_lang' => 'fr_FR',
            'flag_path' => 'uploads/flags/fr.jpg',
            'status' => true,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'fr');

        $french = Language::query()->where('code', 'fr')->firstOrFail();
        $this->assertTrue(
            LanguageTranslation::query()
                ->where('language_id', $french->id)
                ->where('label', 'welcome')
                ->exists(),
        );
    }

    public function test_admin_can_bulk_update_paginated_translations(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'superadmin@selloff.test')->firstOrFail());

        $language = Language::query()->where('code', 'en')->firstOrFail();
        $translation = LanguageTranslation::query()->create([
            'language_id' => $language->id,
            'label' => 'save_changes',
            'translation' => 'Save changes',
        ]);

        $this->putJson("/api/v1/admin/languages/{$language->id}/translations/bulk", [
            'translations' => [
                (string) $translation->id => 'Save updates',
            ],
        ])->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertSame(
            'Save updates',
            LanguageTranslation::query()->findOrFail($translation->id)->translation,
        );
    }
}
