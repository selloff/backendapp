<?php

namespace App\Modules\Selloff\Cart\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ApplyShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_method_id' => ['required_without:seller_shipping', 'exists:shipping_methods,id'],
            'seller_shipping' => ['sometimes', 'array', 'min:1'],
            'seller_shipping.*.seller_id' => ['required_with:seller_shipping', 'integer'],
            'seller_shipping.*.shipping_method_id' => ['required_with:seller_shipping', 'exists:shipping_methods,id'],
            'country_id' => ['nullable', 'integer'],
            'state_id' => ['nullable', 'integer'],
        ];
    }
}
