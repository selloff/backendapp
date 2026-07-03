<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryRole = $this->relationLoaded('roles') ? $this->roles->first() : null;

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'username' => $this->username,
            'slug' => $this->slug,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'storage_avatar' => $this->storage_avatar,
            'is_enable_login' => (bool) $this->is_enable_login,
            'is_disable' => (bool) $this->is_disable,
            'is_banned' => (bool) $this->is_banned,
            'email_confirmed' => $this->email_verified_at !== null,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'membership_plan_title' => $this->membership_plan_title ?? null,
            'is_affiliate' => (int) ($this->is_affiliate ?? 0),
            'phone_number' => $this->phone_number,
            'about_me' => $this->about_me,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
            'address' => $this->address,
            'zip_code' => $this->zip_code,
            'social_media_data' => $this->when(
                $this->relationLoaded('vendorProfile') && $this->vendorProfile?->social_media_data,
                fn () => $this->vendorProfile?->social_media_data,
                $this->social_media_data,
            ),
            'is_commission_set' => $this->relationLoaded('vendorProfile')
                ? (bool) ($this->vendorProfile?->is_commission_set ?? false)
                : false,
            'commission_rate' => $this->relationLoaded('vendorProfile')
                ? ($this->vendorProfile?->commission_rate !== null ? (float) $this->vendorProfile->commission_rate : null)
                : null,
            'account_delete_requested_at' => $this->account_delete_requested_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'primary_role' => $primaryRole ? [
                'id' => $primaryRole->id,
                'name' => $primaryRole->name,
                'is_super_admin' => $primaryRole->name === 'super-admin',
                'is_admin' => in_array($primaryRole->name, ['super-admin', 'admin'], true),
                'is_vendor' => $primaryRole->name === 'vendor',
            ] : null,
        ];
    }
}
