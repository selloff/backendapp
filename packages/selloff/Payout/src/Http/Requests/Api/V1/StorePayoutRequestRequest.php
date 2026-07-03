<?php

namespace App\Modules\Selloff\Payout\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePayoutRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vendor') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'payout_info' => ['nullable', 'array'],
        ];
    }
}
