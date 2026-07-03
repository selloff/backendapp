<?php

namespace App\Modules\Selloff\Review\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductAbuseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:5', 'max:10000'],
            'report_type' => ['sometimes', 'string', 'in:product,product_unavailable'],
        ];
    }
}
