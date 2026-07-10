<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Modules\Selloff\Order\Models\QuoteRequest;

class QuoteEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
    ) {}

    public function queueRequest(QuoteRequest $quote): ?EmailJob
    {
        $quote->loadMissing(['seller', 'product.translations']);
        $sellerEmail = trim((string) ($quote->seller?->email ?? ''));

        if ($sellerEmail === '') {
            return null;
        }

        return $this->queueMain(
            TransactionalEmailType::QUOTE_REQUEST,
            $sellerEmail,
            'Quote request',
            'You have a new quote request.<br>Quote: <strong>#'.e((string) $quote->id).'</strong>',
            $this->vendorQuoteUrl(),
            'View details',
        );
    }

    public function queueQuoted(QuoteRequest $quote): ?EmailJob
    {
        $quote->loadMissing(['buyer']);
        $buyerEmail = trim((string) ($quote->buyer?->email ?? ''));

        if ($buyerEmail === '') {
            return null;
        }

        $price = $quote->quoted_price !== null
            ? '<br>Quoted price: <strong>'.e((string) $quote->quoted_price).'</strong>'
            : '';

        return $this->queueMain(
            TransactionalEmailType::QUOTE_SUBMITTED,
            $buyerEmail,
            'Quote request',
            'Your quote request has been replied to.<br>Quote: <strong>#'.e((string) $quote->id).'</strong>'.$price,
            $this->buyerQuoteUrl(),
            'View details',
        );
    }

    public function queueAccepted(QuoteRequest $quote): ?EmailJob
    {
        $quote->loadMissing(['seller']);
        $sellerEmail = trim((string) ($quote->seller?->email ?? ''));

        if ($sellerEmail === '') {
            return null;
        }

        return $this->queueMain(
            TransactionalEmailType::QUOTE_ACCEPTED,
            $sellerEmail,
            'Quote request',
            'Your quote was accepted.<br>Quote: <strong>#'.e((string) $quote->id).'</strong>',
            $this->vendorQuoteUrl(),
            'View details',
        );
    }

    public function queueRejected(QuoteRequest $quote): ?EmailJob
    {
        $quote->loadMissing(['seller']);
        $sellerEmail = trim((string) ($quote->seller?->email ?? ''));

        if ($sellerEmail === '') {
            return null;
        }

        return $this->queueMain(
            TransactionalEmailType::QUOTE_REJECTED,
            $sellerEmail,
            'Quote request',
            'Your quote was rejected.<br>Quote: <strong>#'.e((string) $quote->id).'</strong>',
            $this->vendorQuoteUrl(),
            'View details',
        );
    }

    private function queueMain(
        string $type,
        string $to,
        string $subject,
        string $content,
        string $url,
        string $buttonText,
    ): ?EmailJob {
        return $this->email->queue(
            $type,
            $to,
            [
                'title' => $subject,
                'content' => $content,
                'url' => $url,
                'buttonText' => $buttonText,
            ],
            subject: $subject,
            template: 'main',
        );
    }

    private function buyerQuoteUrl(): string
    {
        return $this->spaBase().'/account/quotes';
    }

    private function vendorQuoteUrl(): string
    {
        return $this->spaBase().'/vendor/quotes';
    }

    private function spaBase(): string
    {
        return rtrim((string) config('selloff.spa_url', config('app.url')), '/');
    }
}
