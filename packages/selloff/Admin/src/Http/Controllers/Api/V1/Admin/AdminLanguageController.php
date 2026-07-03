<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Models\Language;
use App\Modules\Selloff\Admin\Models\LanguageTranslation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminLanguageController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            Language::query()
                ->orderBy('language_order')
                ->orderBy('id')
                ->get([
                    'id',
                    'name',
                    'code',
                    'language_code',
                    'text_direction',
                    'language_order',
                    'text_editor_lang',
                    'flag_path',
                    'is_default',
                    'status',
                ]),
        );
    }

    public function show(Language $language): JsonResponse
    {
        return ApiResponse::success($language->only([
            'id',
            'name',
            'code',
            'language_code',
            'text_direction',
            'language_order',
            'text_editor_lang',
            'flag_path',
            'is_default',
            'status',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:20', 'unique:languages,code'],
            'language_code' => ['required', 'string', 'max:20'],
            'text_direction' => ['required', 'in:ltr,rtl'],
            'language_order' => ['required', 'integer', 'min:1', 'max:255'],
            'text_editor_lang' => ['required', 'string', 'max:20'],
            'flag_path' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        $language = DB::transaction(function () use ($data) {
            $language = Language::query()->create([
                ...$data,
                'status' => $data['status'] ?? true,
                'is_default' => $data['is_default'] ?? false,
            ]);

            if (! empty($data['is_default'])) {
                Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
            }

            $this->cloneDefaultTranslations($language);

            return $language;
        });

        return ApiResponse::success($language->fresh(), 201);
    }

    public function update(Request $request, Language $language): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'code' => ['sometimes', 'string', 'max:20', 'unique:languages,code,'.$language->id],
            'language_code' => ['sometimes', 'string', 'max:20'],
            'text_direction' => ['sometimes', 'in:ltr,rtl'],
            'language_order' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'text_editor_lang' => ['sometimes', 'string', 'max:20'],
            'flag_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        if ($language->is_default && array_key_exists('status', $data) && ! $data['status']) {
            return ApiResponse::error('Cannot deactivate the default language.', 422);
        }

        if (! empty($data['is_default'])) {
            Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
        }

        $language->update($data);

        return ApiResponse::success($language->fresh());
    }

    public function destroy(Language $language): JsonResponse
    {
        if ($language->is_default) {
            return ApiResponse::error('Cannot delete the default language.', 422);
        }

        if ((int) $language->id === 1) {
            return ApiResponse::error('Cannot delete the primary language.', 422);
        }

        $language->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function translations(Request $request, Language $language): JsonResponse
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 50)), 500);
        $q = trim((string) $request->query('q', ''));

        $query = $language->translations()->orderBy('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('label', 'ilike', '%'.$q.'%')
                    ->orWhere('translation', 'ilike', '%'.$q.'%');
            });
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success($paginator->toArray());
    }

    public function upsertTranslation(Request $request, Language $language): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'translation' => ['required', 'string', 'max:2000'],
        ]);

        $translation = LanguageTranslation::query()->updateOrCreate(
            ['language_id' => $language->id, 'label' => $data['label']],
            ['translation' => $data['translation']],
        );

        return ApiResponse::success($translation, 201);
    }

    public function bulkUpdateTranslations(Request $request, Language $language): JsonResponse
    {
        $data = $request->validate([
            'translations' => ['required', 'array'],
            'translations.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = 0;

        foreach ($data['translations'] as $id => $translation) {
            $translationId = (int) $id;
            if ($translationId <= 0) {
                continue;
            }

            $updated += LanguageTranslation::query()
                ->where('language_id', $language->id)
                ->whereKey($translationId)
                ->update(['translation' => $translation ?? '']);
        }

        return ApiResponse::success(['updated' => $updated]);
    }

    public function deleteTranslation(Language $language, LanguageTranslation $translation): JsonResponse
    {
        abort_unless((int) $translation->language_id === (int) $language->id, 404);

        $translation->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function export(Language $language): StreamedResponse
    {
        $payload = [
            'language' => [
                'name' => $language->name,
                'short_form' => $language->code,
                'language_code' => $language->language_code,
                'text_direction' => $language->text_direction,
                'text_editor_lang' => $language->text_editor_lang,
            ],
            'translations' => $language->translations()
                ->orderBy('id')
                ->get(['label', 'translation'])
                ->map(fn (LanguageTranslation $row) => [
                    'label' => $row->label,
                    'translation' => $row->translation,
                ])
                ->all(),
        ];

        $filename = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $language->name) ?: 'language';

        return response()->streamDownload(
            static function () use ($payload) {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            },
            $filename.'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'language' => ['required', 'array'],
            'language.name' => ['required', 'string', 'max:200'],
            'language.short_form' => ['required', 'string', 'max:20'],
            'language.language_code' => ['required', 'string', 'max:20'],
            'language.text_direction' => ['nullable', 'in:ltr,rtl'],
            'language.text_editor_lang' => ['nullable', 'string', 'max:20'],
            'translations' => ['nullable', 'array'],
            'translations.*.label' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.translation' => ['nullable', 'string', 'max:2000'],
            'flag_path' => ['nullable', 'string', 'max:500'],
        ]);

        $languageMeta = $data['language'];
        $shortForm = $languageMeta['short_form'];

        if (Language::query()->where('code', $shortForm)->exists()) {
            return ApiResponse::error('A language with this short form already exists.', 422);
        }

        $language = DB::transaction(function () use ($data, $languageMeta, $shortForm) {
            $order = (int) Language::query()->max('language_order') + 1;

            $language = Language::query()->create([
                'name' => $languageMeta['name'],
                'code' => $shortForm,
                'language_code' => $languageMeta['language_code'],
                'text_direction' => $languageMeta['text_direction'] ?? 'ltr',
                'text_editor_lang' => $languageMeta['text_editor_lang'] ?? 'en',
                'language_order' => $order,
                'flag_path' => $data['flag_path'] ?? null,
                'status' => true,
                'is_default' => false,
            ]);

            foreach ($data['translations'] ?? [] as $row) {
                LanguageTranslation::query()->create([
                    'language_id' => $language->id,
                    'label' => $row['label'],
                    'translation' => $row['translation'] ?? '',
                ]);
            }

            return $language;
        });

        return ApiResponse::success($language, 201);
    }

    private function cloneDefaultTranslations(Language $language): void
    {
        $source = Language::query()->find(1) ?? Language::query()->where('is_default', true)->first();

        if (! $source || (int) $source->id === (int) $language->id) {
            return;
        }

        $source->translations()
            ->orderBy('id')
            ->get(['label', 'translation'])
            ->each(function (LanguageTranslation $translation) use ($language) {
                LanguageTranslation::query()->create([
                    'language_id' => $language->id,
                    'label' => $translation->label,
                    'translation' => $translation->translation,
                ]);
            });
    }
}
