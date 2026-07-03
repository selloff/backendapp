<?php

namespace App\Modules\Selloff\Payment\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWalletDepositPaystackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:255'],
        ];
    }
}
