<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Admin\Models\Language;
use App\Modules\Selloff\Catalog\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminTagsListService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $search = trim((string) ($request->input('q') ?: $request->input('search', '')));
        $langId = $request->filled('lang_id') ? (int) $request->input('lang_id') : null;

        return Tag::query()
            ->select('tags.*')
            ->selectSub(
                DB::table('product_tag')
                    ->selectRaw('count(*)')
                    ->whereColumn('product_tag.tag_id', 'tags.id'),
                'products_count',
            )
            ->selectSub(
                DB::table('languages')
                    ->select('name')
                    ->whereColumn('languages.id', 'tags.lang_id')
                    ->limit(1),
                'language_name',
            )
            ->when($search !== '', fn ($query) => $query->where('tag', 'like', $search.'%'))
            ->when($langId !== null && $langId > 0, fn ($query) => $query->where('lang_id', $langId))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (Tag $tag): array => $this->transform($tag));
    }

    /**
     * @param  array{tag: string, lang_id: int}  $data
     */
    public function create(array $data): Tag
    {
        $this->assertUniqueTag($data['tag'], $data['lang_id']);

        return Tag::query()->create($data);
    }

    /**
     * @param  array{tag: string, lang_id: int}  $data
     */
    public function update(Tag $tag, array $data): Tag
    {
        $this->assertUniqueTag($data['tag'], $data['lang_id'], $tag->id);

        $tag->update($data);

        return $tag->fresh() ?? $tag;
    }

    private function assertUniqueTag(string $tag, int $langId, ?int $ignoreId = null): void
    {
        $exists = Tag::query()
            ->where('tag', $tag)
            ->where('lang_id', $langId)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'tag' => ['This tag already exists for the selected language.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'tag' => $tag->tag,
            'lang_id' => $tag->lang_id,
            'language_name' => $tag->language_name ?? Language::query()->whereKey($tag->lang_id)->value('name'),
            'products_count' => (int) ($tag->products_count ?? 0),
            'created_at' => $tag->created_at,
            'updated_at' => $tag->updated_at,
        ];
    }

    /**
     * @return array<string, list<string|Rule>>
     */
    public static function validationRules(?Tag $tag = null): array
    {
        return [
            'tag' => ['required', 'string', 'min:2', 'max:255'],
            'lang_id' => ['required', 'integer', 'exists:languages,id'],
        ];
    }
}
