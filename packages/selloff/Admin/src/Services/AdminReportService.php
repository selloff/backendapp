<?php

declare(strict_types=1);

namespace App\Modules\Selloff\Admin\Services;

use App\Models\User;
use App\Modules\Selloff\Affiliate\Models\AffiliateEarning;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductListingDailyMetric;
use App\Modules\Selloff\Catalog\Models\VendorListingContactView;
use App\Modules\Selloff\Catalog\Support\ListingMetricsSchema;
use App\Modules\Selloff\Catalog\Services\VendorListingPerformanceService;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Payment\Models\BankTransferRequest;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Modules\Selloff\Support\Models\SupportTicket;
use App\Support\ServicePaymentQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportService
{
    /** @var list<string> */
    public const TYPES = [
        'overview',
        'traffic',
        'contact-views',
        'listing-performance',
        'user-engagement',
        'product-performance',
        'sales',
        'revenue',
        'digital-sales',
        'vendor-sales',
        'quote-requests',
        'geographic',
        'member-subscriptions',
        'promotions',
        'user-wallet',
        'bank-transfers',
        'payouts-refunds',
        'escrow',
        'reviews',
        'support-moderation',
        'affiliate',
    ];

    public function __construct(
        private readonly AdminReportPeriod $period,
        private readonly AdminAnalyticsService $analytics,
        private readonly VendorListingPerformanceService $listingPerformance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request, string $type): array
    {
        abort_if($request->user() === null, 401);

        return match ($type) {
            'overview' => $this->buildOverview($request),
            'traffic' => $this->buildTraffic($request),
            'contact-views' => $this->buildContactViews($request),
            'listing-performance' => $this->buildListingPerformance($request),
            'user-engagement' => $this->buildUserEngagement($request),
            'product-performance' => $this->buildProductPerformance($request),
            'sales' => $this->buildSales($request),
            'revenue' => $this->buildRevenue($request),
            'digital-sales' => $this->buildDigitalSales($request),
            'vendor-sales' => $this->buildVendorSales($request),
            'quote-requests' => $this->buildQuoteRequests($request),
            'geographic' => $this->buildGeographic($request),
            'member-subscriptions' => $this->buildMemberSubscriptions($request),
            'promotions' => $this->buildPromotions($request),
            'user-wallet' => $this->buildUserWallet($request),
            'bank-transfers' => $this->buildBankTransfers($request),
            'payouts-refunds' => $this->buildPayoutsRefunds($request),
            'escrow' => $this->buildEscrow($request),
            'reviews' => $this->buildReviews($request),
            'support-moderation' => $this->buildSupportModeration($request),
            'affiliate' => $this->buildAffiliate($request),
            default => abort(404),
        };
    }

    public function exportCsv(Request $request, string $type): StreamedResponse
    {
        $payload = $this->build($request, $type);
        $filename = 'report-'.$type.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['section', 'label', 'count', 'amount']);
            foreach ($payload['breakdown'] as $row) {
                fputcsv($handle, [
                    'breakdown',
                    (string) ($row['label'] ?? ''),
                    $row['count'] ?? '',
                    $row['amount'] ?? '',
                ]);
            }

            fputcsv($handle, []);
            $details = $payload['details']['data'] ?? [];
            if ($details !== []) {
                $first = (array) $details[0];
                fputcsv($handle, array_merge(['section'], array_keys($first)));
                foreach ($details as $row) {
                    $row = (array) $row;
                    fputcsv($handle, array_merge(['detail'], array_map(
                        fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value),
                        array_values($row),
                    )));
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOverview(Request $request): array
    {
        $period = $this->period->resolve($request);
        $current = $this->analyticsPayload($request, $period['from'], $period['to']);
        $previous = $this->analyticsPayload($request, $period['previous_from'], $period['previous_to']);
        $currentKpis = $current['kpis'];
        $previousKpis = $previous['kpis'];

        $summary = [];
        $this->addDelta($summary, 'gmv', (float) $currentKpis['gmv'], (float) $previousKpis['gmv']);
        $this->addDelta($summary, 'orders', (float) $currentKpis['orders_count'], (float) $previousKpis['orders_count']);
        $this->addDelta($summary, 'contact_views', (float) $currentKpis['contact_views'], (float) $previousKpis['contact_views']);
        $this->addDelta($summary, 'new_users', (float) $currentKpis['new_users'], (float) $previousKpis['new_users']);
        $this->addDelta(
            $summary,
            'total_subscription_payments',
            (float) $currentKpis['total_subscription_payments'],
            (float) $previousKpis['total_subscription_payments'],
        );
        $summary['total_wallet_balance'] = round((float) $currentKpis['total_wallet_balance'], 2);

        $series = $current['time_series']['revenue'] ?? $this->revenueSeries(
            $period['from'],
            $period['to'],
            $period['days'],
        );

        $breakdown = $this->overviewReportBreakdown();

        $details = $this->paginateDetails(
            Order::query()
                ->with('buyer:id,first_name,last_name,email')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (Order $order): array => $this->mapOrderDetail($order),
        );

        return $this->envelope('overview', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTraffic(Request $request): array
    {
        $period = $this->period->resolve($request);
        $current = $this->listingPerformanceForRange($period['from'], $period['to']);
        $previous = $this->listingPerformanceForRange($period['previous_from'], $period['previous_to']);

        $summary = [];
        $this->addDelta($summary, 'traffic', (float) $current['totals']['traffic'], (float) $previous['totals']['traffic']);
        $this->addDelta($summary, 'visitors', (float) $current['totals']['visitors'], (float) $previous['totals']['visitors']);
        $this->addDelta($summary, 'impressions', (float) $current['totals']['impressions'], (float) $previous['totals']['impressions']);

        $series = collect($current['series'])->map(fn (array $row): array => [
            'date' => $row['date'] ?? $row['label'],
            'label' => $row['label'],
            'traffic' => (int) $row['traffic'],
            'visitors' => (int) $row['visitors'],
            'impressions' => (int) $row['impressions'],
        ])->values()->all();

        $breakdown = collect($current['top_listings'] ?? [])
            ->map(fn (array $row): array => [
                'label' => (string) $row['title'],
                'count' => (int) $row['views'],
            ])
            ->values()
            ->all();

        $impressionsSelect = ListingMetricsSchema::impressionsSumSql();

        $details = $this->paginateDetails(
            ProductListingDailyMetric::query()
                ->selectRaw("metric_date, SUM(traffic) as traffic, SUM(visitors) as visitors, {$impressionsSelect}")
                ->whereBetween('metric_date', [$period['from']->toDateString(), $period['to']->toDateString()])
                ->groupBy('metric_date')
                ->orderByDesc('metric_date'),
            $request,
            fn ($row): array => [
                'date' => (string) $row->metric_date,
                'traffic' => (int) $row->traffic,
                'visitors' => (int) $row->visitors,
                'impressions' => (int) $row->impressions,
            ],
        );

        return $this->envelope('traffic', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContactViews(Request $request): array
    {
        $period = $this->period->resolve($request);
        $current = $this->listingPerformanceForRange($period['from'], $period['to']);
        $previous = $this->listingPerformanceForRange($period['previous_from'], $period['previous_to']);

        $summary = [];
        $this->addDelta(
            $summary,
            'contact_views',
            (float) $current['totals']['contact_views'],
            (float) $previous['totals']['contact_views'],
        );

        $series = collect($current['series'])->map(fn (array $row): array => [
            'date' => $row['date'] ?? $row['label'],
            'label' => $row['label'],
            'contact_views' => (int) $row['contact_views'],
        ])->values()->all();

        $breakdown = collect($current['top_listings'] ?? [])
            ->map(fn (array $row): array => [
                'label' => (string) $row['title'],
                'count' => (int) $row['views'],
            ])
            ->values()
            ->all();

        $details = $this->paginateDetails(
            VendorListingContactView::query()
                ->with(['viewer:id,first_name,last_name,email,slug', 'vendor:id,first_name,last_name,slug', 'product:id,slug'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('created_at'),
            $request,
            fn (VendorListingContactView $view): array => [
                'id' => $view->id,
                'created_at' => $view->created_at?->toIso8601String(),
                'vendor_id' => $view->vendor_id,
                'viewer_id' => $view->viewer_id,
                'product_id' => $view->product_id,
                'viewer_email' => $view->viewer?->email,
                'vendor_slug' => $view->vendor?->slug,
                'product_slug' => $view->product?->slug,
            ],
        );

        return $this->envelope('contact-views', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListingPerformance(Request $request): array
    {
        $period = $this->period->resolve($request);
        $current = $this->listingPerformanceForRange($period['from'], $period['to']);
        $previous = $this->listingPerformanceForRange($period['previous_from'], $period['previous_to']);

        $summary = [];
        $this->addDelta($summary, 'traffic', (float) $current['totals']['traffic'], (float) $previous['totals']['traffic']);
        $this->addDelta($summary, 'visitors', (float) $current['totals']['visitors'], (float) $previous['totals']['visitors']);
        $this->addDelta(
            $summary,
            'impressions',
            (float) $current['totals']['impressions'],
            (float) $previous['totals']['impressions'],
        );
        $this->addDelta(
            $summary,
            'contact_views',
            (float) $current['totals']['contact_views'],
            (float) $previous['totals']['contact_views'],
        );
        $this->addDelta($summary, 'chats', (float) $current['totals']['chats'], (float) $previous['totals']['chats']);
        $this->addDelta(
            $summary,
            'promotion_spend',
            (float) $current['totals']['promotion_spend'],
            (float) $previous['totals']['promotion_spend'],
        );

        $series = collect($current['series'])->map(fn (array $row): array => [
            'date' => $row['date'] ?? $row['label'],
            'label' => $row['label'],
            'traffic' => (int) $row['traffic'],
            'visitors' => (int) $row['visitors'],
            'impressions' => (int) $row['impressions'],
            'contact_views' => (int) $row['contact_views'],
            'chats' => (int) $row['chats'],
            'promotion_spend' => (float) $row['promotion_spend'],
        ])->values()->all();

        $breakdown = collect($current['top_listings'] ?? [])
            ->map(fn (array $row): array => [
                'label' => (string) $row['title'],
                'count' => (int) $row['views'],
            ])
            ->values()
            ->all();

        $details = $this->paginateDetails(
            VendorListingContactView::query()
                ->with(['viewer:id,first_name,last_name,email,slug', 'product:id,slug'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('created_at'),
            $request,
            fn (VendorListingContactView $view): array => [
                'id' => $view->id,
                'created_at' => $view->created_at?->toIso8601String(),
                'viewer_email' => $view->viewer?->email,
                'product_slug' => $view->product?->slug,
            ],
        );

        return $this->envelope('listing-performance', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserEngagement(Request $request): array
    {
        $period = $this->period->resolve($request);
        $current = $this->analyticsPayload($request, $period['from'], $period['to']);
        $previous = $this->analyticsPayload($request, $period['previous_from'], $period['previous_to']);
        $currentKpis = $current['kpis'];
        $previousKpis = $previous['kpis'];

        $currentChats = (int) Conversation::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->count();
        $previousChats = (int) Conversation::query()
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->count();

        $summary = [];
        $this->addDelta($summary, 'new_users', (float) $currentKpis['new_users'], (float) $previousKpis['new_users']);
        $this->addDelta(
            $summary,
            'new_vendors',
            (float) $currentKpis['new_vendors'],
            (float) $previousKpis['new_vendors'],
        );
        $this->addDelta(
            $summary,
            'active_users',
            (float) $currentKpis['active_users_30d'],
            (float) $previousKpis['active_users_30d'],
        );
        $this->addDelta($summary, 'chats', (float) $currentChats, (float) $previousChats);

        $series = $current['time_series']['signups'] ?? $this->signupsSeries($period['from'], $period['to'], $period['days']);

        $breakdown = [
            ['label' => 'New users', 'count' => (int) $currentKpis['new_users']],
            ['label' => 'New vendors', 'count' => (int) $currentKpis['new_vendors']],
            ['label' => 'Active users', 'count' => (int) $currentKpis['active_users_30d']],
            ['label' => 'Chats started', 'count' => $currentChats],
        ];

        $details = $this->paginateDetails(
            User::query()
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('created_at'),
            $request,
            fn (User $user): array => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'slug' => $user->slug,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('user-engagement', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductPerformance(Request $request): array
    {
        $period = $this->period->resolve($request);
        $current = $this->analyticsPayload($request, $period['from'], $period['to']);
        $previous = $this->analyticsPayload($request, $period['previous_from'], $period['previous_to']);
        $currentKpis = $current['kpis'];
        $previousKpis = $previous['kpis'];

        $summary = [];
        $this->addDelta(
            $summary,
            'listed_products',
            (float) $currentKpis['listed_products'],
            (float) $previousKpis['listed_products'],
        );
        $this->addDelta(
            $summary,
            'pending_products',
            (float) $currentKpis['pending_products'],
            (float) $previousKpis['pending_products'],
        );

        $series = $this->countSeries(
            Product::query(),
            'created_at',
            $period['from'],
            $period['to'],
            $period['days'],
        );

        $breakdown = collect($current['breakdowns']['products_by_status'] ?? [])
            ->map(fn (array $row): array => [
                'label' => (string) $row['status'],
                'count' => (int) $row['count'],
            ])
            ->values()
            ->all();

        $topCategories = collect($current['breakdowns']['top_categories'] ?? [])
            ->map(fn (array $row): array => [
                'label' => (string) $row['name'],
                'count' => (int) $row['product_count'],
                'amount' => (float) ($row['order_count'] ?? 0),
            ])
            ->values()
            ->all();

        $breakdown = array_merge($breakdown, $topCategories);

        $details = $this->paginateDetails(
            Product::query()
                ->with(['vendor:id,first_name,last_name,email,slug', 'translations'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (Product $product): array => [
                'id' => $product->id,
                'title' => $product->translations->first()?->title,
                'slug' => $product->slug,
                'status' => $product->status,
                'vendor_id' => $product->vendor_id,
                'created_at' => $product->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('product-performance', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSales(Request $request): array
    {
        $period = $this->period->resolve($request);
        $current = $this->analyticsPayload($request, $period['from'], $period['to']);
        $previous = $this->analyticsPayload($request, $period['previous_from'], $period['previous_to']);
        $currentKpis = $current['kpis'];
        $previousKpis = $previous['kpis'];

        $summary = [];
        $this->addDelta($summary, 'gmv', (float) $currentKpis['gmv'], (float) $previousKpis['gmv']);
        $this->addDelta($summary, 'orders', (float) $currentKpis['orders_count'], (float) $previousKpis['orders_count']);
        $this->addDelta(
            $summary,
            'paid_orders',
            (float) $currentKpis['paid_orders'],
            (float) $previousKpis['paid_orders'],
        );
        $this->addDelta(
            $summary,
            'avg_order_value',
            (float) $currentKpis['avg_order_value'],
            (float) $previousKpis['avg_order_value'],
        );
        $this->addDelta(
            $summary,
            'completed_orders',
            (float) $currentKpis['completed_orders'],
            (float) $previousKpis['completed_orders'],
        );
        $this->addDelta(
            $summary,
            'cancelled_orders',
            (float) $currentKpis['cancelled_orders'],
            (float) $previousKpis['cancelled_orders'],
        );

        $series = $current['time_series']['orders'] ?? $this->ordersSeries($period['from'], $period['to'], $period['days']);

        $breakdown = collect($current['breakdowns']['orders_by_status'] ?? [])
            ->map(fn (array $row): array => [
                'label' => (string) $row['status'],
                'count' => (int) $row['count'],
            ])
            ->values()
            ->all();

        $details = $this->paginateDetails(
            Order::query()
                ->with('buyer:id,first_name,last_name,email')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (Order $order): array => $this->mapOrderDetail($order),
        );

        return $this->envelope('sales', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRevenue(Request $request): array
    {
        $period = $this->period->resolve($request);
        $current = $this->analyticsPayload($request, $period['from'], $period['to']);
        $previous = $this->analyticsPayload($request, $period['previous_from'], $period['previous_to']);
        $currentKpis = $current['kpis'];
        $previousKpis = $previous['kpis'];

        $summary = [];
        $this->addDelta($summary, 'gmv', (float) $currentKpis['gmv'], (float) $previousKpis['gmv']);
        $this->addDelta(
            $summary,
            'platform_commission',
            (float) $currentKpis['platform_commission'],
            (float) $previousKpis['platform_commission'],
        );

        $series = $current['time_series']['revenue'] ?? $this->revenueSeries($period['from'], $period['to'], $period['days']);

        $breakdown = collect($current['breakdowns']['payments_by_method'] ?? [])
            ->map(fn (array $row): array => [
                'label' => (string) $row['method'],
                'count' => (int) $row['count'],
                'amount' => (float) $row['amount'],
            ])
            ->values()
            ->all();

        $details = $this->paginateDetails(
            Order::query()
                ->with('buyer:id,first_name,last_name,email')
                ->where('payment_status', 'payment_received')
                ->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (Order $order): array => $this->mapOrderDetail($order),
        );

        return $this->envelope('revenue', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDigitalSales(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentQuery = DigitalSale::query()->whereBetween('created_at', [$period['from'], $period['to']]);
        $previousQuery = DigitalSale::query()->whereBetween('created_at', [$period['previous_from'], $period['previous_to']]);

        $currentCount = (int) (clone $currentQuery)->count();
        $previousCount = (int) (clone $previousQuery)->count();
        $currentAmount = (float) (clone $currentQuery)->sum('price');
        $previousAmount = (float) (clone $previousQuery)->sum('price');

        $summary = [];
        $this->addDelta($summary, 'sales_count', (float) $currentCount, (float) $previousCount);
        $this->addDelta($summary, 'sales_amount', $currentAmount, $previousAmount);

        $series = $this->amountSeries(
            DigitalSale::query(),
            'created_at',
            'price',
            $period['from'],
            $period['to'],
            $period['days'],
            'sales_count',
            'sales_amount',
        );

        $breakdown = DigitalSale::query()
            ->selectRaw('seller_id, COUNT(*) as count, SUM(price) as amount')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('seller_id')
            ->orderByDesc('amount')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $seller = User::query()->find($row->seller_id);

                return [
                    'label' => $seller?->email ?? 'Seller #'.$row->seller_id,
                    'count' => (int) $row->count,
                    'amount' => round((float) $row->amount, 2),
                ];
            })
            ->values()
            ->all();

        $details = $this->paginateDetails(
            DigitalSale::query()
                ->with(['buyer:id,email', 'seller:id,email', 'product:id,slug', 'order:id,order_number'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (DigitalSale $sale): array => [
                'id' => $sale->id,
                'purchase_code' => $sale->purchase_code,
                'price' => (float) $sale->price,
                'buyer_email' => $sale->buyer?->email,
                'seller_email' => $sale->seller?->email,
                'order_number' => $sale->order?->order_number,
                'created_at' => $sale->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('digital-sales', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVendorSales(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentQuery = $this->vendorEarningRangeQuery($period['from'], $period['to']);
        $previousQuery = $this->vendorEarningRangeQuery($period['previous_from'], $period['previous_to']);

        $currentEarned = (float) (clone $currentQuery)->sum('earned_amount');
        $previousEarned = (float) (clone $previousQuery)->sum('earned_amount');
        $currentCommission = (float) (clone $currentQuery)->sum('commission_amount');
        $previousCommission = (float) (clone $previousQuery)->sum('commission_amount');
        $currentOrderCount = (int) (clone $currentQuery)->count();
        $previousOrderCount = (int) (clone $previousQuery)->count();

        $summary = [];
        $this->addDelta($summary, 'vendor_earnings', $currentEarned, $previousEarned);
        $this->addDelta($summary, 'platform_commission', $currentCommission, $previousCommission);
        $this->addDelta($summary, 'order_count', (float) $currentOrderCount, (float) $previousOrderCount);

        $series = $this->amountSeries(
            $this->vendorEarningRangeQuery($period['from'], $period['to']),
            'created_at',
            'earned_amount',
            $period['from'],
            $period['to'],
            $period['days'],
            'earning_count',
            'earned_amount',
        );

        $breakdown = VendorEarning::query()
            ->selectRaw('seller_id, COUNT(*) as count, SUM(earned_amount) as amount, SUM(commission_amount) as commission')
            ->where('is_refunded', false)
            ->whereHas('order', fn ($query) => $query
                ->where('payment_status', 'payment_received')
                ->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$period['from'], $period['to']]))
            ->groupBy('seller_id')
            ->orderByDesc('amount')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $seller = User::query()->find($row->seller_id);

                return [
                    'label' => $seller?->email ?? 'Seller #'.$row->seller_id,
                    'count' => (int) $row->count,
                    'amount' => round((float) $row->amount, 2),
                ];
            })
            ->values()
            ->all();

        $details = $this->paginateDetails(
            VendorEarning::query()
                ->with(['seller:id,email,slug', 'order:id,order_number,price_total'])
                ->where('is_refunded', false)
                ->whereHas('order', fn ($query) => $query
                    ->whereBetween('created_at', [$period['from'], $period['to']]))
                ->orderByDesc('id'),
            $request,
            fn (VendorEarning $earning): array => [
                'id' => $earning->id,
                'seller_email' => $earning->seller?->email,
                'order_number' => $earning->order?->order_number,
                'earned_amount' => (float) $earning->earned_amount,
                'commission_amount' => (float) $earning->commission_amount,
                'created_at' => $earning->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('vendor-sales', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuoteRequests(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentCount = (int) QuoteRequest::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->count();
        $previousCount = (int) QuoteRequest::query()
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->count();

        $summary = [];
        $this->addDelta($summary, 'quote_requests', (float) $currentCount, (float) $previousCount);

        $series = $this->countSeries(
            QuoteRequest::query(),
            'created_at',
            $period['from'],
            $period['to'],
            $period['days'],
        );

        $breakdown = QuoteRequest::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $details = $this->paginateDetails(
            QuoteRequest::query()
                ->with(['buyer:id,email', 'seller:id,email', 'product:id,slug'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (QuoteRequest $quote): array => [
                'id' => $quote->id,
                'status' => $quote->status,
                'message' => $quote->message,
                'quoted_price' => $quote->quoted_price !== null ? (float) $quote->quoted_price : null,
                'buyer_email' => $quote->buyer?->email,
                'seller_email' => $quote->seller?->email,
                'created_at' => $quote->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('quote-requests', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGeographic(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentOrders = (int) Order::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->count();
        $previousOrders = (int) Order::query()
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->count();
        $currentGmv = (float) Order::query()
            ->where('payment_status', 'payment_received')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->sum('price_total');
        $previousGmv = (float) Order::query()
            ->where('payment_status', 'payment_received')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->sum('price_total');

        $summary = [];
        $this->addDelta($summary, 'orders', (float) $currentOrders, (float) $previousOrders);
        $this->addDelta($summary, 'gmv', $currentGmv, $previousGmv);

        $series = $this->ordersSeries($period['from'], $period['to'], $period['days']);

        $breakdown = Order::query()
            ->join('users', 'users.id', '=', 'orders.buyer_id')
            ->leftJoin('countries', 'countries.id', '=', 'users.country_id')
            ->whereBetween('orders.created_at', [$period['from'], $period['to']])
            ->selectRaw("COALESCE(countries.name, 'Unknown') as label, COUNT(*) as count, SUM(orders.price_total) as amount")
            ->groupByRaw("COALESCE(countries.name, 'Unknown')")
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'count' => (int) $row->count,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();

        $countryNames = Country::query()->pluck('name', 'id');

        $details = $this->paginateDetails(
            Order::query()
                ->with('buyer:id,email,country_id')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'buyer_email' => $order->buyer?->email,
                'country' => $countryNames[$order->buyer?->country_id] ?? 'Unknown',
                'price_total' => (float) $order->price_total,
                'created_at' => $order->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('geographic', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMemberSubscriptions(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentQuery = ServicePaymentQuery::wherePaid(MembershipTransaction::query())
            ->whereBetween('created_at', [$period['from'], $period['to']]);
        $previousQuery = ServicePaymentQuery::wherePaid(MembershipTransaction::query())
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']]);

        $currentAmount = (float) (clone $currentQuery)->sum(DB::raw(ServicePaymentQuery::membershipPaidAmountExpression()));
        $previousAmount = (float) (clone $previousQuery)->sum(DB::raw(ServicePaymentQuery::membershipPaidAmountExpression()));
        $currentCount = (int) (clone $currentQuery)->count();
        $previousCount = (int) (clone $previousQuery)->count();

        $summary = [];
        $this->addDelta($summary, 'payments_count', (float) $currentCount, (float) $previousCount);
        $this->addDelta($summary, 'payments_amount', $currentAmount, $previousAmount);

        $series = $this->amountSeries(
            ServicePaymentQuery::wherePaid(MembershipTransaction::query()),
            'created_at',
            DB::raw(ServicePaymentQuery::membershipPaidAmountExpression()),
            $period['from'],
            $period['to'],
            $period['days'],
            'payments_count',
            'payments_amount',
        );

        $breakdown = MembershipTransaction::query()
            ->selectRaw('status, COUNT(*) as count, SUM('.ServicePaymentQuery::membershipPaidAmountExpression().') as amount')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->status,
                'count' => (int) $row->count,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();

        $details = $this->paginateDetails(
            MembershipTransaction::query()
                ->with(['user:id,email', 'membershipPlan:id,title'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (MembershipTransaction $transaction): array => [
                'id' => $transaction->id,
                'status' => $transaction->status,
                'amount' => (float) ($transaction->amount_charged ?: $transaction->amount),
                'user_email' => $transaction->user?->email,
                'plan' => $transaction->membershipPlan?->title,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('member-subscriptions', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPromotions(Request $request): array
    {
        $period = $this->period->resolve($request);
        $listing = $this->listingPerformanceForRange($period['from'], $period['to']);
        $previousListing = $this->listingPerformanceForRange($period['previous_from'], $period['previous_to']);

        $currentPayments = (float) ServicePaymentQuery::wherePaid(PromotionTransaction::query())
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->sum('amount');
        $previousPayments = (float) ServicePaymentQuery::wherePaid(PromotionTransaction::query())
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->sum('amount');

        $promotionSpend = (float) $listing['totals']['promotion_spend'];
        $previousSpend = (float) $previousListing['totals']['promotion_spend'];
        $contactViews = (int) $listing['totals']['contact_views'];

        $summary = [];
        $this->addDelta($summary, 'promotion_payments', $currentPayments, $previousPayments);
        $this->addDelta($summary, 'promotion_spend', $promotionSpend, $previousSpend);
        $this->addDelta(
            $summary,
            'contact_views',
            (float) $contactViews,
            (float) $previousListing['totals']['contact_views'],
        );
        $summary['roi_contact_views_per_spend'] = $promotionSpend > 0
            ? round($contactViews / $promotionSpend, 4)
            : null;

        $series = collect($listing['series'])->map(fn (array $row): array => [
            'date' => $row['date'] ?? $row['label'],
            'label' => $row['label'],
            'promotion_spend' => (float) $row['promotion_spend'],
            'contact_views' => (int) $row['contact_views'],
        ])->values()->all();

        $breakdown = PromotionTransaction::query()
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as amount')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->status,
                'count' => (int) $row->count,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();

        $details = $this->paginateDetails(
            PromotionTransaction::query()
                ->with(['user:id,email', 'product:id,slug'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (PromotionTransaction $transaction): array => [
                'id' => $transaction->id,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'user_email' => $transaction->user?->email,
                'product_slug' => $transaction->product?->slug,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('promotions', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserWallet(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentQuery = WalletDeposit::query()->whereBetween('created_at', [$period['from'], $period['to']]);
        $previousQuery = WalletDeposit::query()->whereBetween('created_at', [$period['previous_from'], $period['previous_to']]);

        $currentCount = (int) (clone $currentQuery)->count();
        $previousCount = (int) (clone $previousQuery)->count();
        $currentAmount = (float) (clone $currentQuery)->sum('amount');
        $previousAmount = (float) (clone $previousQuery)->sum('amount');

        $summary = [];
        $this->addDelta($summary, 'deposits_count', (float) $currentCount, (float) $previousCount);
        $this->addDelta($summary, 'deposits_amount', $currentAmount, $previousAmount);
        $summary['total_wallet_balance'] = round((float) User::query()->sum('wallet_balance'), 2);

        $series = $this->amountSeries(
            WalletDeposit::query(),
            'created_at',
            'amount',
            $period['from'],
            $period['to'],
            $period['days'],
            'deposits_count',
            'deposits_amount',
        );

        $breakdown = WalletDeposit::query()
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as amount')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->status,
                'count' => (int) $row->count,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();

        $details = $this->paginateDetails(
            WalletDeposit::query()
                ->with('user:id,email,slug')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (WalletDeposit $deposit): array => [
                'id' => $deposit->id,
                'status' => $deposit->status,
                'amount' => (float) $deposit->amount,
                'payment_method' => $deposit->payment_method,
                'user_email' => $deposit->user?->email,
                'created_at' => $deposit->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('user-wallet', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBankTransfers(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentQuery = BankTransferRequest::query()->whereBetween('created_at', [$period['from'], $period['to']]);
        $previousQuery = BankTransferRequest::query()->whereBetween('created_at', [$period['previous_from'], $period['previous_to']]);

        $currentCount = (int) (clone $currentQuery)->count();
        $previousCount = (int) (clone $previousQuery)->count();
        $currentAmount = (float) DB::table('bank_transfer_requests as btr')
            ->leftJoin('orders', 'orders.order_number', '=', 'btr.order_number')
            ->whereBetween('btr.created_at', [$period['from'], $period['to']])
            ->sum('orders.price_total');
        $previousAmount = (float) DB::table('bank_transfer_requests as btr')
            ->leftJoin('orders', 'orders.order_number', '=', 'btr.order_number')
            ->whereBetween('btr.created_at', [$period['previous_from'], $period['previous_to']])
            ->sum('orders.price_total');

        $summary = [];
        $this->addDelta($summary, 'transfers_count', (float) $currentCount, (float) $previousCount);
        $this->addDelta($summary, 'transfers_amount', $currentAmount, $previousAmount);

        $series = $this->countSeries(
            BankTransferRequest::query(),
            'created_at',
            $period['from'],
            $period['to'],
            $period['days'],
        );

        $breakdown = BankTransferRequest::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(function ($row) use ($period): array {
                $amount = (float) DB::table('bank_transfer_requests as btr')
                    ->leftJoin('orders', 'orders.order_number', '=', 'btr.order_number')
                    ->where('btr.status', $row->status)
                    ->whereBetween('btr.created_at', [$period['from'], $period['to']])
                    ->sum('orders.price_total');

                return [
                    'label' => (string) $row->status,
                    'count' => (int) $row->count,
                    'amount' => round($amount, 2),
                ];
            })
            ->values()
            ->all();

        $details = $this->paginateDetails(
            BankTransferRequest::query()
                ->with('user:id,email,slug')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (BankTransferRequest $transfer): array => [
                'id' => $transfer->id,
                'status' => $transfer->status,
                'order_number' => $transfer->order_number,
                'payment_note' => $transfer->payment_note,
                'user_email' => $transfer->user?->email,
                'created_at' => $transfer->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('bank-transfers', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayoutsRefunds(Request $request): array
    {
        $period = $this->period->resolve($request);
        $payoutQuery = PayoutRequest::query()->whereBetween('created_at', [$period['from'], $period['to']]);
        $refundQuery = RefundRequest::query()->whereBetween('created_at', [$period['from'], $period['to']]);

        $currentPayoutAmount = (float) (clone $payoutQuery)->sum('amount');
        $previousPayoutAmount = (float) PayoutRequest::query()
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->sum('amount');
        $currentPayoutCount = (int) (clone $payoutQuery)->count();
        $previousPayoutCount = (int) PayoutRequest::query()
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->count();
        $currentRefundCount = (int) (clone $refundQuery)->count();
        $previousRefundCount = (int) RefundRequest::query()
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->count();

        $summary = [];
        $this->addDelta($summary, 'payout_count', (float) $currentPayoutCount, (float) $previousPayoutCount);
        $this->addDelta($summary, 'payout_amount', $currentPayoutAmount, $previousPayoutAmount);
        $this->addDelta($summary, 'refund_requests', (float) $currentRefundCount, (float) $previousRefundCount);

        $series = $this->mergeDualCountSeries(
            $period['from'],
            $period['to'],
            $period['days'],
            PayoutRequest::query(),
            'payouts',
            fn (CarbonInterface $from, CarbonInterface $to, int $days) => $this->countSeries(
                RefundRequest::query(),
                'created_at',
                $from,
                $to,
                $days,
            ),
            'refunds',
        );

        $payoutBreakdown = PayoutRequest::query()
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as amount')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('status')
            ->get()
            ->map(fn ($row): array => [
                'label' => 'Payout: '.$row->status,
                'count' => (int) $row->count,
                'amount' => round((float) $row->amount, 2),
            ]);

        $refundBreakdown = RefundRequest::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('status')
            ->get()
            ->map(fn ($row): array => [
                'label' => 'Refund: '.$row->status,
                'count' => (int) $row->count,
            ]);

        $breakdown = $payoutBreakdown->merge($refundBreakdown)->values()->all();

        $union = PayoutRequest::query()
            ->selectRaw("'payout' as row_type, id, status, amount, created_at, NULL as detail, seller_id as actor_id")
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->unionAll(
                RefundRequest::query()
                    ->selectRaw("'refund' as row_type, id, status, NULL as amount, created_at, description as detail, buyer_id as actor_id")
                    ->whereBetween('created_at', [$period['from'], $period['to']]),
            );

        $details = $this->paginateUnionQuery($request, $union);
        $userEmails = User::query()
            ->whereIn(
                'id',
                collect($details['data'])->pluck('actor_id')->filter()->unique()->values()->all(),
            )
            ->pluck('email', 'id');

        $details['data'] = collect($details['data'])->map(fn (array $row): array => [
            'type' => (string) $row['row_type'],
            'id' => (int) $row['id'],
            'status' => (string) $row['status'],
            'amount' => $row['amount'] !== null ? (float) $row['amount'] : null,
            'detail' => $row['detail'],
            'user_email' => $userEmails[(int) $row['actor_id']] ?? null,
            'created_at' => Carbon::parse((string) $row['created_at'])->toIso8601String(),
        ])->values()->all();

        return $this->envelope('payouts-refunds', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEscrow(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentQuery = EscrowTransaction::query()->whereBetween('created_at', [$period['from'], $period['to']]);
        $previousQuery = EscrowTransaction::query()->whereBetween('created_at', [$period['previous_from'], $period['previous_to']]);

        $currentCount = (int) (clone $currentQuery)->count();
        $previousCount = (int) (clone $previousQuery)->count();
        $currentAmount = (float) (clone $currentQuery)->sum('amount');
        $previousAmount = (float) (clone $previousQuery)->sum('amount');

        $summary = [];
        $this->addDelta($summary, 'escrow_count', (float) $currentCount, (float) $previousCount);
        $this->addDelta($summary, 'escrow_amount', $currentAmount, $previousAmount);
        $summary['escrow_active'] = (int) EscrowTransaction::query()
            ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
            ->count();

        $series = $this->amountSeries(
            EscrowTransaction::query(),
            'created_at',
            'amount',
            $period['from'],
            $period['to'],
            $period['days'],
            'escrow_count',
            'escrow_amount',
        );

        $breakdown = EscrowTransaction::query()
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as amount')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->status,
                'count' => (int) $row->count,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();

        $details = $this->paginateDetails(
            EscrowTransaction::query()
                ->with(['buyer:id,email', 'seller:id,email', 'order:id,order_number'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (EscrowTransaction $transaction): array => [
                'id' => $transaction->id,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'buyer_email' => $transaction->buyer?->email,
                'seller_email' => $transaction->seller?->email,
                'order_number' => $transaction->order?->order_number,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('escrow', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReviews(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentCount = (int) ProductReview::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->count();
        $previousCount = (int) ProductReview::query()
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->count();

        $summary = [];
        $this->addDelta($summary, 'reviews', (float) $currentCount, (float) $previousCount);

        $series = $this->countSeries(
            ProductReview::query(),
            'created_at',
            $period['from'],
            $period['to'],
            $period['days'],
        );

        $rows = ProductReview::query()
            ->selectRaw('rating, COUNT(*) as count')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $breakdown = collect(range(5, 1))->map(fn (int $rating): array => [
            'label' => (string) $rating.' stars',
            'count' => (int) ($rows[$rating] ?? 0),
        ])->values()->all();

        $details = $this->paginateDetails(
            ProductReview::query()
                ->with(['user:id,email', 'product:id,slug'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (ProductReview $review): array => [
                'id' => $review->id,
                'rating' => (int) $review->rating,
                'review' => $review->review,
                'is_approved' => (bool) $review->is_approved,
                'user_email' => $review->user?->email,
                'product_slug' => $review->product?->slug,
                'created_at' => $review->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('reviews', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSupportModeration(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentTickets = (int) SupportTicket::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->count();
        $previousTickets = (int) SupportTicket::query()
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->count();
        $previousAbuseReports = (int) DB::table('abuse_reports')
            ->whereBetween('created_at', [$period['previous_from'], $period['previous_to']])
            ->count();
        $pendingProducts = (int) Product::query()->adminPendingModeration()->count();
        $openTickets = (int) SupportTicket::query()->whereIn('status', ['open', 'pending'])->count();
        $abuseReports = (int) DB::table('abuse_reports')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->count();

        $summary = [];
        $this->addDelta($summary, 'support_tickets', (float) $currentTickets, (float) $previousTickets);
        $this->addDelta($summary, 'abuse_reports', (float) $abuseReports, (float) $previousAbuseReports);
        $summary['pending_products'] = $pendingProducts;
        $summary['open_support_tickets'] = $openTickets;

        $series = $this->mergeDualCountSeries(
            $period['from'],
            $period['to'],
            $period['days'],
            SupportTicket::query(),
            'tickets',
            fn (CarbonInterface $from, CarbonInterface $to, int $days) => $this->tableCountSeries(
                'abuse_reports',
                $from,
                $to,
                $days,
                'count',
            ),
            'abuse',
        );

        $breakdown = SupportTicket::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $breakdown[] = ['label' => 'Pending products (snapshot)', 'count' => $pendingProducts];
        $breakdown[] = ['label' => 'Abuse reports', 'count' => $abuseReports];

        $details = $this->paginateUnionQuery(
            $request,
            SupportTicket::query()
                ->selectRaw("'ticket' as row_type, id, status, NULL as amount, created_at, subject as detail, user_id as actor_id")
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->unionAll(
                    DB::table('abuse_reports')
                        ->selectRaw("'abuse' as row_type, id, status, NULL as amount, created_at, report_type as detail, reporter_id as actor_id")
                        ->whereBetween('created_at', [$period['from'], $period['to']]),
                ),
        );
        $userEmails = User::query()
            ->whereIn(
                'id',
                collect($details['data'])->pluck('actor_id')->filter()->unique()->values()->all(),
            )
            ->pluck('email', 'id');

        $details['data'] = collect($details['data'])->map(fn (array $row): array => [
            'type' => (string) $row['row_type'],
            'id' => (int) $row['id'],
            'status' => (string) $row['status'],
            'detail' => $row['detail'],
            'user_email' => $userEmails[(int) $row['actor_id']] ?? null,
            'created_at' => Carbon::parse((string) $row['created_at'])->toIso8601String(),
        ])->values()->all();

        return $this->envelope('support-moderation', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAffiliate(Request $request): array
    {
        $period = $this->period->resolve($request);
        $currentQuery = AffiliateEarning::query()->whereBetween('created_at', [$period['from'], $period['to']]);
        $previousQuery = AffiliateEarning::query()->whereBetween('created_at', [$period['previous_from'], $period['previous_to']]);

        $currentAmount = (float) (clone $currentQuery)->sum('earned_amount');
        $previousAmount = (float) (clone $previousQuery)->sum('earned_amount');
        $currentCount = (int) (clone $currentQuery)->count();
        $previousCount = (int) (clone $previousQuery)->count();

        $summary = [];
        $this->addDelta($summary, 'affiliate_earnings', $currentAmount, $previousAmount);
        $this->addDelta($summary, 'affiliate_records', (float) $currentCount, (float) $previousCount);

        $series = $this->amountSeries(
            AffiliateEarning::query(),
            'created_at',
            'earned_amount',
            $period['from'],
            $period['to'],
            $period['days'],
            'records_count',
            'earned_amount',
        );

        $breakdown = AffiliateEarning::query()
            ->selectRaw('referrer_id, COUNT(*) as count, SUM(earned_amount) as amount')
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->groupBy('referrer_id')
            ->orderByDesc('amount')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $referrer = User::query()->find($row->referrer_id);

                return [
                    'label' => $referrer?->email ?? 'Referrer #'.$row->referrer_id,
                    'count' => (int) $row->count,
                    'amount' => round((float) $row->amount, 2),
                ];
            })
            ->values()
            ->all();

        $details = $this->paginateDetails(
            AffiliateEarning::query()
                ->with(['referrer:id,email', 'seller:id,email', 'product:id,slug'])
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->orderByDesc('id'),
            $request,
            fn (AffiliateEarning $earning): array => [
                'id' => $earning->id,
                'earned_amount' => (float) $earning->earned_amount,
                'commission_rate' => (float) $earning->commission_rate,
                'referrer_email' => $earning->referrer?->email,
                'seller_email' => $earning->seller?->email,
                'product_slug' => $earning->product?->slug,
                'created_at' => $earning->created_at?->toIso8601String(),
            ],
        );

        return $this->envelope('affiliate', $period, $summary, $series, $breakdown, $details);
    }

    /**
     * @param  array{from: CarbonInterface, to: CarbonInterface, previous_from: CarbonInterface, previous_to: CarbonInterface, days: int}  $period
     * @param  list<array<string, mixed>>  $series
     * @param  list<array<string, mixed>>  $breakdown
     * @param  array{data: list<mixed>, total: int, current_page: int, last_page: int, per_page: int}  $details
     * @return array<string, mixed>
     */
    private function envelope(
        string $report,
        array $period,
        array $summary,
        array $series,
        array $breakdown,
        array $details,
    ): array {
        return [
            'report' => $report,
            'period' => $this->period->payload($period),
            'summary' => $summary,
            'series' => $series,
            'breakdown' => $breakdown,
            'details' => $details,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyticsPayload(Request $request, CarbonInterface $from, CarbonInterface $to): array
    {
        $synthetic = Request::create('/', 'GET', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
        $synthetic->setUserResolver(fn () => $request->user());

        return $this->analytics->build($synthetic);
    }

    /**
     * @return array<string, mixed>
     */
    private function listingPerformanceForRange(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->listingPerformance->platformSummaryForRange(
            Carbon::parse($from->toDateString()),
            Carbon::parse($to->toDateString()),
            $from->format('d/m').' - '.$to->format('d/m'),
        );
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  callable(mixed): array<string, mixed>|null  $mapper
     * @return array{data: list<array<string, mixed>>, total: int, current_page: int, last_page: int, per_page: int}
     */
    private function paginateDetails(Builder $query, Request $request, ?callable $mapper = null): array
    {
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $paginator = $query->paginate($perPage);
        $mapper ??= fn ($row): array => $row instanceof \Illuminate\Database\Eloquent\Model ? $row->toArray() : (array) $row;

        return [
            'data' => collect($paginator->items())->map($mapper)->values()->all(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $unionQuery
     * @return array{data: list<array<string, mixed>>, total: int, current_page: int, last_page: int, per_page: int}
     */
    private function paginateUnionQuery(Request $request, Builder|\Illuminate\Database\Query\Builder $unionQuery): array
    {
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $page = max(1, (int) $request->input('page', 1));
        $paginator = DB::query()
            ->fromSub($unionQuery, 'combined')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (object $row): array => (array) $row)->values()->all(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * @param  array<string, float|int|null>  $summary
     */
    private function addDelta(array &$summary, string $key, float $current, float $previous): void
    {
        $summary[$key] = round($current, 2);
        $summary[$key.'_delta_pct'] = $this->percentChange($previous, $current);
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return list<array{date: string, total: int, completed: int, cancelled: int}>
     */
    private function ordersSeries(CarbonInterface $from, CarbonInterface $to, int $days): array
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
     * @return list<array{date: string, gmv: float, commission: float}>
     */
    private function revenueSeries(CarbonInterface $from, CarbonInterface $to, int $days): array
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
            return [
                'date' => $date,
                'gmv' => round((float) ($gmvRows[$date] ?? 0), 2),
                'commission' => round((float) ($commissionRows[$date] ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{date: string, users: int, vendors: int}>
     */
    private function signupsSeries(CarbonInterface $from, CarbonInterface $to, int $days): array
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
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return list<array{date: string, count: int}>
     */
    private function countSeries(
        Builder $query,
        string $dateColumn,
        CarbonInterface $from,
        CarbonInterface $to,
        int $days,
    ): array {
        $bucket = $days <= 90 ? 'day' : 'month';
        $points = $this->buildBuckets($from, $to, $bucket);

        $rows = (clone $query)
            ->selectRaw($this->dateBucketSelect($dateColumn, $bucket).' as bucket, COUNT(*) as total')
            ->whereBetween($dateColumn, [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return collect($points)->map(fn (string $date): array => [
            'date' => $date,
            'count' => (int) ($rows[$date] ?? 0),
        ])->values()->all();
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $amountColumn
     * @return list<array<string, mixed>>
     */
    private function amountSeries(
        Builder $query,
        string $dateColumn,
        $amountColumn,
        CarbonInterface $from,
        CarbonInterface $to,
        int $days,
        string $countKey,
        string $amountKey,
    ): array {
        $bucket = $days <= 90 ? 'day' : 'month';
        $points = $this->buildBuckets($from, $to, $bucket);
        $amountExpression = $amountColumn instanceof \Illuminate\Contracts\Database\Query\Expression
            ? $amountColumn->getValue(DB::connection()->getQueryGrammar())
            : $amountColumn;

        $countRows = (clone $query)
            ->selectRaw($this->dateBucketSelect($dateColumn, $bucket).' as bucket, COUNT(*) as total')
            ->whereBetween($dateColumn, [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $amountRows = (clone $query)
            ->selectRaw($this->dateBucketSelect($dateColumn, $bucket)." as bucket, SUM({$amountExpression}) as total")
            ->whereBetween($dateColumn, [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return collect($points)->map(function (string $date) use ($countRows, $amountRows, $countKey, $amountKey): array {
            return [
                'date' => $date,
                $countKey => (int) ($countRows[$date] ?? 0),
                $amountKey => round((float) ($amountRows[$date] ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * @return Builder<VendorEarning>
     */
    private function vendorEarningRangeQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return VendorEarning::query()
            ->where('is_refunded', false)
            ->whereHas('order', fn ($query) => $query
                ->where('payment_status', 'payment_received')
                ->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$from, $to]));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOrderDetail(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'price_total' => (float) $order->price_total,
            'currency_code' => $order->currency_code,
            'buyer_email' => $order->buyer?->email,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function overviewReportBreakdown(): array
    {
        $labels = [
            'traffic' => 'Traffic',
            'contact-views' => 'Contact views',
            'listing-performance' => 'Listing performance',
            'user-engagement' => 'User engagement',
            'product-performance' => 'Product performance',
            'sales' => 'Sales',
            'revenue' => 'Revenue',
            'digital-sales' => 'Digital sales',
            'vendor-sales' => 'Vendor sales',
            'quote-requests' => 'Quote requests',
            'geographic' => 'Geographic',
            'member-subscriptions' => 'Member subscriptions',
            'promotions' => 'Promotions & ad spend',
            'user-wallet' => 'User wallet',
            'bank-transfers' => 'Bank transfers',
            'payouts-refunds' => 'Payouts & refunds',
            'escrow' => 'Escrow',
            'reviews' => 'Reviews',
            'support-moderation' => 'Support & moderation',
            'affiliate' => 'Affiliate',
        ];

        return collect(self::TYPES)
            ->reject(fn (string $type): bool => $type === 'overview')
            ->map(fn (string $type): array => [
                'label' => $labels[$type] ?? $type,
                'count' => 0,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  callable(CarbonInterface, CarbonInterface, int): list<array{date: string, count: int}>  $secondarySeries
     * @return list<array<string, mixed>>
     */
    private function mergeDualCountSeries(
        CarbonInterface $from,
        CarbonInterface $to,
        int $days,
        Builder $primaryQuery,
        string $primaryKey,
        callable $secondarySeries,
        string $secondaryKey,
    ): array {
        $primary = collect($this->countSeries($primaryQuery, 'created_at', $from, $to, $days))->keyBy('date');
        $secondary = collect($secondarySeries($from, $to, $days))->keyBy('date');
        $dates = $primary->keys()->merge($secondary->keys())->unique()->sort()->values();

        return $dates->map(function (string $date) use ($primary, $secondary, $primaryKey, $secondaryKey): array {
            return [
                'date' => $date,
                $primaryKey => (int) ($primary[$date]['count'] ?? 0),
                $secondaryKey => (int) ($secondary[$date]['count'] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    private function tableCountSeries(
        string $table,
        CarbonInterface $from,
        CarbonInterface $to,
        int $days,
        string $countKey = 'count',
    ): array {
        $bucket = $days <= 90 ? 'day' : 'month';
        $points = $this->buildBuckets($from, $to, $bucket);

        $rows = DB::table($table)
            ->selectRaw($this->dateBucketSelect('created_at', $bucket).' as bucket, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return collect($points)->map(fn (string $date): array => [
            'date' => $date,
            $countKey => (int) ($rows[$date] ?? 0),
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
}
