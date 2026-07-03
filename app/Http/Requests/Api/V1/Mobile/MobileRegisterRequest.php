<?php

namespace App\Http\Requests\Api\V1\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class MobileRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fullname' => ['sometimes', 'string', 'max:255'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'referral_code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'referred_by_code' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array{first_name: string, last_name: string}
     */
    public function nameParts(): array
    {
        if ($this->filled('first_name') && $this->filled('last_name')) {
            return [
                'first_name' => $this->string('first_name')->toString(),
                'last_name' => $this->string('last_name')->toString(),
            ];
        }

        $parts = preg_split('/\s+/', trim($this->string('fullname')->toString()), 2) ?: [];

        return [
            'first_name' => $parts[0] ?? 'Member',
            'last_name' => $parts[1] ?? 'User',
        ];
    }
}
