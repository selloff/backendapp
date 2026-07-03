<?php

namespace App\Modules\Selloff\Order\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GuestCheckoutRequest extends FormRequest
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
            'guest_email' => ['required', 'email', 'max:255'],
            'payment_method' => ['required', 'string', 'max:100'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'shipping_data' => ['nullable', 'array'],
            'shipping_data.name' => ['nullable', 'string', 'max:255'],
            'shipping_data.first_name' => ['nullable', 'string', 'max:255'],
            'shipping_data.last_name' => ['nullable', 'string', 'max:255'],
            'shipping_data.sFirstName' => ['nullable', 'string', 'max:255'],
            'shipping_data.sLastName' => ['nullable', 'string', 'max:255'],
            'shipping_data.email' => ['nullable', 'email', 'max:255'],
            'shipping_data.sEmail' => ['nullable', 'email', 'max:255'],
            'shipping_data.phone' => ['nullable', 'string', 'max:50'],
            'shipping_data.sPhoneNumber' => ['nullable', 'string', 'max:50'],
            'shipping_data.address' => ['nullable', 'string', 'max:500'],
            'shipping_data.city' => ['nullable', 'string', 'max:255'],
            'shipping_data.country' => ['nullable', 'string', 'max:255'],
            'affiliate_link_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
