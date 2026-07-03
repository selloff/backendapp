<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Payment\Models\PaymentTransaction;
use App\Support\AdminTabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTransactionExportService
{
    /** @var list<string> */
    private const HEADERS = [
        'ID',
        'Order Number',
        'Payment ID',
        'Payment Method',
        'Payment Status',
        'Amount',
        'Currency',
        'Buyer',
        'Created At',
    ];

    public function export(Request $request): StreamedResponse
    {
        $format = $this->normalizeFormat($request->string('format')->toString());

        return AdminTabularExport::stream('transactions', $format, self::HEADERS, function () use ($request) {
            foreach ($this->filteredQuery($request)->lazyById(200) as $transaction) {
                /** @var PaymentTransaction $transaction */
                yield $this->row($transaction);
            }
        });
    }

    private function filteredQuery(Request $request): Builder
    {
        return PaymentTransaction::query()
            ->with(['user', 'order'])
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $term = $request->string('q')->toString();
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('transaction_number', 'like', '%'.$term.'%')
                        ->orWhereHas('order', function (Builder $orderQuery) use ($term): void {
                            $orderQuery->where('order_number', 'like', '%'.$term.'%');
                            if (ctype_digit($term)) {
                                $orderQuery->orWhere('id', (int) $term);
                            }
                        });
                });
            })
            ->orderByDesc('id');
    }

    /**
     * @return list<string|int|float|null>
     */
    private function row(PaymentTransaction $transaction): array
    {
        return [
            $transaction->id,
            $transaction->order?->order_number,
            $transaction->transaction_number,
            $transaction->payment_method,
            $transaction->payment_status,
            $transaction->amount,
            $transaction->currency_code,
            $transaction->user?->email,
            $transaction->created_at?->toIso8601String(),
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
