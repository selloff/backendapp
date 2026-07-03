<?php

namespace App\Modules\Selloff\Cart\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'product_options_snapshot' => ['nullable', 'array'],
            'product_options_summary' => ['nullable', 'string', 'max:500'],
        ];
    }
}
