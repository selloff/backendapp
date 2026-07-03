<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Affiliate\Models\AffiliateEarning;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Payment\Models\WalletTransaction;
use App\Modules\Selloff\User\Models\VendorProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('vendorProfile');

        $transactions = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => $tx->amount,
                'balance_after' => $tx->balance_after,
                'description' => $tx->description,
                'order_id' => $tx->order_id,
                'created_at' => $tx->created_at,
            ]);

        $expenses = $transactions->where('type', 'expense')->values();

        $deposits = WalletDeposit::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (WalletDeposit $deposit) => [
                'id' => $deposit->id,
                'amount' => $deposit->amount,
                'status' => $deposit->status,
                'payment_method' => $deposit->payment_method,
                'created_at' => $deposit->created_at,
            ]);

        $referralEarnings = AffiliateEarning::query()
            ->with(['product.translations', 'seller:id,first_name,last_name,email'])
            ->where('referrer_id', $user->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AffiliateEarning $earning) => [
                'id' => $earning->id,
                'product_id' => $earning->product_id,
                'product_title' => $earning->product?->translations->first()?->title,
                'product_slug' => $earning->product?->slug,
                'seller_name' => $earning->seller?->name,
                'commission_rate' => $earning->commission_rate,
                'earned_amount' => $earning->earned_amount,
                'currency_code' => $earning->currency_code,
                'created_at' => $earning->created_at,
            ]);

        return ApiResponse::success([
            'balance' => (float) $user->wallet_balance,
            'transactions' => $transactions->values(),
            'expenses' => $expenses,
            'deposits' => $deposits,
            'referral_earnings' => $referralEarnings,
            'payout_account' => $user->vendorProfile?->payout_info,
        ]);
    }

    public function updatePayoutAccount(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'swift_code' => ['nullable', 'string', 'max:50'],
        ]);

        $profile = VendorProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['payout_info' => $data],
        );

        return ApiResponse::success([
            'payout_account' => $profile->payout_info,
        ]);
    }
}
