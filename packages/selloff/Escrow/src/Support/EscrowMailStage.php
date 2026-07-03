<?php

namespace App\Modules\Selloff\Escrow\Support;

enum EscrowMailStage: string
{
    case BuyerAgreement = 'buyer-agreement';
    case SellerAgreement = 'seller-agreement';
    case AdminEscrowInitiation = 'admin-escrow-initiation';
    case PaymentLink = 'payment-link';
    case BuyerPaidBuyer = 'buyer-paid-buyer';
    case BuyerPaidSeller = 'buyer-paid-seller';
    case ItemShippedBuyer = 'item-shipped-buyer';
    case ItemReceivedSeller = 'item-received-seller';
    case AdminItemShipped = 'admin-item-shipped';
    case AdminItemReceived = 'admin-item-received';

    public function ctaColor(): string
    {
        return match ($this) {
            self::BuyerAgreement, self::SellerAgreement => (string) config('selloff.mail_branding.escrow_cta', '#D75A07'),
            self::PaymentLink => (string) config('selloff.mail_branding.success_cta', '#008D59'),
            self::BuyerPaidSeller => (string) config('selloff.mail_branding.success_cta', '#579A00'),
            self::ItemShippedBuyer => (string) config('selloff.mail_branding.confirm_cta', '#68B503'),
            default => (string) config('selloff.mail_branding.primary', '#0075bb'),
        };
    }

    public function htmlView(): string
    {
        return 'selloff-escrow::mail.stages.'.$this->value;
    }

    public function textView(): string
    {
        return 'selloff-escrow::mail.plain.'.$this->value;
    }
}
