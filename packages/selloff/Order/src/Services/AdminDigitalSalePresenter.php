<?php

namespace App\Modules\Selloff\Order\Services;

use App\Modules\Selloff\Order\Models\DigitalSale;

class AdminDigitalSalePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(DigitalSale $sale): array
    {
        $lineItem = $sale->order?->items?->firstWhere('product_id', $sale->product_id);
        $price = $sale->price;
        if ((float) $price <= 0) {
            $price = $lineItem?->total_price ?? $sale->order?->price_total ?? 0;
        }

        $currency = $sale->currency_code ?: $sale->order?->currency_code;

        return [
            'id' => $sale->id,
            'purchase_code' => $sale->purchase_code,
            'license_key' => $sale->license_key,
            'order_id' => $sale->order_id,
            'order_number' => $sale->order?->order_number,
            'product_id' => $sale->product_id,
            'product_title' => $sale->product_title ?? $sale->product?->translations->first()?->title,
            'product_slug' => $sale->product?->slug,
            'price' => $price,
            'currency_code' => $currency,
            'buyer' => $sale->buyer ? [
                'id' => $sale->buyer->id,
                'name' => $sale->buyer->name,
                'email' => $sale->buyer->email,
                'slug' => $sale->buyer->slug,
            ] : null,
            'seller' => $sale->seller ? [
                'id' => $sale->seller->id,
                'name' => $sale->seller->name,
                'email' => $sale->seller->email,
                'slug' => $sale->seller->slug,
            ] : null,
            'purchase_date' => $sale->created_at,
            'created_at' => $sale->created_at,
        ];
    }
}
