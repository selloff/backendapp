<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin_panel') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'slug')->ignore($userId)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'about_me' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'password' => ['sometimes', 'string', Password::defaults(), 'confirmed'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'storage_avatar' => ['sometimes', 'nullable', 'string', 'max:50'],
            'country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['sometimes', 'nullable', 'integer', 'exists:states,id'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'exists:cities,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'zip_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'social_media_data' => ['sometimes', 'nullable', 'array'],
            'social_media_data.*' => ['nullable', 'string', 'max:1000'],
            'commission_mode' => ['sometimes', 'string', Rule::in(['default', 'custom', 'none'])],
            'commission_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99.99'],
            'is_enable_login' => ['sometimes', 'boolean'],
            'is_disable' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
