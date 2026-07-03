<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin_panel') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'q' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'banned'])],
            'email_status' => ['sometimes', 'string', Rule::in(['confirmed', 'unconfirmed'])],
            'is_enable_login' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', Rule::in(['id', 'email', 'first_name', 'last_name', 'created_at'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'show' => ['sometimes', 'integer', Rule::in([15, 30, 60, 100])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
