<?php

namespace App\Modules\Selloff\Catalog\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Legacy seller dashboard product list filters (ProductModel::filterUserProducts).
 *
 * Legacy uses tinyint flags: status 0=pending/1=published, visibility 0=hidden/1=visible.
 * Imported rows may still store the string forms "0"/"1" before repair normalization.
 */
final class LegacyVendorProductListFilter
{
    public static function itemsForSale(Builder $query): void
    {
        $query
            ->where('is_deleted', false)
            ->where(function (Builder $inner): void {
                $inner->where('is_draft', false)->orWhere('is_draft', 0);
            })
            ->where(function (Builder $outer): void {
                $outer->where(function (Builder $active): void {
                    $active->where(function (Builder $inner): void {
                        $inner->where('status', 'published')->orWhere('status', '1');
                    })
                        ->where(function (Builder $inner): void {
                            $inner->where('visibility', 'visible')
                                ->orWhere('visibility', '1');
                        });
                })
                    ->orWhere(function (Builder $edited): void {
                        $edited->where('is_edited', true)
                            ->where(function (Builder $sold): void {
                                $sold->where('is_sold', false)->orWhere('is_sold', 0);
                            });
                    });
            });
    }

    public static function pending(Builder $query): void
    {
        $query
            ->where('is_deleted', false)
            ->where(function (Builder $inner): void {
                $inner->where('is_draft', false)->orWhere('is_draft', 0);
            })
            ->where(function (Builder $inner): void {
                $inner->where('status', 'pending')->orWhere('status', '0');
            });
    }

    public static function hidden(Builder $query): void
    {
        $query
            ->where('is_deleted', false)
            ->where(function (Builder $inner): void {
                $inner->where('is_draft', false)->orWhere('is_draft', 0);
            })
            ->where(function (Builder $inner): void {
                $inner->where('visibility', 'hidden')->orWhere('visibility', '0');
            });
    }

    public static function draft(Builder $query): void
    {
        $query
            ->where('is_deleted', false)
            ->where(function (Builder $inner): void {
                $inner->where('is_draft', true)
                    ->orWhere('is_draft', 1)
                    ->orWhere('status', 'draft');
            });
    }

    public static function sold(Builder $query): void
    {
        $query
            ->where('is_deleted', false)
            ->where(function (Builder $inner): void {
                $inner->where('is_sold', true)->orWhere('is_sold', 1);
            });
    }
}
