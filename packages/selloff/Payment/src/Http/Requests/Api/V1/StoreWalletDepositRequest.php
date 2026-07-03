<?php

namespace App\Modules\Selloff\Payment\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:bank_transfer,stripe,demo,paystack'],
        ];
    }
}
