<?php

namespace App\Modules\Selloff\Catalog\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AdminProductQueryFilter
{
    public static function apply(Builder $query, Request $request): void
    {
        $list = $request->string('list')->toString();

        if ($list === 'all' || $list === 'products' || $list === '') {
            $query->adminItemsForSale();
        }

        if ($request->boolean('pending_only') || $list === 'pending') {
            $query->adminPendingModeration();
        }

        if ($list === 'featured') {
            $query->where('is_promoted', true);
        }

        if ($list === 'special') {
            $query->where('is_special_offer', true)
                ->where('is_deleted', false)
                ->where('is_draft', false);
        }

        if ($list === 'edited') {
            $query->where('is_edited', true)
                ->where('is_deleted', false)
                ->where('is_draft', false);
        }

        if ($list === 'hidden') {
            $query->where('is_deleted', false)
                ->where('is_draft', false)
                ->where('is_verified', true)
                ->where(function (Builder $inner) {
                    $inner->where('visibility', 'hidden')->orWhere('visibility', '0');
                });
        }

        if ($list === 'expired') {
            $query->where('is_deleted', false)
                ->where('is_draft', false)
                ->whereHas('vendor', function (Builder $vendor) {
                    $vendor->whereNotExists(function ($sub) {
                        $sub->from('user_membership_plans')
                            ->whereColumn('user_membership_plans.user_id', 'users.id')
                            ->where('user_membership_plans.is_active', true)
                            ->where(function ($expires) {
                                $expires->whereNull('user_membership_plans.expires_at')
                                    ->orWhere('user_membership_plans.expires_at', '>', now());
                            });
                    });
                });
        }

        if ($list === 'sold') {
            $query->where('is_sold', true)->where('is_deleted', false);
        }

        if ($list === 'drafts') {
            $query->where('is_draft', true)->where('is_deleted', false);
        }

        if ($list === 'deleted') {
            $query->where('is_deleted', true);
        } elseif (
            ! $request->boolean('include_deleted')
            && ! in_array($list, ['all', 'products', '', 'hidden', 'sold', 'drafts', 'pending', 'expired', 'featured', 'special', 'edited'], true)
            && ! $request->boolean('pending_only')
        ) {
            $query->where('is_deleted', false);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('featured_only')) {
            $query->where('is_promoted', true);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('listing_type')) {
            $query->where('listing_type', $request->string('listing_type'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('stock')) {
            $stock = $request->string('stock');
            if ($stock === 'in_stock') {
                $query->where(function (Builder $inner) {
                    $inner->where('type', 'digital')->orWhere('stock', '>', 0);
                });
            } elseif ($stock === 'out_of_stock') {
                $query->where('type', '!=', 'digital')->where('stock', '<=', 0);
            }
        }

        if ($request->filled('updated_from')) {
            $query->whereDate('updated_at', '>=', $request->input('updated_from'));
        }

        if ($request->filled('updated_to')) {
            $query->whereDate('updated_at', '<=', $request->input('updated_to'));
        }

        $search = trim((string) ($request->input('search') ?: $request->input('q', '')));
        if ($search === '') {
            return;
        }

        $term = '%'.$search.'%';
        $query->where(function (Builder $inner) use ($term) {
            $inner->whereHas(
                'translations',
                fn (Builder $translation) => $translation->whereLike('title', $term, caseSensitive: false),
            )->orWhereLike('sku', $term, caseSensitive: false);
        });
    }

    public static function applySort(Builder $query, Request $request): void
    {
        $allowedSorts = ['id', 'created_at', 'updated_at'];
        $sort = $request->string('sort')->toString();
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'updated_at';
        }

        $direction = strtolower($request->string('direction')->toString()) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $direction);

        if ($sort !== 'id') {
            $query->orderByDesc('id');
        }
    }
}
