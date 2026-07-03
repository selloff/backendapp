<?php

namespace App\Modules\Auth\Http\Requests\Api\V1;

use App\Support\TurnstileValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'cf_turnstile_response' => ['sometimes', 'nullable', 'string'],
            'referral_code' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        TurnstileValidator::appendToValidator(
            $validator,
            $this->input('cf_turnstile_response'),
            $this->ip(),
            required: true,
        );
    }
}
