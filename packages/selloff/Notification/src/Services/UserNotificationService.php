<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;
use App\Modules\Selloff\Notification\Models\UserNotificationRead;
use App\Modules\Selloff\Notification\Support\UserNotificationType;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Review\Models\ProductComment;
use App\Modules\Selloff\Review\Models\ProductReview;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserNotificationService
{
    private const PER_TYPE_LIMIT = 15;

    private const ACTIVITY_WINDOW_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $isVendor = $user->hasRole('vendor');
        $readKeys = UserNotificationRead::query()
            ->where('user_id', $user->id)
            ->pluck('notification_key')
            ->flip();

        $groups = [];
        $unreadCount = 0;

        foreach (UserNotificationType::ordered() as $type) {
            if (! $this->userCanAccessType($user, $type, $isVendor)) {
                continue;
            }

            $subjectIds = $this->subjectIdsForType($type, $user, $isVendor);
            $totalCount = $subjectIds->count();
            if ($totalCount === 0) {
                continue;
            }

            $groupUnread = $this->countUnreadForIds($type, $subjectIds, $readKeys);
            if ($groupUnread === 0) {
                continue;
            }

            $unreadCount += $groupUnread;

            $items = $this->collectForType($type, $user, $isVendor, $readKeys)
                ->map(fn (array $item): array => [
                    ...$item,
                    'is_read' => false,
                ])
                ->values()
                ->all();

            $groups[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'list_url' => $type->listUrl($isVendor),
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
        $parsed = UserNotificationType::parseKey($key);
        abort_if($parsed === null, 404);

        $isVendor = $user->hasRole('vendor');
        if (! $this->userCanAccessType($user, $parsed['type'], $isVendor)) {
            abort(403);
        }

        if (! $this->notificationExists($parsed['type'], $parsed['subject_id'], $user, $isVendor)) {
            abort(404);
        }

        UserNotificationRead::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_key' => $key,
            ],
            [
                'read_at' => now(),
            ],
        );
    }

    public function markAllRead(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $isVendor = $user->hasRole('vendor');
        $readKeys = UserNotificationRead::query()
            ->where('user_id', $user->id)
            ->pluck('notification_key')
            ->flip();

        $marked = 0;

        foreach (UserNotificationType::ordered() as $type) {
            if (! $this->userCanAccessType($user, $type, $isVendor)) {
                continue;
            }

            foreach ($this->subjectIdsForType($type, $user, $isVendor) as $subjectId) {
                $key = $type->makeKey((int) $subjectId);
                if ($readKeys->has($key)) {
                    continue;
                }

                UserNotificationRead::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'notification_key' => $key,
                    ],
                    [
                        'read_at' => now(),
                    ],
                );
                $marked++;
            }
        }

        return $marked;
    }

    private function activityCutoff(): \Carbon\CarbonInterface
    {
        return now()->subDays(self::ACTIVITY_WINDOW_DAYS);
    }

    private function userCanAccessType(User $user, UserNotificationType $type, bool $isVendor): bool
    {
        return match ($type->audience()) {
            'vendor' => $isVendor,
            'member' => true,
            'both' => true,
            default => false,
        };
    }

    /**
     * @return Collection<int, int>
     */
    private function subjectIdsForType(UserNotificationType $type, User $user, bool $isVendor): Collection
    {
        $cutoff = $this->activityCutoff();

        return match ($type) {
            UserNotificationType::ProductApproved => Product::query()
                ->where('vendor_id', $user->id)
                ->where('is_verified', true)
                ->where('is_deleted', false)
                ->where('is_draft', false)
                ->where('updated_at', '>=', $cutoff)
                ->whereColumn('updated_at', '>', 'created_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::ProductRejected => Product::query()
                ->where('vendor_id', $user->id)
                ->where('is_verified', false)
                ->where('is_deleted', false)
                ->where('is_draft', false)
                ->whereNotNull('reject_reason')
                ->where('reject_reason', '!=', '')
                ->where('updated_at', '>=', $cutoff)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::ProductEditedPending => Product::query()
                ->where('vendor_id', $user->id)
                ->where('is_edited', true)
                ->where('is_deleted', false)
                ->where('is_draft', false)
                ->where('updated_at', '>=', $cutoff)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::NewMessage => Conversation::query()
                ->where(fn (Builder $query) => $query
                    ->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id))
                ->whereHas('messages', fn (Builder $query) => $query
                    ->where('receiver_id', $user->id)
                    ->where('is_read', false)
                    ->where('created_at', '>=', $cutoff))
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::NewSale => OrderItem::query()
                ->where('seller_id', $user->id)
                ->where('created_at', '>=', $cutoff)
                ->select('order_id')
                ->distinct()
                ->orderByDesc('order_id')
                ->pluck('order_id'),
            UserNotificationType::NewReview => ProductReview::query()
                ->where('created_at', '>=', $cutoff)
                ->whereHas('product', fn (Builder $query) => $query->where('vendor_id', $user->id))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::NewComment => ProductComment::query()
                ->where('created_at', '>=', $cutoff)
                ->whereHas('product', fn (Builder $query) => $query->where('vendor_id', $user->id))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::QuoteRequest => QuoteRequest::query()
                ->where('seller_id', $user->id)
                ->where('status', 'pending')
                ->where('created_at', '>=', $cutoff)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::VendorRefundRequest => RefundRequest::query()
                ->where('seller_id', $user->id)
                ->where('status', 'pending')
                ->where('is_completed', false)
                ->where('created_at', '>=', $cutoff)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::OrderUpdate => Order::query()
                ->where('buyer_id', $user->id)
                ->where('updated_at', '>=', $cutoff)
                ->where(function (Builder $query): void {
                    $query->where('status', '!=', 'pending')
                        ->orWhereHas('items', fn (Builder $items) => $items
                            ->whereNotNull('order_status')
                            ->where('order_status', '!=', 'pending'));
                })
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::RefundUpdate => RefundRequest::query()
                ->where('buyer_id', $user->id)
                ->where('updated_at', '>=', $cutoff)
                ->where(function (Builder $query): void {
                    $query->where('status', '!=', 'pending')
                        ->orWhere('is_completed', true);
                })
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id'),
            UserNotificationType::QuoteResponse => QuoteRequest::query()
                ->where('buyer_id', $user->id)
                ->whereIn('status', ['quoted', 'accepted', 'rejected', 'closed'])
                ->where('updated_at', '>=', $cutoff)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id'),
        };
    }

    /**
     * @param  Collection<int, int>  $subjectIds
     */
    private function countUnreadForIds(UserNotificationType $type, Collection $subjectIds, Collection $readKeys): int
    {
        return $subjectIds
            ->filter(fn (int $id): bool => ! $readKeys->has($type->makeKey($id)))
            ->count();
    }

    /**
     * @return list<int>
     */
    private function readSubjectIdsForType(UserNotificationType $type, Collection $readKeys): array
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
    private function collectForType(
        UserNotificationType $type,
        User $user,
        bool $isVendor,
        Collection $readKeys,
    ): Collection {
        return match ($type) {
            UserNotificationType::ProductApproved => $this->collectApprovedProducts($user, $isVendor, $readKeys),
            UserNotificationType::ProductRejected => $this->collectRejectedProducts($user, $isVendor, $readKeys),
            UserNotificationType::ProductEditedPending => $this->collectEditedProducts($user, $isVendor, $readKeys),
            UserNotificationType::NewMessage => $this->collectMessages($user, $isVendor, $readKeys),
            UserNotificationType::NewSale => $this->collectSales($user, $isVendor, $readKeys),
            UserNotificationType::NewReview => $this->collectReviews($user, $isVendor, $readKeys),
            UserNotificationType::NewComment => $this->collectComments($user, $isVendor, $readKeys),
            UserNotificationType::QuoteRequest => $this->collectVendorQuoteRequests($user, $isVendor, $readKeys),
            UserNotificationType::VendorRefundRequest => $this->collectVendorRefundRequests($user, $isVendor, $readKeys),
            UserNotificationType::OrderUpdate => $this->collectOrderUpdates($user, $isVendor, $readKeys),
            UserNotificationType::RefundUpdate => $this->collectRefundUpdates($user, $isVendor, $readKeys),
            UserNotificationType::QuoteResponse => $this->collectQuoteResponses($user, $isVendor, $readKeys),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectApprovedProducts(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::ProductApproved;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $query = Product::query()
            ->where('vendor_id', $user->id)
            ->where('is_verified', true)
            ->where('is_deleted', false)
            ->where('is_draft', false)
            ->where('updated_at', '>=', $this->activityCutoff())
            ->whereColumn('updated_at', '>', 'created_at')
            ->with('translations');
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(fn (Product $product): array => $this->mapProductItem(
                $type,
                $product,
                'Your listing was approved',
                $isVendor,
                $product->updated_at,
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRejectedProducts(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::ProductRejected;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $query = Product::query()
            ->where('vendor_id', $user->id)
            ->where('is_verified', false)
            ->where('is_deleted', false)
            ->where('is_draft', false)
            ->whereNotNull('reject_reason')
            ->where('reject_reason', '!=', '')
            ->where('updated_at', '>=', $this->activityCutoff())
            ->with('translations');
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(fn (Product $product): array => $this->mapProductItem(
                $type,
                $product,
                'Listing needs changes · '.trim((string) $product->reject_reason),
                $isVendor,
                $product->updated_at,
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectEditedProducts(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::ProductEditedPending;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $query = Product::query()
            ->where('vendor_id', $user->id)
            ->where('is_edited', true)
            ->where('is_deleted', false)
            ->where('is_draft', false)
            ->where('updated_at', '>=', $this->activityCutoff())
            ->with('translations');
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(fn (Product $product): array => $this->mapProductItem(
                $type,
                $product,
                'Edited listing awaiting review',
                $isVendor,
                $product->updated_at,
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectMessages(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::NewMessage;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $cutoff = $this->activityCutoff();
        $query = Conversation::query()
            ->with(['sender', 'receiver', 'product.translations', 'latestMessage'])
            ->where(fn (Builder $inner) => $inner
                ->where('sender_id', $user->id)
                ->orWhere('receiver_id', $user->id))
            ->whereHas('messages', fn (Builder $inner) => $inner
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->where('created_at', '>=', $cutoff));
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (Conversation $conversation) use ($type, $user, $isVendor): array {
                $other = $conversation->sender_id === $user->id
                    ? $conversation->receiver
                    : $conversation->sender;
                $unread = Message::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('receiver_id', $user->id)
                    ->where('is_read', false)
                    ->count();
                $preview = $conversation->latestMessage?->message;
                if (is_string($preview) && strlen($preview) > 80) {
                    $preview = substr($preview, 0, 77).'…';
                }

                return [
                    'key' => $type->makeKey((int) $conversation->id),
                    'title' => 'Message from '.$this->userLabel($other),
                    'body' => ($preview ?: 'New message').' · '.$unread.' unread',
                    'created_at' => $this->formatTimestamp($conversation->last_message_at),
                    'action_url' => $type->actionUrl((int) $conversation->id, $isVendor),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectSales(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::NewSale;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $cutoff = $this->activityCutoff();
        $orderIds = OrderItem::query()
            ->where('seller_id', $user->id)
            ->where('created_at', '>=', $cutoff)
            ->when($readIds !== [], fn (Builder $query) => $query->whereNotIn('order_id', $readIds))
            ->select('order_id')
            ->distinct()
            ->orderByDesc('order_id')
            ->limit(self::PER_TYPE_LIMIT)
            ->pluck('order_id');

        return Order::query()
            ->with(['buyer', 'items' => fn ($query) => $query->where('seller_id', $user->id)])
            ->whereIn('id', $orderIds)
            ->orderByDesc('id')
            ->get()
            ->map(function (Order $order) use ($type, $isVendor): array {
                $itemCount = $order->items->count();
                $total = number_format((float) $order->items->sum('total_price'), 2);

                return [
                    'key' => $type->makeKey((int) $order->id),
                    'title' => 'New sale · Order #'.$order->order_number,
                    'body' => $this->userLabel($order->buyer).' · '.$itemCount.' item(s) · '.$total,
                    'created_at' => $this->formatTimestamp($order->created_at),
                    'action_url' => $type->actionUrl((int) $order->id, $isVendor),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectReviews(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::NewReview;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $query = ProductReview::query()
            ->where('created_at', '>=', $this->activityCutoff())
            ->whereHas('product', fn (Builder $inner) => $inner->where('vendor_id', $user->id))
            ->with(['user', 'product.translations']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (ProductReview $review) use ($type, $isVendor): array {
                $productTitle = $review->product?->translations->first()?->title ?? 'Product #'.$review->product_id;

                return [
                    'key' => $type->makeKey((int) $review->id),
                    'title' => 'Review on '.$productTitle,
                    'body' => $this->userLabel($review->user).' · '.$this->formatTimestamp($review->created_at),
                    'created_at' => $this->formatTimestamp($review->created_at),
                    'action_url' => $type->actionUrl((int) $review->id, $isVendor),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectComments(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::NewComment;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $query = ProductComment::query()
            ->where('created_at', '>=', $this->activityCutoff())
            ->whereHas('product', fn (Builder $inner) => $inner->where('vendor_id', $user->id))
            ->with(['user', 'product.translations']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (ProductComment $comment) use ($type, $isVendor): array {
                $productTitle = $comment->product?->translations->first()?->title ?? 'Product #'.$comment->product_id;

                return [
                    'key' => $type->makeKey((int) $comment->id),
                    'title' => 'Comment on '.$productTitle,
                    'body' => $this->userLabel($comment->user).' · '.$this->formatTimestamp($comment->created_at),
                    'created_at' => $this->formatTimestamp($comment->created_at),
                    'action_url' => $type->actionUrl((int) $comment->id, $isVendor),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectVendorQuoteRequests(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::QuoteRequest;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $query = QuoteRequest::query()
            ->where('seller_id', $user->id)
            ->where('status', 'pending')
            ->where('created_at', '>=', $this->activityCutoff())
            ->with(['buyer', 'product.translations']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (QuoteRequest $quote) use ($type, $isVendor): array {
                $productTitle = $quote->product?->translations->first()?->title ?? 'Product #'.$quote->product_id;

                return [
                    'key' => $type->makeKey((int) $quote->id),
                    'title' => 'Quote request for '.$productTitle,
                    'body' => $this->userLabel($quote->buyer).' · '.$this->formatTimestamp($quote->created_at),
                    'created_at' => $this->formatTimestamp($quote->created_at),
                    'action_url' => $type->actionUrl((int) $quote->id, $isVendor),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectVendorRefundRequests(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::VendorRefundRequest;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $query = RefundRequest::query()
            ->where('seller_id', $user->id)
            ->where('status', 'pending')
            ->where('is_completed', false)
            ->where('created_at', '>=', $this->activityCutoff())
            ->with(['buyer', 'order']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (RefundRequest $refund) use ($type, $isVendor): array {
                $orderLabel = $refund->order?->order_number ?? '#'.$refund->order_id;

                return [
                    'key' => $type->makeKey((int) $refund->id),
                    'title' => 'Refund request for order '.$orderLabel,
                    'body' => $this->userLabel($refund->buyer).' · '.$this->formatTimestamp($refund->created_at),
                    'created_at' => $this->formatTimestamp($refund->created_at),
                    'action_url' => $type->actionUrl((int) $refund->id, $isVendor),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectOrderUpdates(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::OrderUpdate;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $cutoff = $this->activityCutoff();
        $query = Order::query()
            ->where('buyer_id', $user->id)
            ->where('updated_at', '>=', $cutoff)
            ->where(function (Builder $inner): void {
                $inner->where('status', '!=', 'pending')
                    ->orWhereHas('items', fn (Builder $items) => $items
                        ->whereNotNull('order_status')
                        ->where('order_status', '!=', 'pending'));
            });
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (Order $order) use ($type, $isVendor): array {
                $status = ucfirst(str_replace('_', ' ', (string) ($order->status ?: 'updated')));

                return [
                    'key' => $type->makeKey((int) $order->id),
                    'title' => 'Order #'.$order->order_number.' updated',
                    'body' => $status.' · '.$this->formatTimestamp($order->updated_at),
                    'created_at' => $this->formatTimestamp($order->updated_at),
                    'action_url' => $type->actionUrl((int) $order->id, $isVendor),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRefundUpdates(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::RefundUpdate;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $query = RefundRequest::query()
            ->where('buyer_id', $user->id)
            ->where('updated_at', '>=', $this->activityCutoff())
            ->where(function (Builder $inner): void {
                $inner->where('status', '!=', 'pending')
                    ->orWhere('is_completed', true);
            })
            ->with('order');
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (RefundRequest $refund) use ($type, $isVendor): array {
                $orderLabel = $refund->order?->order_number ?? '#'.$refund->order_id;
                $status = ucfirst(str_replace('_', ' ', (string) $refund->status));

                return [
                    'key' => $type->makeKey((int) $refund->id),
                    'title' => 'Refund update for order '.$orderLabel,
                    'body' => $status.' · '.$this->formatTimestamp($refund->updated_at),
                    'created_at' => $this->formatTimestamp($refund->updated_at),
                    'action_url' => $type->actionUrl((int) $refund->id, $isVendor),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectQuoteResponses(User $user, bool $isVendor, Collection $readKeys): Collection
    {
        $type = UserNotificationType::QuoteResponse;
        $readIds = $this->readSubjectIdsForType($type, $readKeys);
        $query = QuoteRequest::query()
            ->where('buyer_id', $user->id)
            ->whereIn('status', ['quoted', 'accepted', 'rejected', 'closed'])
            ->where('updated_at', '>=', $this->activityCutoff())
            ->with(['seller', 'product.translations']);
        $this->applyUnreadSubjectFilter($query, 'id', $readIds);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (QuoteRequest $quote) use ($type, $isVendor): array {
                $productTitle = $quote->product?->translations->first()?->title ?? 'Product #'.$quote->product_id;
                $status = ucfirst((string) $quote->status);

                return [
                    'key' => $type->makeKey((int) $quote->id),
                    'title' => 'Quote response for '.$productTitle,
                    'body' => $this->userLabel($quote->seller).' · '.$status,
                    'created_at' => $this->formatTimestamp($quote->updated_at),
                    'action_url' => $type->actionUrl((int) $quote->id, $isVendor),
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProductItem(
        UserNotificationType $type,
        Product $product,
        string $suffix,
        bool $isVendor,
        mixed $timestamp,
    ): array {
        $title = $product->translations->first()?->title ?? $product->slug ?? 'Product #'.$product->id;

        return [
            'key' => $type->makeKey((int) $product->id),
            'title' => $title,
            'body' => $suffix,
            'created_at' => $this->formatTimestamp($timestamp),
            'action_url' => $type->actionUrl((int) $product->id, $isVendor),
        ];
    }

    private function notificationExists(
        UserNotificationType $type,
        int $subjectId,
        User $user,
        bool $isVendor,
    ): bool {
        return $this->subjectIdsForType($type, $user, $isVendor)->contains($subjectId);
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
