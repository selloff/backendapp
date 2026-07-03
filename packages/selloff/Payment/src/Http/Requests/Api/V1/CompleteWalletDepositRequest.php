<?php

namespace App\Modules\Selloff\Payment\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWalletDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment_settings') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
