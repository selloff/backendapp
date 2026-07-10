<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Language;
use App\Modules\Selloff\Admin\Models\LanguageTranslation;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can list languages with legacy fields', function () {
    Sanctum::actingAs(User::query()->where('email', 'superadmin@selloff.test')->firstOrFail());

    $this->getJson('/api/v1/admin/languages')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                ['id', 'name', 'code', 'language_code', 'text_direction', 'language_order', 'text_editor_lang', 'is_default', 'status'],
            ],
        ]);
});

test('admin can create language and clone default translations', function () {
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
    ], superAdminPinHeaders())->assertCreated()
        ->assertJsonPath('data.code', 'fr');

    $french = Language::query()->where('code', 'fr')->firstOrFail();
    expect(LanguageTranslation::query()
        ->where('language_id', $french->id)
        ->where('label', 'welcome')
        ->exists())->toBeTrue();
});

test('admin can bulk update paginated translations', function () {
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
    ], superAdminPinHeaders())->assertOk()
        ->assertJsonPath('data.updated', 1);

    expect(LanguageTranslation::query()->findOrFail($translation->id)->translation)->toBe('Save updates');
});
