<?php

namespace App\Modules\Selloff\Catalog\Http\Requests\Api\V1\Concerns;

use App\Modules\Selloff\Catalog\Services\BrandSettingsService;
use Illuminate\Validation\Validator;

trait ValidatesProductBrand
{
    protected function configureProductBrandValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $settings = app(BrandSettingsService::class)->all();

            if (! $settings['brand_status']) {
                return;
            }

            if ($settings['is_brand_optional']) {
                return;
            }

            $status = (string) $this->input('status', 'published');
            if ($status !== 'published') {
                return;
            }

            $brandId = (int) $this->input('brand_id', 0);
            if ($brandId > 0) {
                return;
            }

            $validator->errors()->add('brand_id', 'Brand is required when publishing a product.');
        });
    }
}
