<?php

namespace App\Modules\Selloff\Catalog\Models;

use App\Models\User;
use App\Modules\Selloff\Catalog\Support\LegacyVendorProductListFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sold' => 'boolean',
            'is_verified' => 'boolean',
            'is_affiliate' => 'boolean',
            'is_commission_set' => 'boolean',
            'commission_rate' => 'decimal:2',
            'is_promoted' => 'boolean',
            'top_boost_active' => 'boolean',
            'top_boost_expires_at' => 'datetime',
            'last_bumped_at' => 'datetime',
            'is_special_offer' => 'boolean',
            'special_offer_at' => 'datetime',
            'is_edited' => 'boolean',
            'is_deleted' => 'boolean',
            'is_draft' => 'boolean',
            'promoted_until' => 'datetime',
            'promoted_at' => 'datetime',
            'multiple_sale' => 'boolean',
            'price' => 'decimal:2',
            'price_discounted' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'is_free_product' => 'boolean',
            'shipping_dimensions' => 'array',
            'approved_snapshot' => 'array',
            'pending_changes' => 'array',
            'pending_submitted_at' => 'datetime',
            'last_edit_rejected_at' => 'datetime',
        ];
    }

    protected static function newFactory(): \Database\Factories\ProductFactory
    {
        return \Database\Factories\ProductFactory::new();
    }

    public function topBoostIsActive(): bool
    {
        if (! (bool) $this->top_boost_active) {
            return false;
        }

        $expiresAt = $this->top_boost_expires_at;

        return $expiresAt === null || $expiresAt->isFuture();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Selloff\Location\Models\Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Selloff\Location\Models\State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Selloff\Location\Models\City::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(\App\Modules\Selloff\Media\Models\ProductImage::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function digitalFiles(): HasMany
    {
        return $this->hasMany(DigitalFile::class);
    }

    public function licenseKeys(): HasMany
    {
        return $this->hasMany(ProductLicenseKey::class);
    }

    public function customFieldProducts(): HasMany
    {
        return $this->hasMany(CustomFieldProduct::class);
    }

    /**
     * Products eligible for marketplace catalog and homepage surfaces.
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('is_active', true)
            ->where(function (Builder $visibility): void {
                $visibility->where('visibility', 'visible')
                    ->orWhere('visibility', '1');
            });
    }

    /**
     * Admin "Items for sale" / latest approved products (legacy list=products).
     */
    public function scopeAdminItemsForSale(Builder $query): Builder
    {
        return $query
            ->where('is_deleted', false)
            ->where('is_draft', false)
            ->where('is_verified', true)
            ->where('status', 'published')
            ->where(function (Builder $inner): void {
                $inner->where('visibility', 'visible')->orWhere('visibility', '1');
            });
    }

    /**
     * Vendor dashboard "Items for sale" (legacy seller list default: status=1, visibility=1, not draft).
     */
    public function scopeVendorItemsForSale(Builder $query): Builder
    {
        LegacyVendorProductListFilter::itemsForSale($query);

        return $query;
    }

    /**
     * Vendor dashboard pending queue (legacy seller list pending: status=0, not draft).
     */
    public function scopeVendorPendingItems(Builder $query): Builder
    {
        LegacyVendorProductListFilter::pending($query);

        return $query;
    }

    /**
     * Vendor dashboard hidden items (legacy seller list hidden: visibility=0, not draft).
     */
    public function scopeVendorHiddenItems(Builder $query): Builder
    {
        LegacyVendorProductListFilter::hidden($query);

        return $query;
    }

    /**
     * Vendor dashboard drafts (legacy seller list draft: is_draft=1).
     */
    public function scopeVendorDraftItems(Builder $query): Builder
    {
        LegacyVendorProductListFilter::draft($query);

        return $query;
    }

    /**
     * Vendor dashboard sold items (legacy seller list sold: is_sold=1).
     */
    public function scopeVendorSoldItems(Builder $query): Builder
    {
        LegacyVendorProductListFilter::sold($query);

        return $query;
    }

    /**
     * Admin moderation queue — awaiting first approval (legacy list=pending_products).
     */
    public function scopeAdminPendingModeration(Builder $query): Builder
    {
        return $query
            ->where('is_verified', false)
            ->where('is_deleted', false)
            ->where('is_draft', false)
            ->where('is_edited', false)
            ->whereNotIn('status', ['hidden', 'draft'])
            ->where(function (Builder $inner): void {
                $inner->whereNull('reject_reason')->orWhere('reject_reason', '');
            });
    }
}
