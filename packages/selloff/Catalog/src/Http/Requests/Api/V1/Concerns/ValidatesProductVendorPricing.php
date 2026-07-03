<?php

namespace App\Modules\Selloff\Catalog\Http\Requests\Api\V1\Concerns;

use App\Modules\Selloff\Catalog\Support\ProductVendorWriteNormalizer;
use Illuminate\Validation\Validator;

trait ValidatesProductVendorPricing
{
    protected function configureProductVendorPricingValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $normalizer = app(ProductVendorWriteNormalizer::class);
            if (! $normalizer->vatEnabled()) {
                return;
            }

            $noVat = (bool) $this->input('no_vat', false);
            $vatRate = $this->input('vat_rate');

            if (! $noVat && $vatRate !== null && $vatRate !== '' && ((float) $vatRate < 0 || (float) $vatRate > 100)) {
                $validator->errors()->add('vat_rate', 'VAT rate must be between 0 and 100.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function productVendorPricingRules(bool $priceRequired = true): array
    {
        return [
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'no_vat' => ['sometimes', 'boolean'],
            'is_free_product' => ['sometimes', 'boolean'],
            'delivery_time_option_id' => ['nullable', 'integer', 'exists:delivery_time_options,id'],
            'price' => [$priceRequired ? 'required' : 'sometimes', 'numeric', 'min:0'],
        ];
    }
}
