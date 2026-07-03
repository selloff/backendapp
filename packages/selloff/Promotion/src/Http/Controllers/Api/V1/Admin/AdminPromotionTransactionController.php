<?php

namespace App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Modules\Selloff\Promotion\Services\FeaturedPromotionService;
use App\Support\ApiResponse;
use App\Support\ServicePaymentQuery;
use App\Support\ServiceInvoiceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPromotionTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $paymentStatus = $request->input('payment_status', $request->input('status'));
        $statusFilter = match ($paymentStatus) {
            'pending_payment', 'pending' => 'pending',
            'payment_received', 'completed' => 'completed',
            default => null,
        };

        $transactions = PromotionTransaction::query()
            ->with(['user:id,first_name,last_name,email,slug,username', 'product:id,title,slug,promote_plan'])
            ->when($statusFilter === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($statusFilter === 'completed', fn ($q) => ServicePaymentQuery::wherePaid($q))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->input('q'));
                $q->where(function ($inner) use ($term) {
                    $inner->where('id', 'like', '%'.$term.'%')
                        ->orWhere('legacy_id', 'like', '%'.$term.'%')
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('email', 'like', '%'.$term.'%')
                            ->orWhere('username', 'like', '%'.$term.'%')
                            ->orWhere('slug', 'like', '%'.$term.'%'))
                        ->orWhereHas('product', fn ($product) => $product
                            ->where('slug', 'like', '%'.$term.'%')
                            ->orWhere('id', 'like', '%'.$term.'%'));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $transactions->getCollection()->transform(fn (PromotionTransaction $tx) => [
            'id' => $tx->id,
            'payment_id' => $tx->legacy_id ? (string) $tx->legacy_id : (string) $tx->id,
            'amount' => $tx->amount,
            'currency_code' => $tx->currency_code,
            'payment_method' => 'wallet_balance',
            'status' => $tx->status,
            'ip_address' => null,
            'purchased_plan' => $tx->product?->promote_plan,
            'user' => $tx->user ? [
                'id' => $tx->user->id,
                'first_name' => $tx->user->first_name,
                'last_name' => $tx->user->last_name,
                'name' => trim(($tx->user->first_name ?? '').' '.($tx->user->last_name ?? '')),
                'email' => $tx->user->email,
                'slug' => $tx->user->slug,
                'username' => $tx->user->username ?? $tx->user->slug,
            ] : null,
            'product' => $tx->product ? [
                'id' => $tx->product->id,
                'title' => $tx->product->title,
                'slug' => $tx->product->slug,
            ] : null,
            'created_at' => $tx->created_at,
        ]);

        return ApiResponse::success($transactions);
    }

    public function approve(PromotionTransaction $promotionTransaction, FeaturedPromotionService $promotions): JsonResponse
    {
        $transaction = $promotions->approvePending($promotionTransaction);

        return ApiResponse::success([
            'id' => $transaction->id,
            'status' => $transaction->status,
        ]);
    }

    public function invoice(Request $request, PromotionTransaction $promotionTransaction, ServiceInvoiceBuilder $invoices): JsonResponse
    {
        $invoices->authorizePromotion($promotionTransaction, $request->user());

        return ApiResponse::success($invoices->promotion($promotionTransaction));
    }
}
