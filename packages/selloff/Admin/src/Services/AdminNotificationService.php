<?php

namespace App\Modules\Selloff\Admin\Services;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\AdminNotificationRead;
use App\Modules\Selloff\Admin\Support\AdminNotificationType;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Content\Models\BlogComment;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Payment\Models\BankTransferRequest;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Review\Models\ProductComment;
use App\Modules\Selloff\Support\Models\SupportTicket;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminNotificationService
{
    private const PER_TYPE_LIMIT = 15;

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $readKeys = AdminNotificationRead::query()
            ->pluck('notification_key')
            ->flip();

        $groups = [];
        $unreadCount = 0;

        foreach (AdminNotificationType::ordered() as $type) {
            if (! $this->userCanAccessType($user, $type)) {
                continue;
            }

            $subjectIds = $this->pendingSubjectIdsForType($type);
            $totalCount = $subjectIds->count();
            if ($totalCount === 0) {
                continue;
            }

            $groupUnread = $this->countUnreadForIds($type, $subjectIds, $readKeys);
            if ($groupUnread === 0) {
                continue;
            }

            $unreadCount += $groupUnread;

            $items = $this->collectForType($type, $readKeys)
                ->map(fn (array $item): array => [
                    ...$item,
                    'is_read' => false,
                ])
                ->values()
                ->all();

            $groups[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'list_url' => $type->listUrl(),
                'unread_count' => $groupUnread,
                'total_count' => $totalCount,
                'items' => $items,
            ];
        }

        return [
            'unread_count' => $unreadCount,
            'groups' => $groups,
        ];
    }

    public function unreadCount(Request $request): int
    {
        return (int) ($this->build($request)['unread_count'] ?? 0);
    }

    public function markRead(Request $request, string $rawKey): void
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $key = urldecode($rawKey);
        $parsed = AdminNotificationType::parseKey($key);
        abort_if($parsed === null, 404);

        if (! $this->userCanAccessType($user, $parsed['type'])) {
            abort(403);
        }

        if (! $this->notificationExists($parsed['type'], $parsed['subject_id'])) {
            abort(404);
        }

        AdminNotificationRead::query()->updateOrCreate(
            ['notification_key' => $key],
            [
                'read_at' => now(),
                'read_by_user_id' => $user->id,
            ],
        );
    }

    public function markAllRead(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $readKeys = AdminNotificationRead::query()
            ->pluck('notification_key')
            ->flip();

        $marked = 0;

        foreach (AdminNotificationType::ordered() as $type) {
            if (! $this->userCanAccessType($user, $type)) {
                continue;
            }

            foreach ($this->pendingSubjectIdsForType($type) as $subjectId) {
                $key = $type->makeKey((int) $subjectId);
                if ($readKeys->has($key)) {
                    continue;
                }

                AdminNotificationRead::query()->updateOrCreate(
                    ['notification_key' => $key],
                    [
                        'read_at' => now(),
                        'read_by_user_id' => $user->id,
                    ],
                );
                $marked++;
            }
        }

        return $marked;
    }

    /**
     * @return Collection<int, int>
     */
    private function pendingSubjectIdsForType(AdminNotificationType $type): Collection
    {
        return match ($type) {
            AdminNotificationType::PendingProduct => Product::query()
                ->adminPendingModeration()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            AdminNotificationType::EditedProduct => Product::query()
                ->where('is_edited', true)
                ->where('is_deleted', false)
                ->where('is_draft', false)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id'),
            AdminNotificationType::ProductComment => ProductComment::query()
                ->where('is_approved', false)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            AdminNotificationType::BlogComment => BlogComment::query()
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            AdminNotificationType::SupportTicket => SupportTicket::query()
                ->whereIn('status', ['open', 'pending'])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id'),
            AdminNotificationType::AbuseReport => DB::table('abuse_reports')
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            AdminNotificationType::RefundRequest => RefundRequest::query()
                ->where('status', 'pending')
                ->where('is_completed', false)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            AdminNotificationType::PayoutRequest => PayoutRequest::query()
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            AdminNotificationType::BankTransfer => BankTransferRequest::query()
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            AdminNotificationType::QuoteRequest => QuoteRequest::query()
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
        };
    }

    /**
     * @param  Collection<int, int>  $subjectIds
     */
    private function countUnreadForIds(AdminNotificationType $type, Collection $subjectIds, Collection $readKeys): int
    {
        return $subjectIds
            ->filter(fn (int $id): bool => ! $readKeys->has($type->makeKey($id)))
            ->count();
    }

    /**
     * @return list<int>
     */
    private function readSubjectIdsForType(AdminNotificationType $type, Collection $readKeys): array
    {
        $prefix = $type->value.':';

        return $readKeys->keys()
            ->filter(fn (mixed $key): bool => is_string($key) && str_starts_with($key, $prefix))
            ->map(fn (string $key): int => (int) substr($key, strlen($prefix)))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $readIds
     */
    private function applyUnreadSubjectFilter(mixed $query, string $column, array $readIds): mixed
    {
        if ($readIds !== []) {
            $query->whereNotIn($column, $readIds);
        }

        return $query;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectForType(AdminNotificationType $type, Collection $readKeys): Collection
    {
        return match ($type) {
            AdminNotificationType::PendingProduct => $this->collectPendingProducts($readKeys),
            AdminNotificationType::EditedProduct => $this->collectEditedProducts($readKeys),
            AdminNotificationType::ProductComment => $this->collectProductComments($readKeys),
            AdminNotificationType::BlogComment => $this->collectBlogComments($readKeys),
            AdminNotificationType::SupportTicket => $this->collectSupportTickets($readKeys),
            AdminNotificationType::AbuseReport => $this->collectAbuseReports($readKeys),
            AdminNotificationType::RefundRequest => $this->collectRefundRequests($readKeys),
            AdminNotificationType::PayoutRequest => $this->collectPayoutRequests($readKeys),
            AdminNotificationType::BankTransfer => $this->collectBankTransfers($readKeys),
            AdminNotificationType::QuoteRequest => $this->collectQuoteRequests($readKeys),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectPendingProducts(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::PendingProduct, $readKeys);
        $query = Product::query()->adminPendingModeration()->with(['vendor', 'translations']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(fn (Product $product): array => $this->mapProductItem(
                AdminNotificationType::PendingProduct,
                $product,
                'Awaiting moderation approval',
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectEditedProducts(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::EditedProduct, $readKeys);
        $query = Product::query()
            ->where('is_edited', true)
            ->where('is_deleted', false)
            ->where('is_draft', false)
            ->with(['vendor', 'translations']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(fn (Product $product): array => $this->mapProductItem(
                AdminNotificationType::EditedProduct,
                $product,
                'Edited listing awaiting review',
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectProductComments(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::ProductComment, $readKeys);
        $query = ProductComment::query()
            ->where('is_approved', false)
            ->with(['user', 'product.translations']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (ProductComment $comment): array {
                $type = AdminNotificationType::ProductComment;
                $productTitle = $comment->product?->translations->first()?->title ?? 'Product #'.$comment->product_id;

                return [
                    'key' => $type->makeKey((int) $comment->id),
                    'title' => 'Comment on '.$productTitle,
                    'body' => $this->userLabel($comment->user).' · '.$this->formatTimestamp($comment->created_at),
                    'created_at' => $this->formatTimestamp($comment->created_at),
                    'action_url' => $type->actionUrl((int) $comment->id),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectBlogComments(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::BlogComment, $readKeys);
        $query = BlogComment::query()
            ->where('status', 'pending')
            ->with(['user', 'post']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (BlogComment $comment): array {
                $type = AdminNotificationType::BlogComment;
                $postTitle = $comment->post?->title ?? 'Blog post #'.$comment->blog_post_id;

                return [
                    'key' => $type->makeKey((int) $comment->id),
                    'title' => 'Blog comment on '.$postTitle,
                    'body' => $this->userLabel($comment->user).' · '.$this->formatTimestamp($comment->created_at),
                    'created_at' => $this->formatTimestamp($comment->created_at),
                    'action_url' => $type->actionUrl((int) $comment->id),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectSupportTickets(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::SupportTicket, $readKeys);
        $query = SupportTicket::query()
            ->whereIn('status', ['open', 'pending'])
            ->with('user');
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (SupportTicket $ticket): array {
                $type = AdminNotificationType::SupportTicket;

                return [
                    'key' => $type->makeKey((int) $ticket->id),
                    'title' => $ticket->subject ?: 'Support ticket #'.$ticket->id,
                    'body' => $this->userLabel($ticket->user).' · '.ucfirst((string) $ticket->status),
                    'created_at' => $this->formatTimestamp($ticket->created_at),
                    'action_url' => $type->actionUrl((int) $ticket->id),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectAbuseReports(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::AbuseReport, $readKeys);
        $query = DB::table('abuse_reports')->where('status', 'pending');
        if ($readIds !== []) {
            $query->whereNotIn('id', $readIds);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (object $row): array {
                $type = AdminNotificationType::AbuseReport;
                $reportType = ucfirst(strtolower((string) ($row->report_type ?: 'product')));

                return [
                    'key' => $type->makeKey((int) $row->id),
                    'title' => $reportType.' abuse report #'.$row->id,
                    'body' => 'Pending review · '.$this->formatTimestamp($row->created_at),
                    'created_at' => $this->formatTimestamp($row->created_at),
                    'action_url' => $type->actionUrl((int) $row->id),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRefundRequests(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::RefundRequest, $readKeys);
        $query = RefundRequest::query()
            ->where('status', 'pending')
            ->where('is_completed', false)
            ->with(['buyer', 'order']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (RefundRequest $refund): array {
                $type = AdminNotificationType::RefundRequest;
                $orderLabel = $refund->order?->order_number ?? '#'.$refund->order_id;

                return [
                    'key' => $type->makeKey((int) $refund->id),
                    'title' => 'Refund request for order '.$orderLabel,
                    'body' => $this->userLabel($refund->buyer).' · '.$this->formatTimestamp($refund->created_at),
                    'created_at' => $this->formatTimestamp($refund->created_at),
                    'action_url' => $type->actionUrl((int) $refund->id),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectPayoutRequests(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::PayoutRequest, $readKeys);
        $query = PayoutRequest::query()
            ->where('status', 'pending')
            ->with('seller');
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (PayoutRequest $payout): array {
                $type = AdminNotificationType::PayoutRequest;
                $amount = number_format((float) $payout->amount, 2);

                return [
                    'key' => $type->makeKey((int) $payout->id),
                    'title' => 'Payout request #'.$payout->id,
                    'body' => $this->userLabel($payout->seller).' · '.$amount.' · '.$this->formatTimestamp($payout->created_at),
                    'created_at' => $this->formatTimestamp($payout->created_at),
                    'action_url' => $type->actionUrl((int) $payout->id),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectBankTransfers(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::BankTransfer, $readKeys);
        $query = BankTransferRequest::query()
            ->where('status', 'pending')
            ->with('user');
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (BankTransferRequest $transfer): array {
                $type = AdminNotificationType::BankTransfer;
                $amount = number_format((float) ($transfer->amount ?? 0), 2);

                return [
                    'key' => $type->makeKey((int) $transfer->id),
                    'title' => 'Bank transfer #'.$transfer->id,
                    'body' => $this->userLabel($transfer->user).' · '.$amount.' · '.$this->formatTimestamp($transfer->created_at),
                    'created_at' => $this->formatTimestamp($transfer->created_at),
                    'action_url' => $type->actionUrl((int) $transfer->id),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectQuoteRequests(Collection $readKeys): Collection
    {
        $readIds = $this->readSubjectIdsForType(AdminNotificationType::QuoteRequest, $readKeys);
        $query = QuoteRequest::query()
            ->where('status', 'pending')
            ->with(['buyer', 'product.translations']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (QuoteRequest $quote): array {
                $type = AdminNotificationType::QuoteRequest;
                $productTitle = $quote->product?->translations->first()?->title ?? 'Product #'.$quote->product_id;

                return [
                    'key' => $type->makeKey((int) $quote->id),
                    'title' => 'Quote request for '.$productTitle,
                    'body' => $this->userLabel($quote->buyer).' · '.$this->formatTimestamp($quote->created_at),
                    'created_at' => $this->formatTimestamp($quote->created_at),
                    'action_url' => $type->actionUrl((int) $quote->id),
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProductItem(AdminNotificationType $type, Product $product, string $suffix): array
    {
        $title = $product->translations->first()?->title ?? $product->slug ?? 'Product #'.$product->id;

        return [
            'key' => $type->makeKey((int) $product->id),
            'title' => $title,
            'body' => $this->userLabel($product->vendor).' · '.$suffix,
            'created_at' => $this->formatTimestamp($product->created_at),
            'action_url' => $type->actionUrl((int) $product->id),
        ];
    }

    private function notificationExists(AdminNotificationType $type, int $subjectId): bool
    {
        return match ($type) {
            AdminNotificationType::PendingProduct => Product::query()
                ->adminPendingModeration()
                ->whereKey($subjectId)
                ->exists(),
            AdminNotificationType::EditedProduct => Product::query()
                ->whereKey($subjectId)
                ->where('is_edited', true)
                ->where('is_deleted', false)
                ->where('is_draft', false)
                ->exists(),
            AdminNotificationType::ProductComment => ProductComment::query()
                ->whereKey($subjectId)
                ->where('is_approved', false)
                ->exists(),
            AdminNotificationType::BlogComment => BlogComment::query()
                ->whereKey($subjectId)
                ->where('status', 'pending')
                ->exists(),
            AdminNotificationType::SupportTicket => SupportTicket::query()
                ->whereKey($subjectId)
                ->whereIn('status', ['open', 'pending'])
                ->exists(),
            AdminNotificationType::AbuseReport => DB::table('abuse_reports')
                ->where('id', $subjectId)
                ->where('status', 'pending')
                ->exists(),
            AdminNotificationType::RefundRequest => RefundRequest::query()
                ->whereKey($subjectId)
                ->where('status', 'pending')
                ->where('is_completed', false)
                ->exists(),
            AdminNotificationType::PayoutRequest => PayoutRequest::query()
                ->whereKey($subjectId)
                ->where('status', 'pending')
                ->exists(),
            AdminNotificationType::BankTransfer => BankTransferRequest::query()
                ->whereKey($subjectId)
                ->where('status', 'pending')
                ->exists(),
            AdminNotificationType::QuoteRequest => QuoteRequest::query()
                ->whereKey($subjectId)
                ->where('status', 'pending')
                ->exists(),
        };
    }

    private function userCanAccessType(User $user, AdminNotificationType $type): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        foreach ($type->permissions() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function userLabel(?User $user): string
    {
        if ($user === null) {
            return 'Unknown user';
        }

        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $username = trim((string) ($user->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        return $user->email;
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
