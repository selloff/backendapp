<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Payment\Models\MembershipPlan;

class MembershipPlanFeatureResolver
{
    /**
     * @return list<string>
     */
    public function forPlan(MembershipPlan $plan, ?int $langId = null): array
    {
        if (is_array($plan->features)) {
            $features = $this->normalizeList($plan->features);
            if ($features !== []) {
                return $features;
            }
        }

        if ($plan->getAttribute('features_array')) {
            $features = $this->fromLegacyFeaturesArray($plan->getAttribute('features_array'), $langId);
            if ($features !== []) {
                return $features;
            }
        }

        if ($plan->description) {
            return array_values(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', $plan->description) ?: [],
            ), fn (string $line) => $line !== ''));
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function fromLegacyFeaturesArray(mixed $featuresArray, ?int $langId = null): array
    {
        $entries = $this->decodeLegacyFeaturesArray($featuresArray);
        if ($entries === []) {
            return [];
        }

        $fallback = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $features = $this->normalizeList($entry['features'] ?? null);
            if ($features === []) {
                continue;
            }

            $entryLangId = (int) ($entry['lang_id'] ?? 0);

            if ($langId !== null && $entryLangId === $langId) {
                return $features;
            }

            if ($fallback === [] && ($langId === null || $entryLangId === 1)) {
                $fallback = $features;
            }
        }

        if ($fallback !== []) {
            return $fallback;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $features = $this->normalizeList($entry['features'] ?? null);
            if ($features !== []) {
                return $features;
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeLegacyFeaturesArray(mixed $featuresArray): array
    {
        if ($featuresArray === null || $featuresArray === '') {
            return [];
        }

        if (is_array($featuresArray)) {
            return array_is_list($featuresArray) ? $featuresArray : [];
        }

        $decoded = @unserialize((string) $featuresArray, ['allowed_classes' => false]);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $features): array
    {
        if (! is_array($features)) {
            return [];
        }

        $normalized = [];

        foreach ($features as $feature) {
            if (! is_scalar($feature)) {
                continue;
            }

            $value = trim((string) $feature);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
