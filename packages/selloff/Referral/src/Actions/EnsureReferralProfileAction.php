<?php

namespace App\Modules\Selloff\Referral\Actions;

use App\Models\User;
use App\Modules\Selloff\User\Models\ReferralProfile;
use Illuminate\Support\Str;

class EnsureReferralProfileAction
{
    public function execute(User $user): ReferralProfile
    {
        $profile = ReferralProfile::query()->where('user_id', $user->id)->first();

        if ($profile) {
            return $profile;
        }

        return ReferralProfile::query()->create([
            'user_id' => $user->id,
            'referral_code' => $this->generateUniqueCode(),
            'referral_points' => 0,
        ]);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (ReferralProfile::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
