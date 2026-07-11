<?php

namespace App\Modules\Selloff\Admin\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductListingDailyMetric;
use App\Modules\Selloff\Catalog\Support\ListingMetricsSchema;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\PaymentTransaction;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Modules\Selloff\Review\Models\ProductComment;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Modules\Selloff\Support\Models\SupportTicket;
use App\Support\ServicePaymentQuery;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    private const TOP_LIMIT = 8;

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $user = $request->user();
        abort_if($user === null, 401);

        [$from, $to] = $this->resolvePeriod($request);
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        $capabilities = $this->capabilities($user);

        $currentKpis = $this->kpis($from, $to);
        $previousKpis = $this->deltaKpis($previousFrom, $previousTo);

        $payload = [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
            ],
            'capabilities' => $capabilities,
            'kpis' => array_merge($currentKpis, [
                'deltas' => $this->deltas($currentKpis, $previousKpis),
            ]),
        ];

        if ($capabilities['orders'] || $capabilities['earnings']) {
            $payload['time_series']['revenue'] = $this->revenueTimeSeries($from, $to, $days);
            $payload['time_series']['orders'] = $this->ordersTimeSeries($from, $to, $days);
        }

        if ($capabilities['membership']) {
            $payload['time_series']['signups'] = $this->signupsTimeSeries($from, $to, $days);
        }

        if ($capabilities['orders']) {
            $payload['breakdowns']['orders_by_status'] = $this->ordersByStatus($from, $to);
            $payload['breakdowns']['payments_by_method'] = $this->paymentsByMethod($from, $to);
            $payload['breakdowns']['top_vendors'] = $this->topVendors($from, $to);
        }

        if ($capabilities['products']) {
            $payload['breakdowns']['products_by_status'] = $this->productsByStatus();
            $payload['breakdowns']['top_categories'] = $this->topCategories($from, $to);
        }

        if ($capabilities['membership']) {
            $payload['breakdowns']['escrow_by_status'] = $this->escrowByStatus();
        }

        if ($capabilities['reviews']) {
            $payload['breakdowns']['reviews_by_rating'] = $this->reviewsByRating($from, $to);
        }

        return $payload;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function resolvePeriod(Request $request): array
    {
        if ($request->string('period') === '24h') {
            $to = Carbon::now();
            $from = $to->copy()->subHours(23)->startOfHour();

            return [$from, $to];
        }

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array<string, bool>
     */
    private function capabilities(User $user): array
    {
        return [
            'orders' => $this->userCanAny($user, ['orders']),
            'products' => $this->userCanAny($user, ['products']),
            'membership' => $this->userCanAny($user, ['membership']),
            'reviews' => $this->userCanAny($user, ['reviews']),
            'comments' => $this->userCanAny($user, ['comments']),
            'earnings' => $this->userCanAny($user, ['earnings']),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function kpis(CarbonInterface $from, CarbonInterface $to): array
    {
        $now = Carbon::now();
        $startOfThisMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $ordersQuery = Order::query()->whereBetween('created_at', [$from, $to]);
        $ordersCount = (int) (clone $ordersQuery)->count();

        $paidOrdersQuery = Order::query()
            ->where('payment_status', 'payment_received')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to]);

        $paidOrdersCount = (int) (clone $paidOrdersQuery)->count();

        $completedOrders = (int) (clone $ordersQuery)->where('status', 'completed')->count();
        $cancelledOrders = (int) (clone $ordersQuery)->where('status', 'cancelled')->count();

        $gmv = (float) (clone $paidOrdersQuery)->sum('price_total');

        $platformCommission = (float) VendorEarning::query()
            ->where('is_refunded', false)
            ->whereHas('order', function ($query) use ($from, $to): void {
                $query
                    ->where('payment_status', 'payment_received')
                    ->where('status', '!=', 'cancelled')
                    ->whereBetween('created_at', [$from, $to]);
            })
            ->sum('commission_amount');

        $avgOrderValue = $paidOrdersCount > 0 ? round($gmv / $paidOrdersCount, 2) : 0.0;

        $newUsers = (int) User::query()->whereBetween('created_at', [$from, $to])->count();
        $newVendors = (int) User::query()
            ->role('vendor')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $promotionRevenue = (float) ServicePaymentQuery::wherePaid(PromotionTransaction::query())
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $membershipRevenue = (float) ServicePaymentQuery::wherePaid(MembershipTransaction::query())
            ->whereBetween('created_at', [$from, $to])
            ->sum(DB::raw(ServicePaymentQuery::membershipPaidAmountExpression()));

        return [
            'gmv' => round($gmv, 2),
            'platform_commission' => round($platformCommission, 2),
            'orders_count' => $ordersCount,
            'total_orders' => (int) Order::query()->count(),
            'avg_order_value' => $avgOrderValue,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'paid_orders' => $paidOrdersCount,
            'new_users' => $newUsers,
            'new_vendors' => $newVendors,
            'signup_this_month' => (int) User::query()
                ->where('created_at', '>=', $startOfThisMonth)
                ->count(),
            'signup_last_month' => (int) User::query()
                ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
                ->count(),
            'total_members' => (int) User::query()->count(),
            'total_vendors' => (int) User::role('vendor')->count(),
            'active_users_30d' => (int) User::query()
                ->whereBetween('last_seen_at', [$from, $to])
                ->count(),
            'pending_products' => Product::query()->adminPendingModeration()->count(),
            'listed_products' => Product::query()->adminItemsForSale()->count(),
            'pending_payouts' => (int) PayoutRequest::query()->where('status', 'pending')->count(),
            'open_refunds' => (int) RefundRequest::query()
                ->where('status', 'pending')
                ->where('is_completed', false)
                ->count(),
            'open_support_tickets' => (int) SupportTicket::query()
                ->whereIn('status', ['open', 'pending'])
                ->count(),
            'escrow_active' => (int) EscrowTransaction::query()
                ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
                ->count(),
            'escrow_total' => (int) EscrowTransaction::query()
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'promotion_revenue' => round($promotionRevenue, 2),
            'membership_revenue' => round($membershipRevenue, 2),
            'total_promotion_payments' => round($promotionRevenue, 2),
            'total_subscription_payments' => round($membershipRevenue, 2),
            'total_wallet_balance' => round((float) User::query()->sum('wallet_balance'), 2),
            'affiliate_members' => (int) User::query()->where('is_affiliate', 1)->count(),
            'contact_views' => (int) ProductListingDailyMetric::query()
                ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
                ->sum('contact_views'),
            'impressions' => ListingMetricsSchema::hasImpressionsColumn()
                ? (int) ProductListingDailyMetric::query()
                    ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
                    ->sum('impressions')
                : 0,
        ];
    }

    /**
     * Only the three previous-period values used by deltas are needed.
     *
     * @return array{gmv: float, orders_count: int, new_users: int}
     */
    private function deltaKpis(CarbonInterface $from, CarbonInterface $to): array
    {
        $ordersCount = (int) Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $gmv = (float) Order::query()
            ->where('payment_status', 'payment_received')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->sum('price_total');

        $newUsers = (int) User::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            'gmv' => round($gmv, 2),
            'orders_count' => $ordersCount,
            'new_users' => $newUsers,
        ];
    }

    /**
     * @param  array<string, float|int>  $current
     * @param  array<string, float|int>  $previous
     * @return array<string, float|null>
     */
    private function deltas(array $current, array $previous): array
    {
        return [
            'gmv_pct' => $this->percentChange((float) $previous['gmv'], (float) $current['gmv']),
            'orders_pct' => $this->percentChange((float) $previous['orders_count'], (float) $current['orders_count']),
            'new_users_pct' => $this->percentChange((float) $previous['new_users'], (float) $current['new_users']),
        ];
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return list<array{date: string, gmv: float, commission: float}>
     */
    private function revenueTimeSeries(CarbonInterface $from, CarbonInterface $to, int $days): array
    {
        $bucket = $days <= 90 ? 'day' : 'month';
        $points = $this->buildBuckets($from, $to, $bucket);

        $gmvRows = Order::query()
            ->selectRaw($this->dateBucketSelect('created_at', $bucket).' as bucket, SUM(price_total) as total')
            ->where('payment_status', 'payment_received')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $commissionRows = VendorEarning::query()
            ->selectRaw($this->dateBucketSelect('orders.created_at', $bucket).' as bucket, SUM(vendor_earnings.commission_amount) as total')
            ->join('orders', 'orders.id', '=', 'vendor_earnings.order_id')
            ->where('vendor_earnings.is_refunded', false)
            ->where('orders.payment_status', 'payment_received')
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return collect($points)->map(function (string $date) use ($gmvRows, $commissionRows): array {
            $gmv = (float) ($gmvRows[$date] ?? 0);
            $commission = (float) ($commissionRows[$date] ?? 0);

            return [
                'date' => $date,
                'gmv' => round($gmv, 2),
                'commission' => round($commission, 2),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{date: string, total: int, completed: int, cancelled: int}>
     */
    private function ordersTimeSeries(CarbonInterface $from, CarbonInterface $to, int $days): array
    {
        $bucket = $days <= 90 ? 'day' : 'month';
        $points = $this->buildBuckets($from, $to, $bucket);

        $totalRows = Order::query()
            ->selectRaw($this->dateBucketSelect('created_at', $bucket).' as bucket, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $completedRows = Order::query()
            ->selectRaw($this->dateBucketSelect('created_at', $bucket).' as bucket, COUNT(*) as total')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $cancelledRows = Order::query()
            ->selectRaw($this->dateBucketSelect('created_at', $bucket).' as bucket, COUNT(*) as total')
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return collect($points)->map(function (string $date) use ($totalRows, $completedRows, $cancelledRows): array {
            return [
                'date' => $date,
                'total' => (int) ($totalRows[$date] ?? 0),
                'completed' => (int) ($completedRows[$date] ?? 0),
                'cancelled' => (int) ($cancelledRows[$date] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{date: string, users: int, vendors: int}>
     */
    private function signupsTimeSeries(CarbonInterface $from, CarbonInterface $to, int $days): array
    {
        $bucket = $days <= 90 ? 'day' : 'month';
        $points = $this->buildBuckets($from, $to, $bucket);

        $userRows = User::query()
            ->selectRaw($this->dateBucketSelect('created_at', $bucket).' as bucket, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $vendorRows = User::query()
            ->role('vendor')
            ->selectRaw($this->dateBucketSelect('created_at', $bucket).' as bucket, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return collect($points)->map(function (string $date) use ($userRows, $vendorRows): array {
            return [
                'date' => $date,
                'users' => (int) ($userRows[$date] ?? 0),
                'vendors' => (int) ($vendorRows[$date] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function ordersByStatus(CarbonInterface $from, CarbonInterface $to): array
    {
        return Order::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'status' => (string) $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{method: string, count: int, amount: float}>
     */
    private function paymentsByMethod(CarbonInterface $from, CarbonInterface $to): array
    {
        return PaymentTransaction::query()
            ->selectRaw('payment_method as method, COUNT(*) as count, SUM(amount) as amount')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('payment_method')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'method' => (string) ($row->method ?? 'unknown'),
                'count' => (int) $row->count,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function productsByStatus(): array
    {
        return [
            ['status' => 'listed', 'count' => Product::query()->adminItemsForSale()->count()],
            ['status' => 'pending', 'count' => Product::query()->adminPendingModeration()->count()],
            ['status' => 'hidden', 'count' => Product::query()->vendorHiddenItems()->count()],
            ['status' => 'draft', 'count' => Product::query()->vendorDraftItems()->count()],
            ['status' => 'sold', 'count' => Product::query()->vendorSoldItems()->count()],
        ];
    }

    /**
     * @return list<array{id: int, name: string, product_count: int, order_count: int}>
     */
    private function topCategories(CarbonInterface $from, CarbonInterface $to): array
    {
        $productCounts = Product::query()
            ->selectRaw('category_id, COUNT(*) as product_count')
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->orderByDesc('product_count')
            ->limit(self::TOP_LIMIT)
            ->get();

        $orderCounts = OrderItem::query()
            ->selectRaw('products.category_id as category_id, COUNT(DISTINCT order_items.order_id) as order_count')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotNull('products.category_id')
            ->groupBy('products.category_id')
            ->pluck('order_count', 'category_id');

        $categories = Category::query()
            ->with('translations')
            ->whereIn('id', $productCounts->pluck('category_id'))
            ->get()
            ->keyBy('id');

        return $productCounts->map(function ($row) use ($categories, $orderCounts): array {
            $category = $categories->get($row->category_id);
            $name = $category?->translations->first()?->name
                ?? $category?->slug
                ?? 'Category #'.$row->category_id;

            return [
                'id' => (int) $row->category_id,
                'name' => (string) $name,
                'product_count' => (int) $row->product_count,
                'order_count' => (int) ($orderCounts[$row->category_id] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{seller_id: int, name: string, gmv: float, order_count: int}>
     */
    private function topVendors(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = OrderItem::query()
            ->selectRaw('order_items.seller_id, SUM(order_items.total_price) as gmv, COUNT(DISTINCT order_items.order_id) as order_count')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotNull('order_items.seller_id')
            ->groupBy('order_items.seller_id')
            ->orderByDesc('gmv')
            ->limit(self::TOP_LIMIT)
            ->get();

        $sellers = User::query()->whereIn('id', $rows->pluck('seller_id'))->get()->keyBy('id');

        return $rows->map(function ($row) use ($sellers): array {
            $seller = $sellers->get($row->seller_id);

            return [
                'seller_id' => (int) $row->seller_id,
                'name' => $seller?->name ?? 'Seller #'.$row->seller_id,
                'gmv' => round((float) $row->gmv, 2),
                'order_count' => (int) $row->order_count,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function escrowByStatus(): array
    {
        return EscrowTransaction::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'status' => (string) $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{rating: int, count: int}>
     */
    private function reviewsByRating(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = ProductReview::query()
            ->selectRaw('rating, COUNT(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('rating')
            ->pluck('count', 'rating');

        return collect(range(5, 1))->map(fn (int $rating): array => [
            'rating' => $rating,
            'count' => (int) ($rows[$rating] ?? 0),
        ])->values()->all();
    }

    /**
     * @return list<string>
     */
    private function buildBuckets(CarbonInterface $from, CarbonInterface $to, string $bucket): array
    {
        $points = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($to)) {
            $points[] = $bucket === 'day'
                ? $cursor->toDateString()
                : $cursor->format('Y-m');

            $cursor = $bucket === 'day'
                ? $cursor->addDay()
                : $cursor->addMonth()->startOfMonth();
        }

        return $points;
    }

    private function dateBucketSelect(string $column, string $bucket): string
    {
        $driver = DB::connection()->getDriverName();

        if ($bucket === 'month') {
            if ($driver === 'pgsql') {
                return "to_char({$column}, 'YYYY-MM')";
            }

            return "strftime('%Y-%m', {$column})";
        }

        if ($driver === 'pgsql') {
            return "to_char({$column}, 'YYYY-MM-DD')";
        }

        return "date({$column})";
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userCanAny(User $user, array $permissions): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
