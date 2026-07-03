<?php

namespace App\Modules\Selloff\Catalog\Support;

use Illuminate\Database\Eloquent\Builder;

class ProductLocationPriorityQuery
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function apply(Builder $query, ?int $priorityStateId, ?int $priorityCityId): void
    {
        if ($priorityCityId) {
            $query->orderByRaw(
                'CASE WHEN city_id = ? THEN 0 WHEN state_id = ? THEN 1 ELSE 2 END',
                [$priorityCityId, $priorityStateId ?? 0]
            );

            return;
        }

        if ($priorityStateId) {
            $query->orderByRaw(
                'CASE WHEN state_id = ? THEN 0 ELSE 1 END',
                [$priorityStateId]
            );
        }
    }

    /**
     * @return array{priority_state_id: int|null, priority_city_id: int|null}
     */
    public static function fromRequest(\Illuminate\Http\Request $request): array
    {
        return [
            'priority_state_id' => $request->filled('priority_state_id') ? $request->integer('priority_state_id') : null,
            'priority_city_id' => $request->filled('priority_city_id') ? $request->integer('priority_city_id') : null,
        ];
    }
}
