<?php

namespace App\Modules\Selloff\Cart\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MergeGuestCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_token' => ['required', 'string', 'max:100'],
        ];
    }
}
