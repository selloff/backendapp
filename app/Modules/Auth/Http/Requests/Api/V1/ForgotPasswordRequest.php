<?php

namespace App\Modules\Auth\Http\Requests\Api\V1;

use App\Support\TurnstileValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'cf_turnstile_response' => ['sometimes', 'nullable', 'string'],
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
