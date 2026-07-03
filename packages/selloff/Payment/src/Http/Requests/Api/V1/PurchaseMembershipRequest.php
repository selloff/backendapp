<?php

namespace App\Modules\Selloff\Payment\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'months' => ['required', 'integer', 'in:1,3,6,12'],
            'payment_method' => ['required', 'string', 'in:wallet_balance,bank_transfer,paystack'],
        ];
    }
}
