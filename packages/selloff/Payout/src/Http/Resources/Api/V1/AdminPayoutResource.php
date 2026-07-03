<?php

namespace App\Modules\Selloff\Payout\Http\Resources\Api\V1;

use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayoutRequest */
class AdminPayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payoutInfo = is_array($this->payout_info) ? $this->payout_info : [];
        $method = $payoutInfo['payout_method'] ?? $payoutInfo['method'] ?? null;
        $sellerPayoutAccount = $this->when(
            $this->relationLoaded('seller') && $this->seller?->relationLoaded('vendorProfile'),
            fn () => $this->enrichPayoutAccount($this->seller->vendorProfile?->payout_info),
        );

        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'amount' => $this->amount,
            'currency_code' => $this->currency_code,
            'status' => $this->status,
            'payout_method' => $method,
            'payout_info' => $this->payout_info,
            'created_at' => $this->created_at?->toIso8601String(),
            'seller' => $this->whenLoaded('seller', fn () => [
                'id' => $this->seller->id,
                'first_name' => $this->seller->first_name,
                'last_name' => $this->seller->last_name,
                'name' => $this->seller->name,
                'email' => $this->seller->email,
                'slug' => $this->seller->slug,
                'username' => $this->seller->username ?? $this->seller->slug,
                'avatar' => $this->seller->avatar,
            ]),
            'seller_payout_account' => $sellerPayoutAccount,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $account
     * @return array<string, mixed>|null
     */
    private function enrichPayoutAccount(?array $account): ?array
    {
        if (! is_array($account) || $account === []) {
            return $account;
        }

        $countryIds = array_values(array_filter([
            $account['iban_country_id'] ?? null,
            $account['swift_country_id'] ?? null,
            $account['swift_bank_branch_country_id'] ?? null,
        ], fn ($id) => $id !== null && $id !== ''));

        if ($countryIds === []) {
            return $account;
        }

        $countryNames = Country::query()
            ->whereIn('id', $countryIds)
            ->pluck('name', 'id');

        if (isset($account['iban_country_id']) && empty($account['iban_country_name'])) {
            $account['iban_country_name'] = $countryNames[$account['iban_country_id']] ?? null;
        }

        if (isset($account['swift_country_id']) && empty($account['swift_country_name'])) {
            $account['swift_country_name'] = $countryNames[$account['swift_country_id']] ?? null;
        }

        if (isset($account['swift_bank_branch_country_id']) && empty($account['swift_bank_branch_country_name'])) {
            $account['swift_bank_branch_country_name'] = $countryNames[$account['swift_bank_branch_country_id']] ?? null;
        }

        return $account;
    }
}
