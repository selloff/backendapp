<?php

namespace App\Modules\Selloff\Escrow\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;

class EscrowPricingService
{
    /**
     * @return array{item_price: float, commission_amount: float, commission_rate: float, seller_amount: float, delivery_cost: float, total_amount: float}
     */
    public function resolvePricing(EscrowTransaction $transaction, ?Product $product = null): array
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $product ??= $this->resolveProduct($transaction, $metadata);

        $columnAmount = (float) ($transaction->amount ?? 0);
        $productPrice = $product !== null ? (float) $product->price : 0.0;
        $metadataItemPrice = isset($metadata['item_price']) ? (float) $metadata['item_price'] : 0.0;

        $rawItemPrice = $columnAmount > 0
            ? $columnAmount
            : ($metadataItemPrice > 0 ? $metadataItemPrice : 0.0);

        [$itemPrice, $scale] = $this->normalizeEscrowItemPrice($rawItemPrice, $productPrice);

        $commissionAmount = (float) ($transaction->commission_amount ?? $metadata['commission_amount'] ?? $metadata['commission'] ?? 0);
        $deliveryCost = (float) ($transaction->delivery_cost ?? $metadata['delivery_cost'] ?? 0);
        $commissionRate = (float) ($metadata['commission_rate'] ?? $product?->category?->escrow_commission_rate ?? 0);
        $sellerAmount = (float) ($transaction->seller_amount ?? $metadata['amount_seller_received'] ?? max(0, $rawItemPrice - $commissionAmount));

        if ($scale < 1.0) {
            $commissionAmount = round($commissionAmount * $scale, 2);
            $deliveryCost = round($deliveryCost * $scale, 2);
            $sellerAmount = round($sellerAmount * $scale, 2);
        }

        if ($sellerAmount <= 0 && $itemPrice > 0) {
            $sellerAmount = max(0, round($itemPrice - $commissionAmount, 2));
        }

        $totalAmount = $itemPrice + $commissionAmount + $deliveryCost;

        return [
            'item_price' => round($itemPrice, 2),
            'commission_amount' => round($commissionAmount, 2),
            'commission_rate' => $commissionRate,
            'seller_amount' => round($sellerAmount, 2),
            'delivery_cost' => round($deliveryCost, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function resolveProduct(EscrowTransaction $transaction, array $metadata = []): ?Product
    {
        $product = $transaction->relationLoaded('product') ? $transaction->product : null;
        if ($product !== null) {
            return $product;
        }

        $metadata = $metadata ?: (is_array($transaction->metadata) ? $transaction->metadata : []);
        $productId = $transaction->product_id ?? (int) ($metadata['item_id'] ?? 0);
        if ($productId <= 0) {
            return null;
        }

        return Product::query()
            ->with(['translations', 'category', 'images'])
            ->find($productId);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function normalizeEscrowItemPrice(float $storedAmount, float $productPrice): array
    {
        if ($storedAmount <= 0) {
            return [$productPrice > 0 ? $productPrice : 0.0, 1.0];
        }

        if ($productPrice > 0 && $this->amountMatchesCatalogPrice($storedAmount, $productPrice)) {
            return [$storedAmount, 1.0];
        }

        if ($productPrice > 0 && $this->isInflatedEscrowAmount($storedAmount, $productPrice)) {
            return [$productPrice, $productPrice / $storedAmount];
        }

        if ($productPrice <= 0 && $this->looksLikeKoboStorage($storedAmount)) {
            return [round($storedAmount / 100, 2), 0.01];
        }

        return [$storedAmount, 1.0];
    }

    private function amountMatchesCatalogPrice(float $amount, float $productPrice): bool
    {
        if ($productPrice <= 0) {
            return false;
        }

        return abs($amount - $productPrice) <= max(0.01, $productPrice * 0.01);
    }

    private function isInflatedEscrowAmount(float $amount, float $productPrice): bool
    {
        if ($productPrice <= 0 || $amount <= 0) {
            return false;
        }

        $ratio = $amount / $productPrice;

        return $ratio >= 75 && $ratio <= 125;
    }

    private function looksLikeKoboStorage(float $amount): bool
    {
        return $amount >= 10000 && abs(fmod($amount, 100)) < 0.001;
    }
}
