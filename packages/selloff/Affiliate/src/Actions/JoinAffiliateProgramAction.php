<?php

namespace App\Modules\Selloff\Affiliate\Actions;

use App\Models\User;
use App\Modules\Selloff\Affiliate\Services\AffiliateProgramSettingsService;
use Illuminate\Validation\ValidationException;

class JoinAffiliateProgramAction
{
    public function __construct(
        private readonly AffiliateProgramSettingsService $program,
    ) {}

    public function execute(User $user, array $payload): User
    {
        if (! filter_var($this->program->programSettings()['status'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'affiliate' => ['Affiliate program is not enabled.'],
            ]);
        }

        if ((int) ($user->is_affiliate ?? 0) === 2) {
            throw ValidationException::withMessages([
                'affiliate' => ['Your affiliate membership has been removed.'],
            ]);
        }

        if ((int) ($user->is_affiliate ?? 0) === 1) {
            return $user;
        }

        $user->update([
            'first_name' => $payload['first_name'] ?? $user->first_name,
            'last_name' => $payload['last_name'] ?? $user->last_name,
            'phone_number' => $payload['phone_number'] ?? $user->phone_number,
            'country_id' => $payload['country_id'] ?? $user->country_id,
            'state_id' => $payload['state_id'] ?? $user->state_id,
            'city_id' => $payload['city_id'] ?? $user->city_id,
            'address' => $payload['address'] ?? $user->address,
            'zip_code' => $payload['zip_code'] ?? $user->zip_code,
            'is_affiliate' => 1,
        ]);

        return $user->fresh();
    }
}
