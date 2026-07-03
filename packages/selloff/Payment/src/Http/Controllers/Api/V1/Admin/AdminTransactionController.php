<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payment\Models\PaymentTransaction;
use App\Modules\Selloff\Payment\Services\AdminTransactionExportService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PaymentTransaction::query()
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

        $paginator = $query->paginate(min($request->integer('per_page', 15), 100));
        $paginator->through(fn (PaymentTransaction $transaction) => [
            'id' => $transaction->id,
            'order_id' => $transaction->order_id,
            'order_number' => $transaction->order?->order_number,
            'payment_method' => $transaction->payment_method,
            'payment_id' => $transaction->transaction_number,
            'payment_status' => $transaction->payment_status,
            'amount' => $transaction->amount,
            'currency_code' => $transaction->currency_code,
            'ip_address' => is_array($transaction->metadata) ? ($transaction->metadata['ip_address'] ?? null) : null,
            'created_at' => $transaction->created_at,
            'user' => $transaction->user ? [
                'id' => $transaction->user->id,
                'name' => $transaction->user->name,
                'email' => $transaction->user->email,
                'slug' => $transaction->user->slug,
            ] : null,
        ]);

        return ApiResponse::success($paginator);
    }

    public function export(Request $request, AdminTransactionExportService $export): StreamedResponse
    {
        $format = $request->string('format')->toString();
        if ($format !== '' && ! in_array($format, ['csv', 'xml', 'excel', 'xlsx'], true)) {
            abort(422, 'Invalid export format.');
        }

        return $export->export($request);
    }

    public function destroy(PaymentTransaction $transaction): JsonResponse
    {
        $transaction->delete();

        return ApiResponse::success(null, message: 'Transaction deleted.');
    }
}
