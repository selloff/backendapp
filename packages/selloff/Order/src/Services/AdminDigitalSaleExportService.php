<?php

namespace App\Modules\Selloff\Order\Services;

use App\Support\AdminTabularExport;
use App\Modules\Selloff\Order\Services\AdminDigitalSalePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDigitalSaleExportService
{
    public function __construct(
        private readonly AdminDigitalSalePresenter $presenter,
    ) {}

    /** @var list<string> */
    private const HEADERS = [
        'ID',
        'Purchase Code',
        'License Key',
        'Order Number',
        'Product',
        'Buyer',
        'Seller',
        'Total',
        'Currency',
        'Created At',
    ];

    public function export(Request $request): StreamedResponse
    {
        $format = $this->normalizeFormat($request->string('format')->toString());

        return AdminTabularExport::stream('digital-sales', $format, self::HEADERS, function () use ($request) {
            foreach ($this->filteredQuery($request)->lazyById(200) as $sale) {
                /** @var DigitalSale $sale */
                yield $this->row($sale);
            }
        });
    }

    private function filteredQuery(Request $request): Builder
    {
        $search = $request->filled('q') ? $request->string('q')->toString() : ($request->filled('search') ? $request->string('search')->toString() : null);

        return DigitalSale::query()
            ->with([
                'buyer:id,first_name,last_name,email,slug',
                'seller:id,first_name,last_name,email,slug',
                'product:id,slug',
                'product.translations',
                'order:id,order_number,price_total,currency_code',
                'order.items:id,order_id,product_id,total_price',
            ])
            ->when($search, function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('purchase_code', 'like', $term)
                        ->orWhere('license_key', 'like', $term);
                });
            })
            ->orderByDesc('id');
    }

    /**
     * @return list<string|int|float|null>
     */
    private function row(DigitalSale $sale): array
    {
        $presented = $this->presenter->present($sale);

        return [
            $presented['id'],
            $presented['purchase_code'],
            $presented['license_key'],
            $presented['order_number'],
            $presented['product_title'],
            $presented['buyer']['email'] ?? null,
            $presented['seller']['email'] ?? null,
            $presented['price'],
            $presented['currency_code'],
            $sale->created_at?->toIso8601String(),
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
