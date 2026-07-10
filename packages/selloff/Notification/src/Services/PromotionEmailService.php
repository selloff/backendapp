<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\MonetizationMailViewDataFactory;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;

class PromotionEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly MonetizationMailViewDataFactory $viewData,
    ) {}

    /**
     * @param  array<string, mixed>  $quote
     */
    public function queueFeaturedPromotion(Product $product, array $quote, float $amount): ?EmailJob
    {
        return $this->queuePromotion(
            $product,
            $quote,
            $amount,
            TransactionalEmailType::PROMOTION_APPLIED,
            'Your product promotion is active',
            'Your listing is now featured on Selloff.',
            'promotion-applied',
        );
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    public function queueVipBoost(Product $product, array $quote, float $amount): ?EmailJob
    {
        return $this->queuePromotion(
            $product,
            $quote,
            $amount,
            TransactionalEmailType::VIP_BOOST_APPLIED,
            'Your TOP Ad boost is active',
            'Your listing now has boosted visibility in search results.',
            'promotion-applied',
        );
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function queuePromotion(
        Product $product,
        array $quote,
        float $amount,
        string $type,
        string $subject,
        string $summary,
        string $template,
    ): ?EmailJob {
        $product->loadMissing('vendor');
        $vendor = $product->vendor;
        $to = trim((string) ($vendor?->email ?? ''));

        if ($to === '') {
            return null;
        }

        return $this->email->queue(
            $type,
            $to,
            $this->viewData->forPromotion($product, $quote, $amount, $subject, $summary),
            subject: $subject,
            template: $template,
        );
    }
}
