<?php

namespace App\Modules\Selloff\Escrow\Support;

class EscrowMailViewData
{
    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<string, string>  $branding
     * @param  array<string, string>  $bank
     */
    public function __construct(
        public readonly EscrowMailStage $stage,
        public readonly string $subject,
        public readonly string $recipientName,
        public readonly string $productTitle,
        public readonly string $productUrl,
        public readonly ?string $productImageUrl,
        public readonly array $pricing,
        public readonly string $currencyCode,
        public readonly ?string $agreementUrl,
        public readonly ?string $paymentUrl,
        public readonly ?string $deliveryAddress,
        public readonly string $ref,
        public readonly ?string $buyerName,
        public readonly ?string $buyerPhone,
        public readonly ?string $buyerUsername,
        public readonly ?string $sellerName,
        public readonly ?string $sellerPhone,
        public readonly ?string $sellerUsername,
        public readonly array $branding,
        public readonly array $bank,
        public readonly bool $deliveryCostPending = false,
    ) {}

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2).' '.$this->currencyCode;
    }

    public function ctaColor(): string
    {
        return $this->stage->ctaColor();
    }
}
