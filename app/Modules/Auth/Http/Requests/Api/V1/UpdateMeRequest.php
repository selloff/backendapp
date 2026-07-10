<?php

namespace App\Modules\Auth\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$this->user()?->id],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'social_media_data' => ['sometimes', 'nullable', 'array'],
            'social_media_data.facebook' => ['nullable', 'string', 'max:500'],
            'social_media_data.twitter' => ['nullable', 'string', 'max:500'],
            'social_media_data.instagram' => ['nullable', 'string', 'max:500'],
            'social_media_data.website' => ['nullable', 'string', 'max:500'],
            'social_media_data.whatsapp' => ['nullable', 'string', 'max:500'],
            'social_media_data.whatsapp_url' => ['nullable', 'string', 'max:500'],
            'country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['sometimes', 'nullable', 'integer', 'exists:states,id'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'exists:cities,id'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'selected_currency_code' => ['sometimes', 'nullable', 'string', 'max:10', 'exists:currencies,code'],
            'send_email_new_message' => ['sometimes', 'boolean'],
        ];
    }
}
