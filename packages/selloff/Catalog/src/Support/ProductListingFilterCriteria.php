<?php

namespace App\Modules\Selloff\Catalog\Support;

use Illuminate\Http\Request;

class ProductListingFilterCriteria
{
    /** @param  array<string, list<string>>  $customFieldFilters */
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $vendorId = null,
        /** @var list<int> */
        public readonly array $brandIds = [],
        public readonly ?float $minPrice = null,
        public readonly ?float $maxPrice = null,
        public readonly bool $promoted = false,
        public readonly bool $discounted = false,
        public readonly ?int $priorityStateId = null,
        public readonly ?int $priorityCityId = null,
        public readonly array $customFieldFilters = [],
    ) {}

    public static function fromRequest(Request $request, ?array $knownFilterKeys = null): self
    {
        $brandIds = self::parseBrandIds($request);

        $customFieldFilters = [];
        $reserved = self::reservedKeys();

        foreach ($request->query() as $key => $value) {
            if (! is_string($key) || in_array($key, $reserved, true)) {
                continue;
            }

            if ($knownFilterKeys !== null && ! in_array($key, $knownFilterKeys, true)) {
                continue;
            }

            $values = self::parseCommaSeparated((string) $value);
            if ($values !== []) {
                $customFieldFilters[$key] = $values;
            }
        }

        return new self(
            search: $request->filled('search') ? trim((string) $request->input('search')) : null,
            categoryId: $request->filled('category_id') ? $request->integer('category_id') : null,
            vendorId: $request->filled('vendor_id') ? $request->integer('vendor_id') : null,
            brandIds: $brandIds,
            minPrice: $request->filled('min_price') ? $request->float('min_price') : null,
            maxPrice: $request->filled('max_price') ? $request->float('max_price') : null,
            promoted: $request->boolean('promoted'),
            discounted: $request->boolean('discounted'),
            priorityStateId: $request->filled('priority_state_id') ? $request->integer('priority_state_id') : null,
            priorityCityId: $request->filled('priority_city_id') ? $request->integer('priority_city_id') : null,
            customFieldFilters: $customFieldFilters,
        );
    }

    /**
     * @return list<string>
     */
    public static function reservedKeys(): array
    {
        return [
            'search',
            'sort',
            'direction',
            'page',
            'per_page',
            'category_id',
            'vendor_id',
            'brand_id',
            'brand',
            'min_price',
            'max_price',
            'promoted',
            'discounted',
            'priority_state_id',
            'priority_city_id',
            'q',
        ];
    }

    /**
     * @return list<int>
     */
    private static function parseBrandIds(Request $request): array
    {
        $ids = [];

        if ($request->filled('brand_id') && $request->integer('brand_id') > 0) {
            $ids[] = $request->integer('brand_id');
        }

        if ($request->filled('brand')) {
            foreach (explode(',', (string) $request->input('brand')) as $part) {
                $id = (int) trim($part);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private static function parseCommaSeparated(string $value): array
    {
        return array_values(array_filter(array_map(
            fn (string $part) => trim($part),
            explode(',', $value),
        ), fn (string $part) => $part !== ''));
    }

    /**
     * @param  array<string, list<string>>|null  $exceptFilterKey
     */
    public function withCustomFieldFiltersExcept(?string $exceptFilterKey): self
    {
        if ($exceptFilterKey === null) {
            return $this;
        }

        $filters = $this->customFieldFilters;
        unset($filters[$exceptFilterKey]);

        return new self(
            search: $this->search,
            categoryId: $this->categoryId,
            vendorId: $this->vendorId,
            brandIds: $exceptFilterKey === 'brand' ? [] : $this->brandIds,
            minPrice: $this->minPrice,
            maxPrice: $this->maxPrice,
            promoted: $this->promoted,
            discounted: $this->discounted,
            priorityStateId: $this->priorityStateId,
            priorityCityId: $this->priorityCityId,
            customFieldFilters: $filters,
        );
    }
}
