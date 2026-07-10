<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\Selloff\Referral\Services\ReferralProgramSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $referralProfile = $this->relationLoaded('referralProfile')
            ? $this->referralProfile
            : null;
        $referralProgram = app(ReferralProgramSettingsService::class)->programSettings();

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'email_verified' => $this->email_verified_at !== null,
            'avatar' => $this->avatar,
            'wallet_balance' => (float) ($this->wallet_balance ?? 0),
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
            'selected_currency_code' => $this->selected_currency_code,
            'is_affiliate' => (int) ($this->is_affiliate ?? 0),
            'referral_code' => $referralProfile?->referral_code,
            'referral_points' => (int) ($referralProfile?->referral_points ?? 0),
            'referral_program_enabled' => (bool) ($referralProgram['status'] ?? false),
            'send_email_new_message' => (bool) ($this->send_email_new_message ?? true),
        ];
    }
}
