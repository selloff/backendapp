<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;

class ProductEditStagingService
{
    public const LOCALE = 'en';

    /** @var list<string> */
    public const MODERATED_FIELDS = ['title', 'description', 'price', 'price_discounted'];

    /**
     * @return array<string, mixed>
     */
    public function captureApprovedSnapshot(Product $product): array
    {
        $product->loadMissing('translations');

        if (is_array($product->approved_snapshot) && $product->approved_snapshot !== []) {
            return $product->approved_snapshot;
        }

        $snapshot = $this->buildSnapshotFromLive($product);
        $product->update(['approved_snapshot' => $snapshot]);

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshotFromLive(Product $product): array
    {
        $product->loadMissing('translations');
        $translation = $this->resolveTranslation($product);

        return [
            'title' => $translation?->title,
            'description' => $translation?->description,
            'price' => $this->formatDecimal($product->price),
            'price_discounted' => $product->price_discounted !== null
                ? $this->formatDecimal($product->price_discounted)
                : null,
            'locale' => self::LOCALE,
        ];
    }

    /**
     * @param  array<string, mixed>  $translationFields
     * @param  array<string, mixed>  $productData
     * @return array<string, mixed>
     */
    public function mergeProposedChanges(Product $product, array $translationFields, array $productData): array
    {
        $product->loadMissing('translations');

        $base = is_array($product->pending_changes) && $product->pending_changes !== []
            ? $product->pending_changes
            : $this->buildSnapshotFromLive($product);

        $merged = $base;

        foreach (['title', 'description'] as $field) {
            if (array_key_exists($field, $translationFields)) {
                $merged[$field] = $translationFields[$field];
            }
        }

        if (array_key_exists('price', $productData)) {
            $merged['price'] = $this->formatDecimal($productData['price']);
        }

        if (array_key_exists('price_discounted', $productData)) {
            $merged['price_discounted'] = $productData['price_discounted'] !== null
                ? $this->formatDecimal($productData['price_discounted'])
                : null;
        }

        $merged['locale'] = self::LOCALE;

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $proposed
     */
    public function stageModeratedFields(Product $product, array $proposed): void
    {
        $this->captureApprovedSnapshot($product->fresh(['translations']));
        $product->refresh();

        $approved = is_array($product->approved_snapshot) && $product->approved_snapshot !== []
            ? $product->approved_snapshot
            : $this->buildSnapshotFromLive($product);

        $product->update([
            'pending_changes' => $proposed,
            'pending_submitted_at' => now(),
            'last_edit_reject_reason' => null,
            'last_edit_rejected_at' => null,
        ]);

        $this->restoreLiveFromSnapshot($product->fresh(['translations']), $approved);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function restoreLiveFromSnapshot(Product $product, array $snapshot): void
    {
        $locale = (string) ($snapshot['locale'] ?? self::LOCALE);

        ProductTranslation::query()->updateOrCreate(
            ['product_id' => $product->id, 'locale' => $locale],
            [
                'title' => $snapshot['title'] ?? null,
                'description' => $snapshot['description'] ?? null,
            ],
        );

        $product->update([
            'price' => $snapshot['price'] ?? $product->price,
            'price_discounted' => $snapshot['price_discounted'] ?? null,
        ]);
    }

    public function applyPendingChanges(Product $product): void
    {
        $pending = $product->pending_changes;

        if (! is_array($pending) || $pending === []) {
            return;
        }

        $locale = (string) ($pending['locale'] ?? self::LOCALE);

        ProductTranslation::query()->updateOrCreate(
            ['product_id' => $product->id, 'locale' => $locale],
            [
                'title' => $pending['title'] ?? null,
                'description' => $pending['description'] ?? null,
            ],
        );

        $approvedSnapshot = [
            'title' => $pending['title'] ?? null,
            'description' => $pending['description'] ?? null,
            'price' => $this->formatDecimal($pending['price'] ?? $product->price),
            'price_discounted' => array_key_exists('price_discounted', $pending) && $pending['price_discounted'] !== null
                ? $this->formatDecimal($pending['price_discounted'])
                : null,
            'locale' => $locale,
        ];

        $product->update([
            'price' => $approvedSnapshot['price'],
            'price_discounted' => $approvedSnapshot['price_discounted'],
            'approved_snapshot' => $approvedSnapshot,
            'pending_changes' => null,
            'pending_submitted_at' => null,
            'last_edit_reject_reason' => null,
            'last_edit_rejected_at' => null,
        ]);
    }

    public function discardPendingChanges(Product $product): void
    {
        $approved = is_array($product->approved_snapshot) && $product->approved_snapshot !== []
            ? $product->approved_snapshot
            : null;

        if ($approved !== null) {
            $this->restoreLiveFromSnapshot($product->fresh(['translations']), $approved);
        }

        $product->update([
            'pending_changes' => null,
            'pending_submitted_at' => null,
        ]);
    }

    /**
     * @return array{approved: array<string, mixed>, pending: array<string, mixed>, changed_fields: list<string>}|null
     */
    public function buildModerationDiff(Product $product): ?array
    {
        if (! is_array($product->pending_changes) || $product->pending_changes === []) {
            return null;
        }

        $product->loadMissing('translations');

        $approved = is_array($product->approved_snapshot) && $product->approved_snapshot !== []
            ? $product->approved_snapshot
            : $this->buildSnapshotFromLive($product);

        $pending = $product->pending_changes;
        $changedFields = [];

        foreach (self::MODERATED_FIELDS as $field) {
            $approvedValue = $approved[$field] ?? null;
            $pendingValue = $pending[$field] ?? null;

            if ($this->normalizeDiffValue($approvedValue) !== $this->normalizeDiffValue($pendingValue)) {
                $changedFields[] = $field;
            }
        }

        return [
            'approved' => $approved,
            'pending' => $pending,
            'changed_fields' => $changedFields,
        ];
    }

    private function resolveTranslation(Product $product): ?ProductTranslation
    {
        return $product->translations->firstWhere('locale', self::LOCALE)
            ?? $product->translations->first();
    }

    private function formatDecimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function normalizeDiffValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return $this->formatDecimal($value);
        }

        return (string) $value;
    }
}
