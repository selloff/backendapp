<?php

namespace App\Modules\Selloff\Order\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaystackCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'string'],
        ];
    }
}
