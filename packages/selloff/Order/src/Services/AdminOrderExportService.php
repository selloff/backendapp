<?php

namespace App\Modules\Selloff\Order\Services;

use App\Modules\Selloff\Order\Models\Order;
use App\Support\AdminTabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOrderExportService
{
    /** @var list<string> */
    private const HEADERS = [
        'ID',
        'Order Number',
        'Buyer',
        'Status',
        'Payment Status',
        'Payment Method',
        'Total',
        'Currency',
        'Created At',
    ];

    public function export(Request $request): StreamedResponse
    {
        $format = $this->normalizeFormat($request->string('format')->toString());

        return AdminTabularExport::stream('orders', $format, self::HEADERS, function () use ($request) {
            $query = $this->filteredQuery($request);

            foreach ($query->lazyById(200) as $order) {
                /** @var Order $order */
                yield $this->row($order);
            }
        });
    }

    private function filteredQuery(Request $request): Builder
    {
        return Order::query()
            ->with(['buyer'])
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn (Builder $q) => $q->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $term = $request->string('q')->toString();
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('order_number', 'like', '%'.$term.'%');
                    if (ctype_digit($term)) {
                        $inner->orWhere('id', (int) $term);
                    }
                });
            })
            ->orderByDesc('id');
    }

    /**
     * @return list<string|int|float|null>
     */
    private function row(Order $order): array
    {
        return [
            $order->id,
            $order->order_number,
            $order->buyer?->email,
            $order->status,
            $order->payment_status,
            $order->payment_method,
            $order->price_total,
            $order->currency_code,
            $order->created_at?->toIso8601String(),
        ];
    }

    private function normalizeFormat(string $format): string
    {
        return match ($format) {
            'xml', 'excel', 'xlsx' => $format === 'xlsx' ? 'excel' : $format,
            default => 'csv',
        };
    }
}
