<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Order\Services\AdminDigitalSaleExportService;
use App\Modules\Selloff\Order\Services\AdminDigitalSalePresenter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDigitalSaleController extends Controller
{
    public function index(Request $request, AdminDigitalSalePresenter $presenter): JsonResponse
    {
        $search = $request->filled('q') ? $request->string('q')->toString() : ($request->filled('search') ? $request->string('search')->toString() : null);

        $sales = DigitalSale::query()
            ->with([
                'buyer:id,first_name,last_name,email,slug',
                'seller:id,first_name,last_name,email,slug',
                'product:id,slug',
                'product.translations',
                'order:id,order_number,price_total,currency_code',
                'order.items:id,order_id,product_id,total_price',
            ])
            ->when($search, function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('purchase_code', 'like', $term)
                        ->orWhere('license_key', 'like', $term);
                });
            })
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 15), 100));

        $sales->through(fn (DigitalSale $sale) => $presenter->present($sale));

        return ApiResponse::success($sales);
    }

    public function export(Request $request, AdminDigitalSaleExportService $export): StreamedResponse
    {
        $format = $request->string('format')->toString();
        if ($format !== '' && ! in_array($format, ['csv', 'xml', 'excel', 'xlsx'], true)) {
            abort(422, 'Invalid export format.');
        }

        return $export->export($request);
    }

    public function destroy(DigitalSale $digitalSale): JsonResponse
    {
        $digitalSale->delete();

        return ApiResponse::success(null, message: 'Digital sale deleted.');
    }
}
