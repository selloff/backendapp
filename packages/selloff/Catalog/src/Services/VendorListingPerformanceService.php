<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductListingDailyMetric;
use App\Modules\Selloff\Catalog\Models\VendorListingContactView;
use App\Modules\Selloff\Catalog\Support\ListingMetricsSchema;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VendorListingPerformanceService
{
    /** @var list<string> */
    public const PERIODS = ['24h', '7d', '14d', '1m', '6m', '1y'];

    /**
     * @return array<string, mixed>
     */
    public function summary(User $vendor, string $period): array
    {
        return $this->buildSummary($vendor, $period);
    }

    /**
     * @return array<string, mixed>
     */
    public function platformSummary(string $period): array
    {
        return $this->buildSummary(null, $period);
    }

    /**
     * @return array<string, mixed>
     */
    public function platformSummaryForRange(Carbon $from, Carbon $to, string $periodLabel): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return $this->assembleSummary(
            null,
            'custom',
            $periodLabel,
            $from,
            $to,
            $this->bucketsForDateRange($from, $to),
            false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(?User $vendor, string $period): array
    {
        $period = $this->normalizePeriod($period);
        [$start, $end, $bucketKeys] = $this->rangeForPeriod($period);

        return $this->assembleSummary(
            $vendor,
            $period,
            $this->periodLabel($period),
            $start,
            $end,
            $bucketKeys,
            $period === '24h',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $bucketKeys
     * @return array<string, mixed>
     */
    private function assembleSummary(
        ?User $vendor,
        string $period,
        string $periodLabel,
        Carbon $start,
        Carbon $end,
        array $bucketKeys,
        bool $hourlyRange,
    ): array {
        [$rangeStart, $rangeEnd] = $hourlyRange
            ? [$start->copy(), $end->copy()]
            : [$start->copy()->startOfDay(), $end->copy()->endOfDay()];

        $listingDailyRows = $this->listingDailyRows($vendor, $rangeStart, $rangeEnd);

        $chatDateSql = $this->sqlDate('created_at');
        $promotionDateSql = $this->sqlDate('created_at');

        $chatCounts = $this->dailyCounts(
            $this->conversationQuery($vendor)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->selectRaw("{$chatDateSql} as metric_date, COUNT(*) as total")
                ->groupByRaw($chatDateSql),
        );

        $promotionTotals = $this->dailyCounts(
            $this->promotionQuery($vendor)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->selectRaw("{$promotionDateSql} as metric_date, SUM(amount) as total")
                ->groupByRaw($promotionDateSql),
        );

        $contactViewCounts = $this->dailyCounts(
            $this->contactViewQuery($vendor)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->selectRaw("{$chatDateSql} as metric_date, COUNT(*) as total")
                ->groupByRaw($chatDateSql),
        );

        $series = collect($bucketKeys)->map(function (array $bucket) use (
            $vendor,
            $listingDailyRows,
            $chatCounts,
            $promotionTotals,
            $contactViewCounts,
            $hourlyRange,
        ) {
            if (($bucket['granularity'] ?? 'day') === 'hour') {
                return $this->hourlyBucketMetrics(
                    $vendor,
                    $bucket,
                    $listingDailyRows,
                );
            }

            $traffic = 0;
            $visitors = 0;
            $impressions = 0;
            $contactViews = 0;
            $chats = 0;
            $promotionSpend = 0.0;

            foreach ($bucket['dates'] as $date) {
                $listing = $listingDailyRows->get($date);

                $traffic += (int) ($listing->traffic ?? 0);
                $visitors += (int) ($listing->visitors ?? 0);
                $impressions += (int) ($listing->impressions ?? 0);
                $contactViews += (int) ($listing->contact_views ?? 0);
                $chats += (int) ($chatCounts[$date] ?? 0);
                $promotionSpend += (float) ($promotionTotals[$date] ?? 0);
            }

            if ($contactViews === 0 && ! $hourlyRange) {
                foreach ($bucket['dates'] as $date) {
                    $contactViews += (int) ($contactViewCounts[$date] ?? 0);
                }
            }

            return [
                'label' => $bucket['label'],
                'date' => $bucket['dates'][0] ?? null,
                'traffic' => $traffic,
                'visitors' => $visitors,
                'impressions' => $impressions,
                'contact_views' => $contactViews,
                'chats' => $chats,
                'promotion_spend' => round($promotionSpend, 2),
            ];
        })->values();

        $totals = [
            'traffic' => (int) $series->sum('traffic'),
            'visitors' => (int) $series->sum('visitors'),
            'impressions' => (int) $series->sum('impressions'),
            'contact_views' => (int) $series->sum('contact_views'),
            'chats' => (int) $series->sum('chats'),
            'promotion_spend' => round((float) $series->sum('promotion_spend'), 2),
        ];

        return [
            'period' => $period,
            'period_label' => $periodLabel,
            'range_label' => $this->rangeLabelForDates($start, $end, $hourlyRange),
            'currency_code' => $vendor?->selected_currency_code ?? $this->defaultCurrencyCode(),
            'series' => $series,
            'totals' => $totals,
            'top_listings' => $this->topListingsByViews($vendor, $rangeStart, $rangeEnd),
            'recent' => [
                'contact_views' => $this->recentContactViews($vendor, $rangeStart, $rangeEnd),
                'chats' => $this->recentChats($vendor, $rangeStart, $rangeEnd),
            ],
        ];
    }

    public function normalizePeriod(string $period): string
    {
        $period = match ($period) {
            'daily' => '14d',
            'weekly' => '7d',
            'monthly' => '1m',
            default => $period,
        };

        return in_array($period, self::PERIODS, true) ? $period : '7d';
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRangeForPeriod(string $period): array
    {
        $period = $this->normalizePeriod($period);
        [$start, $end] = $this->rangeForPeriod($period);

        if ($period === '24h') {
            return [$start->copy(), now()];
        }

        return [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
    }

    private function defaultCurrencyCode(): string
    {
        $settings = app(PlatformSettingsService::class)->all();

        return (string) ($settings['default_currency'] ?? 'NGN');
    }

    /**
     * @return \Illuminate\Support\Collection<string, object>
     */
    private function listingDailyRows(?User $vendor, Carbon $rangeStart, Carbon $rangeEnd)
    {
        if (! ListingMetricsSchema::hasProductDailyMetricsTable()) {
            return collect();
        }

        $query = ProductListingDailyMetric::query()
            ->whereBetween('metric_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);

        if ($vendor !== null) {
            $query->where('vendor_id', $vendor->id);
        }

        $impressionsSelect = ListingMetricsSchema::impressionsSumSql();

        return $query
            ->selectRaw("metric_date, SUM(traffic) as traffic, SUM(visitors) as visitors, {$impressionsSelect}, SUM(contact_views) as contact_views")
            ->groupBy('metric_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->metric_date)->toDateString());
    }

    private function conversationQuery(?User $vendor)
    {
        $query = Conversation::query();

        if ($vendor !== null) {
            $query->where('receiver_id', $vendor->id);
        }

        return $query;
    }

    private function promotionQuery(?User $vendor)
    {
        $query = PromotionTransaction::query()->where('status', 'completed');

        if ($vendor !== null) {
            $query->where('user_id', $vendor->id);
        }

        return $query;
    }

    private function contactViewQuery(?User $vendor)
    {
        $query = VendorListingContactView::query();

        if ($vendor !== null) {
            $query->where('vendor_id', $vendor->id);
        }

        return $query;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, object>  $listingDailyRows
     * @return array<string, mixed>
     */
    private function hourlyBucketMetrics(
        ?User $vendor,
        array $bucket,
        $listingDailyRows,
    ): array {
        /** @var Carbon $bucketStart */
        $bucketStart = $bucket['starts_at'];
        /** @var Carbon $bucketEnd */
        $bucketEnd = $bucket['ends_at'];

        $date = $bucketStart->toDateString();
        $listing = $listingDailyRows->get($date);

        $dailyTraffic = (int) ($listing->traffic ?? 0);
        $dailyVisitors = (int) ($listing->visitors ?? 0);
        $dailyImpressions = (int) ($listing->impressions ?? 0);
        $dailyContactViews = (int) ($listing->contact_views ?? 0);

        $hoursInDay = 24;
        $traffic = (int) round($dailyTraffic / $hoursInDay);
        $visitors = (int) round($dailyVisitors / $hoursInDay);
        $impressions = (int) round($dailyImpressions / $hoursInDay);
        $contactViews = (int) round($dailyContactViews / $hoursInDay);

        $chats = $this->conversationQuery($vendor)
            ->whereBetween('created_at', [$bucketStart, $bucketEnd])
            ->count();

        $promotionSpend = (float) $this->promotionQuery($vendor)
            ->whereBetween('created_at', [$bucketStart, $bucketEnd])
            ->sum('amount');

        if ($contactViews === 0) {
            $contactViews = $this->contactViewQuery($vendor)
                ->whereBetween('created_at', [$bucketStart, $bucketEnd])
                ->count();
        }

        return [
            'label' => $bucket['label'],
            'date' => $bucketStart->toIso8601String(),
            'traffic' => $traffic,
            'visitors' => $visitors,
            'impressions' => $impressions,
            'contact_views' => $contactViews,
            'chats' => $chats,
            'promotion_spend' => round($promotionSpend, 2),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function dailyCounts($query): array
    {
        $counts = [];

        foreach ($query->get() as $row) {
            $counts[(string) $row->metric_date] = is_numeric($row->total) ? $row->total + 0 : 0;
        }

        return $counts;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: list<array<string, mixed>>}
     */
    private function rangeForPeriod(string $period): array
    {
        $end = now();
        $buckets = [];

        if ($period === '24h') {
            $start = $end->copy()->subHours(23)->startOfHour();
            for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addHour()) {
                $bucketEnd = $cursor->copy()->endOfHour();
                if ($bucketEnd->gt($end)) {
                    $bucketEnd = $end->copy();
                }
                $buckets[] = [
                    'label' => $cursor->format('H:i'),
                    'starts_at' => $cursor->copy(),
                    'ends_at' => $bucketEnd,
                    'dates' => [$cursor->toDateString()],
                    'granularity' => 'hour',
                ];
            }

            return [$start, $end, $buckets];
        }

        $end = now()->endOfDay();

        if ($period === '6m') {
            $start = now()->subMonths(6)->addDay()->startOfDay();
            for ($cursor = $start->copy()->startOfWeek(); $cursor->lte($end); $cursor->addWeek()) {
                $weekStart = $cursor->copy()->startOfWeek();
                if ($weekStart->lt($start)) {
                    $weekStart = $start->copy();
                }
                $weekEnd = $cursor->copy()->endOfWeek();
                if ($weekEnd->gt($end)) {
                    $weekEnd = $end->copy();
                }
                $dates = $this->datesBetween($weekStart, $weekEnd);
                if ($dates === []) {
                    continue;
                }
                $buckets[] = [
                    'label' => $weekStart->format('d/m'),
                    'dates' => $dates,
                    'granularity' => 'day',
                ];
            }

            return [$start, $end, $buckets];
        }

        if ($period === '1y') {
            $start = now()->subYear()->addDay()->startOfDay();
            for ($cursor = $start->copy()->startOfMonth(); $cursor->lte($end); $cursor->addMonth()) {
                $monthStart = $cursor->copy()->startOfMonth();
                if ($monthStart->lt($start)) {
                    $monthStart = $start->copy();
                }
                $monthEnd = $cursor->copy()->endOfMonth();
                if ($monthEnd->gt($end)) {
                    $monthEnd = $end->copy();
                }
                $dates = $this->datesBetween($monthStart, $monthEnd);
                if ($dates === []) {
                    continue;
                }
                $buckets[] = [
                    'label' => $monthStart->format('M y'),
                    'dates' => $dates,
                    'granularity' => 'day',
                ];
            }

            return [$start, $end, $buckets];
        }

        $start = match ($period) {
            '7d' => now()->subDays(6)->startOfDay(),
            '14d' => now()->subDays(13)->startOfDay(),
            '1m' => now()->subMonth()->addDay()->startOfDay(),
            default => now()->subDays(6)->startOfDay(),
        };

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $buckets[] = [
                'label' => $cursor->format('d/m'),
                'dates' => [$cursor->toDateString()],
                'granularity' => 'day',
            ];
        }

        return [$start, $end, $buckets];
    }

    /**
     * @return list<string>
     */
    private function datesBetween(Carbon $start, Carbon $end): array
    {
        $dates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            '24h' => 'Last 24 hours',
            '7d' => 'Last 7 days',
            '14d' => 'Last 2 weeks',
            '1m' => 'Last 1 month',
            '6m' => 'Last 6 months',
            '1y' => 'Last 1 year',
            default => 'Last 7 days',
        };
    }

    private function rangeLabelForDates(Carbon $start, Carbon $end, bool $hourlyRange): string
    {
        if ($hourlyRange) {
            return $start->format('d/m H:i').' - '.$end->format('d/m H:i');
        }

        return $start->format('d/m').' - '.$end->format('d/m');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bucketsForDateRange(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $toDay = $to->copy()->startOfDay();
        $days = max(1, $from->diffInDays($toDay) + 1);
        $buckets = [];

        if ($days <= 90) {
            for ($cursor = $from->copy(); $cursor->lte($toDay); $cursor->addDay()) {
                $buckets[] = [
                    'label' => $cursor->format('d/m'),
                    'dates' => [$cursor->toDateString()],
                    'granularity' => 'day',
                ];
            }

            return $buckets;
        }

        if ($days <= 180) {
            for ($cursor = $from->copy()->startOfWeek(); $cursor->lte($toDay); $cursor->addWeek()) {
                $weekStart = $cursor->copy()->startOfWeek();
                if ($weekStart->lt($from)) {
                    $weekStart = $from->copy();
                }
                $weekEnd = $cursor->copy()->endOfWeek();
                if ($weekEnd->gt($toDay)) {
                    $weekEnd = $toDay->copy();
                }
                $dates = $this->datesBetween($weekStart, $weekEnd);
                if ($dates === []) {
                    continue;
                }
                $buckets[] = [
                    'label' => $weekStart->format('d/m'),
                    'dates' => $dates,
                    'granularity' => 'day',
                ];
            }

            return $buckets;
        }

        for ($cursor = $from->copy()->startOfMonth(); $cursor->lte($toDay); $cursor->addMonth()) {
            $monthStart = $cursor->copy()->startOfMonth();
            if ($monthStart->lt($from)) {
                $monthStart = $from->copy();
            }
            $monthEnd = $cursor->copy()->endOfMonth();
            if ($monthEnd->gt($toDay)) {
                $monthEnd = $toDay->copy();
            }
            $dates = $this->datesBetween($monthStart, $monthEnd);
            if ($dates === []) {
                continue;
            }
            $buckets[] = [
                'label' => $monthStart->format('M y'),
                'dates' => $dates,
                'granularity' => 'day',
            ];
        }

        return $buckets;
    }

    private function sqlDate(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "{$column}::date",
            'sqlite' => "date({$column})",
            default => "DATE({$column})",
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topListingsByViews(?User $vendor, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $query = ProductListingDailyMetric::query()
            ->whereBetween('metric_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);

        if ($vendor !== null) {
            $query->where('vendor_id', $vendor->id);
        }

        $rows = $query
            ->selectRaw('product_id, SUM(traffic) as views')
            ->groupBy('product_id')
            ->havingRaw('SUM(traffic) > 0')
            ->orderByDesc('views')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->with(['vendor:id,first_name,last_name,slug', 'translations'])
            ->whereIn('id', $rows->pluck('product_id'))
            ->get(['id', 'slug', 'is_promoted', 'vendor_id'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($products, $vendor) {
            $product = $products->get($row->product_id);
            $translation = $product?->translations->firstWhere('locale', 'en')
                ?? $product?->translations->first();
            $item = [
                'product_id' => (int) $row->product_id,
                'title' => $translation?->title ?? 'Listing #'.$row->product_id,
                'slug' => $product?->slug,
                'views' => (int) $row->views,
                'is_promoted' => (bool) ($product?->is_promoted ?? false),
            ];

            if ($vendor === null && $product?->vendor !== null) {
                $item['vendor_id'] = $product->vendor->id;
                $item['vendor_name'] = $product->vendor->name;
                $item['vendor_slug'] = $product->vendor->slug;
            }

            return $item;
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentContactViews(?User $vendor, Carbon $start, Carbon $end): array
    {
        return $this->contactViewQuery($vendor)
            ->with('viewer')
            ->whereNotNull('viewer_id')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (VendorListingContactView $view) => [
                'id' => $view->id,
                'created_at' => $view->created_at?->toIso8601String(),
                'user' => $this->formatUser($view->viewer),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentChats(?User $vendor, Carbon $start, Carbon $end): array
    {
        return $this->conversationQuery($vendor)
            ->with('sender')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'created_at' => $conversation->created_at?->toIso8601String(),
                'user' => $this->formatUser($conversation->sender),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => trim($user->first_name.' '.$user->last_name) ?: $user->name,
            'slug' => $user->slug,
            'avatar_url' => $user->avatar ?? null,
        ];
    }
}
