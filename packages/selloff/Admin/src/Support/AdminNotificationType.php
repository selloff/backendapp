<?php

namespace App\Modules\Selloff\Admin\Support;

enum AdminNotificationType: string
{
    case PendingProduct = 'pending_product';
    case EditedProduct = 'edited_product';
    case ProductComment = 'product_comment';
    case BlogComment = 'blog_comment';
    case SupportTicket = 'support_ticket';
    case AbuseReport = 'abuse_report';
    case RefundRequest = 'refund_request';
    case PayoutRequest = 'payout_request';
    case BankTransfer = 'bank_transfer';
    case QuoteRequest = 'quote_request';

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::PendingProduct,
            self::EditedProduct,
            self::ProductComment,
            self::BlogComment,
            self::SupportTicket,
            self::AbuseReport,
            self::RefundRequest,
            self::PayoutRequest,
            self::BankTransfer,
            self::QuoteRequest,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingProduct => 'Pending products',
            self::EditedProduct => 'Edited products',
            self::ProductComment => 'Product comments',
            self::BlogComment => 'Blog comments',
            self::SupportTicket => 'Support tickets',
            self::AbuseReport => 'Abuse reports',
            self::RefundRequest => 'Refund requests',
            self::PayoutRequest => 'Payout requests',
            self::BankTransfer => 'Bank transfers',
            self::QuoteRequest => 'Quote requests',
        };
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::PendingProduct, self::EditedProduct => ['products'],
            self::ProductComment, self::BlogComment => ['comments'],
            self::AbuseReport => ['abuse_reports'],
            self::BankTransfer => ['payment_settings', 'admin_panel'],
            self::RefundRequest, self::PayoutRequest, self::QuoteRequest, self::SupportTicket => ['admin_panel'],
        };
    }

    public function listUrl(): string
    {
        return match ($this) {
            self::PendingProduct => '/admin/products?list=pending',
            self::EditedProduct => '/admin/products?list=edited',
            self::ProductComment => '/admin/comments?approved=0',
            self::BlogComment => '/admin/blog-comments?status=pending',
            self::SupportTicket => '/admin/support/tickets',
            self::AbuseReport => '/admin/abuse-reports',
            self::RefundRequest => '/admin/refunds',
            self::PayoutRequest => '/admin/payout-requests?status=pending',
            self::BankTransfer => '/admin/bank-transfer-reports?status=pending',
            self::QuoteRequest => '/admin/quote-requests?status=pending',
        };
    }

    public function actionUrl(int $subjectId): string
    {
        return match ($this) {
            self::PendingProduct, self::EditedProduct => "/admin/products/{$subjectId}",
            self::ProductComment => '/admin/comments?approved=0',
            self::BlogComment => '/admin/blog-comments?status=pending',
            self::SupportTicket => "/admin/support/tickets/{$subjectId}",
            self::AbuseReport => '/admin/abuse-reports',
            self::RefundRequest => "/admin/refunds/{$subjectId}",
            self::PayoutRequest => '/admin/payout-requests?status=pending',
            self::BankTransfer => '/admin/bank-transfer-reports?status=pending',
            self::QuoteRequest => '/admin/quote-requests?status=pending',
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
