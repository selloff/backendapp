<?php

namespace App\Modules\Selloff\Escrow\Services;

use App\Modules\Selloff\Escrow\Mail\EscrowStageMail;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowMailStage;
use App\Modules\Selloff\Notification\Services\EmailOptionGate;
use App\Modules\Selloff\Notification\Services\PlatformMailService;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;

class EscrowNotificationService
{
    public function __construct(
        private readonly EscrowMailViewDataFactory $mailData,
        private readonly EmailOptionGate $gate,
        private readonly PlatformMailService $mail,
    ) {}

    public function sendBuyerAgreement(EscrowTransaction $transaction): void
    {
        $title = $this->productTitle($transaction);
        $subject = "Escrow purchase agreement — {$title}";

        $this->dispatch(
            (string) $transaction->buyer_email,
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::BuyerAgreement,
                $subject,
                $this->buyerName($transaction),
                agreementUrl: $this->agreementUrl($transaction->buyer_agreement_token),
                deliveryCostPending: true,
            ),
        );
    }

    public function sendSellerAgreement(EscrowTransaction $transaction): void
    {
        $title = $this->productTitle($transaction);
        $subject = "Escrow sale agreement — {$title}";

        $this->dispatch(
            (string) $transaction->seller_email,
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::SellerAgreement,
                $subject,
                $this->sellerName($transaction),
                agreementUrl: $this->agreementUrl($transaction->seller_agreement_token),
                deliveryCostPending: true,
            ),
        );
    }

    public function sendBuyerPaymentLink(EscrowTransaction $transaction, string $paymentUrl): void
    {
        $title = $this->productTitle($transaction);
        $subject = "Escrow payment link — {$title}";

        $this->dispatch(
            (string) $transaction->buyer_email,
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::PaymentLink,
                $subject,
                $this->buyerName($transaction),
                paymentUrl: $paymentUrl,
            ),
        );
    }

    public function sendBuyerPaidConfirmation(EscrowTransaction $transaction): void
    {
        $title = $this->productTitle($transaction);
        $subject = "Escrow payment received — {$title}";

        $this->dispatch(
            (string) $transaction->buyer_email,
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::BuyerPaidBuyer,
                $subject,
                $this->buyerName($transaction),
            ),
        );
    }

    public function sendSellerPaymentReceived(EscrowTransaction $transaction): void
    {
        $title = $this->productTitle($transaction);
        $subject = "Escrow buyer paid — {$title}";

        $this->dispatch(
            (string) $transaction->seller_email,
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::BuyerPaidSeller,
                $subject,
                $this->sellerName($transaction),
                agreementUrl: $this->agreementUrl($transaction->seller_agreement_token),
            ),
        );
    }

    public function sendItemShippedToBuyer(EscrowTransaction $transaction): void
    {
        $title = $this->productTitle($transaction);
        $subject = "Escrow item shipped — {$title}";

        $this->dispatch(
            (string) $transaction->buyer_email,
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::ItemShippedBuyer,
                $subject,
                $this->buyerName($transaction),
                agreementUrl: $this->agreementUrl($transaction->buyer_agreement_token),
            ),
        );
    }

    public function sendItemReceivedToSeller(EscrowTransaction $transaction): void
    {
        $title = $this->productTitle($transaction);
        $subject = "Escrow item received — {$title}";

        $this->dispatch(
            (string) $transaction->seller_email,
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::ItemReceivedSeller,
                $subject,
                $this->sellerName($transaction),
            ),
        );
    }

    public function sendNewEscrowToAdmin(EscrowTransaction $transaction): void
    {
        $title = $this->productTitle($transaction);
        $ref = (string) $transaction->ref;
        $subject = "New Escrow Agreement Initiated [Ref: {$ref}]: {$title}";

        $this->dispatch(
            $this->adminEmail(),
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::AdminEscrowInitiation,
                $subject,
                'Escrow Admin',
                deliveryCostPending: true,
            ),
        );
    }

    public function sendItemShippedToAdmin(EscrowTransaction $transaction): void
    {
        $title = $this->productTitle($transaction);
        $ref = (string) $transaction->ref;
        $subject = "Escrow item shipped [Ref: {$ref}]: {$title}";

        $this->dispatch(
            $this->adminEmail(),
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::AdminItemShipped,
                $subject,
                'Escrow Admin',
            ),
        );
    }

    public function sendItemReceivedToAdmin(EscrowTransaction $transaction): void
    {
        $title = $this->productTitle($transaction);
        $ref = (string) $transaction->ref;
        $subject = "Escrow item received [Ref: {$ref}]: {$title}";

        $this->dispatch(
            $this->adminEmail(),
            $this->mailData->forStage(
                $transaction,
                EscrowMailStage::AdminItemReceived,
                $subject,
                'Escrow Admin',
            ),
        );
    }

    private function adminEmail(): string
    {
        return (string) config('selloff.escrow_admin_email', 'escrow@selloff.ng');
    }

    private function productTitle(EscrowTransaction $transaction): string
    {
        $transaction->loadMissing(['product.translations']);
        $product = $transaction->product;

        return (string) ($product?->translations->firstWhere('locale', 'en')?->title
            ?? $product?->translations->first()?->title
            ?? $transaction->ref
            ?? 'your item');
    }

    private function buyerName(EscrowTransaction $transaction): string
    {
        $transaction->loadMissing('buyer');
        $name = trim((string) ($transaction->buyer?->name ?? ''));

        return $name !== '' ? $name : (string) ($transaction->buyer_email ?? 'Buyer');
    }

    private function sellerName(EscrowTransaction $transaction): string
    {
        $transaction->loadMissing('seller');
        $name = trim((string) ($transaction->seller?->name ?? ''));

        return $name !== '' ? $name : (string) ($transaction->seller_email ?? 'Seller');
    }

    private function agreementUrl(?string $token): string
    {
        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        return "{$base}/escrow/{$token}";
    }

    private function dispatch(string $to, \App\Modules\Selloff\Escrow\Support\EscrowMailViewData $data): void
    {
        if ($to === '' || ! $this->gate->isEnabled(TransactionalEmailType::ESCROW)) {
            return;
        }

        $this->mail->sendMailable(new EscrowStageMail($data), $to);
    }
}
