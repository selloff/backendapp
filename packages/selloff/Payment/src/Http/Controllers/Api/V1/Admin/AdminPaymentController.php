<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payment\Models\BankTransferRequest;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use App\Modules\Selloff\Payment\Services\PaymentMethodRegistry;
use App\Modules\Selloff\Payment\Services\WalletDepositService;
use App\Support\ApiResponse;
use App\Support\ServiceInvoiceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function settings(PaymentMethodRegistry $registry, PaymentGatewaySettingsService $gatewaySettings): JsonResponse
    {
        return ApiResponse::success([
            'payment_methods' => $registry->available(),
            'gateway_settings' => $gatewaySettings->all(),
            'membership_plans' => MembershipPlan::query()->orderBy('title')->get(),
        ]);
    }

    public function updateGateways(Request $request, PaymentGatewaySettingsService $gatewaySettings, PaymentMethodRegistry $registry): JsonResponse
    {
        $data = $request->validate([
            'wallet_enabled' => ['sometimes', 'boolean'],
            'wallet_deposit_enabled' => ['sometimes', 'boolean'],
            'wallet_min_deposit' => ['sometimes', 'numeric', 'min:0'],
            'bank_transfer_enabled' => ['sometimes', 'boolean'],
            'bank_transfer_instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'cash_on_delivery_enabled' => ['sometimes', 'boolean'],
            'cash_on_delivery_debt_limit' => ['sometimes', 'numeric', 'min:0'],
            'stripe_enabled' => ['sometimes', 'boolean'],
            'stripe_public_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'vat_status' => ['sometimes', 'boolean'],
            'cart_location_selection' => ['sometimes', 'boolean'],
            'additional_invoice_info' => ['sometimes', 'nullable', 'array'],
            'additional_invoice_info.*' => ['nullable', 'string', 'max:5000'],
        ]);

        $gatewaySettings->update($data);

        return ApiResponse::success([
            'payment_methods' => $registry->available(),
            'gateway_settings' => $gatewaySettings->all(),
        ]);
    }

    public function updateLegacyGateway(Request $request, PaymentGatewaySettingsService $gatewaySettings, PaymentMethodRegistry $registry): JsonResponse
    {
        $data = $request->validate([
            'name_key' => ['required', 'string', 'max:50'],
            'status' => ['sometimes', 'boolean'],
            'environment' => ['sometimes', 'nullable', 'string', 'max:30'],
            'public_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'secret_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'webhook_secret' => ['sometimes', 'nullable', 'string', 'max:500'],
            'transaction_fee' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $nameKey = $data['name_key'];
        unset($data['name_key']);

        return ApiResponse::success([
            'payment_methods' => $registry->available(),
            'gateway_settings' => $gatewaySettings->updateLegacyGateway($nameKey, $data),
        ]);
    }

    public function walletDeposits(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $paymentStatus = $request->input('payment_status', $request->input('status'));
        $statusFilter = match ($paymentStatus) {
            'pending_payment', 'pending', 'awaiting_payment' => 'pending',
            'payment_received', 'completed' => 'completed',
            default => null,
        };

        $deposits = WalletDeposit::query()
            ->with('user:id,first_name,last_name,email,slug,username')
            ->when($statusFilter !== null, fn ($q) => $q->where('status', $statusFilter))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->input('q'));
                $q->where(function ($inner) use ($term) {
                    $inner->where('id', 'like', '%'.$term.'%')
                        ->orWhere('transaction_id', 'like', '%'.$term.'%')
                        ->orWhere('legacy_id', 'like', '%'.$term.'%')
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('email', 'like', '%'.$term.'%')
                            ->orWhere('username', 'like', '%'.$term.'%')
                            ->orWhere('slug', 'like', '%'.$term.'%'));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $deposits->getCollection()->transform(fn (WalletDeposit $deposit) => [
            'id' => $deposit->id,
            'payment_id' => $deposit->transaction_id ?? (string) $deposit->id,
            'amount' => $deposit->amount,
            'currency_code' => $deposit->currency_code ?? 'NGN',
            'payment_method' => $deposit->payment_method,
            'status' => $deposit->status,
            'ip_address' => null,
            'user' => $deposit->user ? [
                'id' => $deposit->user->id,
                'first_name' => $deposit->user->first_name,
                'last_name' => $deposit->user->last_name,
                'name' => $deposit->user->name,
                'email' => $deposit->user->email,
                'slug' => $deposit->user->slug,
                'username' => $deposit->user->username ?? $deposit->user->slug,
            ] : null,
            'created_at' => $deposit->created_at,
        ]);

        return ApiResponse::success($deposits);
    }

    public function bankTransfers(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $transfers = BankTransferRequest::query()
            ->with('user:id,first_name,last_name,email,slug,username')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->input('q'));
                $q->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', '%'.$term.'%')
                        ->orWhere('id', 'like', '%'.$term.'%')
                        ->orWhere('payment_note', 'like', '%'.$term.'%')
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('email', 'like', '%'.$term.'%')
                            ->orWhere('username', 'like', '%'.$term.'%')
                            ->orWhere('slug', 'like', '%'.$term.'%'));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $orderIds = Order::query()
            ->whereIn('order_number', $transfers->getCollection()->pluck('order_number')->filter())
            ->pluck('id', 'order_number');

        $transfers->getCollection()->transform(fn (BankTransferRequest $transfer) => [
            'id' => $transfer->id,
            'order_number' => $transfer->order_number,
            'order_id' => $transfer->order_number ? ($orderIds[$transfer->order_number] ?? null) : null,
            'report_type' => $transfer->order_number ? 'order' : 'wallet_deposit',
            'payment_note' => $transfer->payment_note,
            'receipt_path' => $transfer->receipt_path,
            'status' => $transfer->status,
            'ip_address' => $transfer->ip_address,
            'user' => $transfer->user ? [
                'id' => $transfer->user->id,
                'first_name' => $transfer->user->first_name,
                'last_name' => $transfer->user->last_name,
                'name' => $transfer->user->name,
                'email' => $transfer->user->email,
                'slug' => $transfer->user->slug,
                'username' => $transfer->user->username ?? $transfer->user->slug,
            ] : null,
            'created_at' => $transfer->created_at,
        ]);

        return ApiResponse::success($transfers);
    }

    public function declineBankTransfer(BankTransferRequest $bankTransferRequest): JsonResponse
    {
        abort_unless($bankTransferRequest->status === 'pending', 422, 'Transfer is not pending.');

        $bankTransferRequest->update(['status' => 'declined']);

        return ApiResponse::success([
            'id' => $bankTransferRequest->id,
            'status' => $bankTransferRequest->status,
        ]);
    }

    public function approveWalletDeposit(WalletDeposit $walletDeposit, WalletDepositService $service): JsonResponse
    {
        abort_unless($walletDeposit->status === 'pending', 422, 'Deposit is not pending.');

        $deposit = $service->completeDeposit($walletDeposit);

        return ApiResponse::success([
            'id' => $deposit->id,
            'status' => $deposit->status,
        ]);
    }

    public function walletDepositInvoice(Request $request, WalletDeposit $walletDeposit, ServiceInvoiceBuilder $invoices): JsonResponse
    {
        $invoices->authorizeWalletDeposit($walletDeposit, $request->user());

        return ApiResponse::success($invoices->walletDeposit($walletDeposit));
    }

    public function destroyBankTransfer(BankTransferRequest $bankTransferRequest): JsonResponse
    {
        $bankTransferRequest->delete();

        return ApiResponse::success(null, message: 'Bank transfer report deleted.');
    }
}
