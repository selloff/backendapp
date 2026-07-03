<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Services\MembershipPurchaseService;
use App\Support\ApiResponse;
use App\Support\ServicePaymentQuery;
use App\Support\ServiceInvoiceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMembershipTransactionController extends Controller
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

        $transactions = MembershipTransaction::query()
            ->with(['membershipPlan:id,title,currency_code', 'user:id,first_name,last_name,email,slug,username'])
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
                            ->orWhere('slug', 'like', '%'.$term.'%'));
                });
            })
            ->latest('id')
            ->paginate($perPage);

        $transactions->getCollection()->transform(fn (MembershipTransaction $tx) => [
            'id' => $tx->id,
            'payment_id' => $tx->legacy_id ? (string) $tx->legacy_id : (string) $tx->id,
            'amount' => $tx->amount,
            'currency_code' => $tx->membershipPlan?->currency_code ?? 'NGN',
            'payment_method' => $tx->payment_method,
            'status' => $tx->status,
            'ip_address' => null,
            'user' => $tx->user ? [
                'id' => $tx->user->id,
                'first_name' => $tx->user->first_name,
                'last_name' => $tx->user->last_name,
                'name' => $tx->user->name,
                'email' => $tx->user->email,
                'slug' => $tx->user->slug,
                'username' => $tx->user->username ?? $tx->user->slug,
            ] : null,
            'plan' => $tx->membershipPlan ? [
                'id' => $tx->membershipPlan->id,
                'title' => $tx->membershipPlan->title,
            ] : null,
            'created_at' => $tx->created_at,
        ]);

        return ApiResponse::success($transactions);
    }

    public function approve(MembershipTransaction $membershipTransaction, MembershipPurchaseService $service): JsonResponse
    {
        $transaction = $service->approvePending($membershipTransaction);

        return ApiResponse::success([
            'id' => $transaction->id,
            'status' => $transaction->status,
        ]);
    }

    public function invoice(Request $request, MembershipTransaction $membershipTransaction, ServiceInvoiceBuilder $invoices): JsonResponse
    {
        $invoices->authorizeMembership($membershipTransaction, $request->user());

        return ApiResponse::success($invoices->membership($membershipTransaction));
    }
}
