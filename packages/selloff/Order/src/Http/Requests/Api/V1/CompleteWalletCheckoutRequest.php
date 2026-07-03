<?php

namespace App\Modules\Selloff\Order\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWalletCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'uuid'],
        ];
    }
}
