<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'first_name',
    'last_name',
    'slug',
    'username',
    'email',
    'password',
    'avatar',
    'facebook_id',
    'google_id',
    'vk_id',
    'storage_avatar',
    'wallet_balance',
    'vendor_balance_adjustment',
    'is_banned',
    'is_affiliate',
    'phone_number',
    'about_me',
    'show_rss_feeds',
    'last_seen_at',
    'shop_opening_status',
    'vendor_documents',
    'shop_request_date',
    'shop_opening_rejection_reason',
    'is_enable_login',
    'is_disable',
    'account_delete_requested_at',
    'email_verified_at',
    'social_media_data',
    'country_id',
    'state_id',
    'city_id',
    'address',
    'zip_code',
    'selected_currency_code',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_enable_login' => 'boolean',
            'is_disable' => 'boolean',
            'account_delete_requested_at' => 'datetime',
            'is_banned' => 'boolean',
            'is_affiliate' => 'integer',
            'show_rss_feeds' => 'boolean',
            'wallet_balance' => 'decimal:2',
            'vendor_balance_adjustment' => 'decimal:2',
            'vendor_documents' => 'array',
            'social_media_data' => 'array',
            'shop_request_date' => 'datetime',
            'shop_opening_status' => 'integer',
        ];
    }

    public function referralProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Selloff\User\Models\ReferralProfile::class);
    }

    public function vendorProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Selloff\User\Models\VendorProfile::class);
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Selloff\Catalog\Models\Product::class, 'vendor_id');
    }

    public function state(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Selloff\Location\Models\State::class);
    }

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Selloff\Location\Models\City::class);
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
