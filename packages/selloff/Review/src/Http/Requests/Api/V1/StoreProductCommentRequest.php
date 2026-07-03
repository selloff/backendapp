<?php

namespace App\Modules\Selloff\Review\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }
}
