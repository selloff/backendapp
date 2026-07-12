<?php

namespace App\Modules\Selloff\Catalog\Http\Requests\Api\V1;

use App\Modules\Selloff\Catalog\Http\Requests\Api\V1\Concerns\ValidatesProductBrand;
use App\Modules\Selloff\Catalog\Http\Requests\Api\V1\Concerns\ValidatesProductVendorPricing;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\ListingTypePlatformValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    use ValidatesProductBrand;
    use ValidatesProductVendorPricing;

    public function authorize(): bool
    {
        return $this->user()?->can('products') ?? false;
    }

    public function withValidator(Validator $validator): void
    {
        $this->configureProductBrandValidation($validator);
        $this->configureProductVendorPricingValidation($validator);
        app(ListingTypePlatformValidator::class)->configure($validator);

        $validator->after(function (Validator $validator): void {
            if (! $this->has('images')) {
                return;
            }

            $product = $this->route('product');
            if (! $product instanceof Product) {
                return;
            }

            $isPublished = ! $product->is_draft
                && $product->status !== 'draft'
                && $product->status !== 'pending'
                && (string) $product->status !== '0';
            $images = $this->input('images', []);

            if ($isPublished && count($images) < 1) {
                $validator->errors()->add('images', 'Published listings must have at least one image.');
            }
        });
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:physical,digital'],
            'listing_type' => ['nullable', 'string', 'in:ordinary_listing,sell_on_site,bidding,license_key'],
            ...$this->productVendorPricingRules(priceRequired: false),
            'price_discounted' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:draft,published,hidden'],
            'visibility' => ['nullable', 'in:visible,hidden'],
            'is_active' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*.path' => ['required_with:images', 'string', 'max:500'],
            'images.*.disk' => ['nullable', 'string', 'max:50'],
            'images.*.variant_paths' => ['nullable', 'array'],
            'images.*.variant_paths.small' => ['nullable', 'string', 'max:500'],
            'images.*.variant_paths.default' => ['nullable', 'string', 'max:500'],
            'images.*.variant_paths.big' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array', 'max:15'],
            'tags.*' => ['string', 'max:100'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'state_id' => ['nullable', 'exists:states,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'zip_code' => ['nullable', 'string', 'max:50'],
            'shipping_dimensions' => ['nullable', 'array'],
            'shipping_dimensions.weight' => ['nullable', 'numeric', 'min:0'],
            'shipping_dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'shipping_dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'shipping_dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'video_path' => ['nullable', 'string', 'max:500'],
            'video_disk' => ['nullable', 'string', 'max:50'],
            'audio_path' => ['nullable', 'string', 'max:500'],
            'audio_disk' => ['nullable', 'string', 'max:50'],
            'options' => ['nullable', 'array'],
            'options.*.name' => ['required_with:options', 'string', 'max:255'],
            'options.*.values' => ['nullable', 'array'],
            'options.*.values.*' => ['string', 'max:255'],
            'digital_files' => ['nullable', 'array'],
            'digital_files.*.file_name' => ['required_with:digital_files', 'string', 'max:500'],
            'digital_files.*.storage' => ['nullable', 'string', 'max:50'],
            'license_keys' => ['nullable', 'array'],
            'license_keys.*' => ['string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*.custom_field_id' => ['required_with:custom_fields', 'integer', 'exists:custom_fields,id'],
            'custom_fields.*.field_value' => ['nullable', 'string', 'max:1000'],
            'custom_fields.*.custom_field_option_id' => ['nullable', 'integer', 'exists:custom_field_options,id'],
        ];
    }
}
