<?php

namespace App\Modules\Selloff\Notification\Support;

enum UserNotificationType: string
{
    case ProductApproved = 'product_approved';
    case ProductRejected = 'product_rejected';
    case ProductEditRejected = 'product_edit_rejected';
    case ProductEditedPending = 'product_edited_pending';
    case NewMessage = 'new_message';
    case NewSale = 'new_sale';
    case NewReview = 'new_review';
    case NewComment = 'new_comment';
    case QuoteRequest = 'quote_request';
    case VendorRefundRequest = 'vendor_refund_request';
    case OrderUpdate = 'order_update';
    case RefundUpdate = 'refund_update';
    case QuoteResponse = 'quote_response';

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::NewMessage,
            self::ProductApproved,
            self::ProductRejected,
            self::ProductEditRejected,
            self::ProductEditedPending,
            self::NewSale,
            self::NewReview,
            self::NewComment,
            self::QuoteRequest,
            self::VendorRefundRequest,
            self::OrderUpdate,
            self::RefundUpdate,
            self::QuoteResponse,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::ProductApproved => 'Products approved',
            self::ProductRejected => 'Products rejected',
            self::ProductEditRejected => 'Edit changes rejected',
            self::ProductEditedPending => 'Edited listings',
            self::NewMessage => 'Messages',
            self::NewSale => 'New sales',
            self::NewReview => 'New reviews',
            self::NewComment => 'Product comments',
            self::QuoteRequest => 'Quote requests',
            self::VendorRefundRequest => 'Refund requests',
            self::OrderUpdate => 'Orders',
            self::RefundUpdate => 'Refund updates',
            self::QuoteResponse => 'Quote responses',
        };
    }

    public function audience(): string
    {
        return match ($this) {
            self::ProductApproved,
            self::ProductRejected,
            self::ProductEditRejected,
            self::ProductEditedPending,
            self::NewSale,
            self::NewReview,
            self::NewComment,
            self::QuoteRequest,
            self::VendorRefundRequest => 'vendor',
            self::OrderUpdate,
            self::RefundUpdate,
            self::QuoteResponse => 'member',
            self::NewMessage => 'both',
        };
    }

    public function requiresVendor(): bool
    {
        return in_array($this->audience(), ['vendor', 'both'], true);
    }

    public function requiresMember(): bool
    {
        return in_array($this->audience(), ['member', 'both'], true);
    }

    public function listUrl(bool $isVendor): string
    {
        return match ($this) {
            self::ProductApproved => '/vendor/products',
            self::ProductRejected => '/vendor/products?st=hidden',
            self::ProductEditRejected => '/vendor/products',
            self::ProductEditedPending => '/vendor/products?st=pending',
            self::NewMessage => $isVendor ? '/vendor/messages' : '/messages',
            self::NewSale => '/vendor/sales',
            self::NewReview => '/vendor/reviews',
            self::NewComment => '/vendor/comments',
            self::QuoteRequest => '/vendor/quotes?status=pending',
            self::VendorRefundRequest => '/vendor/refunds',
            self::OrderUpdate => '/orders',
            self::RefundUpdate => '/account/refunds',
            self::QuoteResponse => '/account/quotes',
        };
    }

    public function actionUrl(int $subjectId, bool $isVendor): string
    {
        return match ($this) {
            self::ProductApproved, self::ProductRejected, self::ProductEditRejected, self::ProductEditedPending => "/vendor/products/{$subjectId}/edit",
            self::NewMessage => $isVendor
                ? "/vendor/messages?conversation={$subjectId}"
                : "/messages?conversation={$subjectId}",
            self::NewSale => "/vendor/sales/{$subjectId}",
            self::NewReview => '/vendor/reviews',
            self::NewComment => '/vendor/comments',
            self::QuoteRequest => '/vendor/quotes?status=pending',
            self::VendorRefundRequest => '/vendor/refunds',
            self::OrderUpdate => "/orders/{$subjectId}",
            self::RefundUpdate => '/account/refunds',
            self::QuoteResponse => '/account/quotes',
        };
    }

    public function makeKey(int $subjectId): string
    {
        return "{$this->value}:{$subjectId}";
    }

    public static function parseKey(string $key): ?array
    {
        if (! preg_match('/^([a-z_]+):(\d+)$/', $key, $matches)) {
            return null;
        }

        $type = self::tryFrom($matches[1]);
        if ($type === null) {
            return null;
        }

        return [
            'type' => $type,
            'subject_id' => (int) $matches[2],
        ];
    }
}
