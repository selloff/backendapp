<?php

namespace App\Http\Resources\Api\V1\Mobile;

use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class MobileUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->loadMissing('vendorProfile');

        return [
            'id' => $this->id,
            'username' => $this->username ?? $this->slug,
            'slug' => $this->slug,
            'email' => $this->email,
            'email_status' => $this->email_verified_at ? 1 : 0,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone_number' => $this->phone_number,
            'avatar' => $this->avatar,
            'avatar_url' => MediaUrl::resolve($this->avatar),
            'shop_name' => $this->vendorProfile?->shop_name,
            'wallet' => (float) $this->wallet_balance,
            'wallet_balance' => (float) $this->wallet_balance,
            'is_verified_seller' => (bool) ($this->vendorProfile?->is_verified_seller ?? false),
            'banned' => (bool) $this->is_banned,
            'roles' => $this->getRoleNames(),
        ];
    }
}
