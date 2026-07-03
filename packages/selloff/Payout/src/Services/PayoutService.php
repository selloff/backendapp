<?php

namespace App\Modules\Selloff\Payout\Services;

use App\Models\User;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    public function __construct(
        private readonly VendorEarningService $earnings,
    ) {}

    public function requestPayout(User $seller, float $amount, ?array $payoutInfo = null): PayoutRequest
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Payout amount must be greater than zero.'],
            ]);
        }

        $available = $this->earnings->availableBalance($seller);

        if ($amount > $available) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient available earnings balance.'],
            ]);
        }

        return PayoutRequest::query()->create([
            'seller_id' => $seller->id,
            'amount' => $amount,
            'currency_code' => 'NGN',
            'status' => 'pending',
            'payout_info' => $payoutInfo,
        ]);
    }

    public function createAdminPayout(User $seller, float $amount, string $method, string $status = 'pending'): PayoutRequest
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Payout amount must be greater than zero.'],
            ]);
        }

        if ($status !== 'completed' && $amount > $this->earnings->availableBalance($seller)) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient available earnings balance.'],
            ]);
        }

        return PayoutRequest::query()->create([
            'seller_id' => $seller->id,
            'amount' => $amount,
            'currency_code' => 'NGN',
            'status' => $status === 'completed' ? 'approved' : 'pending',
            'payout_info' => [
                'method' => $method,
                'payout_method' => $method,
            ],
        ]);
    }

    public function approve(PayoutRequest $request): PayoutRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Payout request is not pending.'],
            ]);
        }

        $request->update(['status' => 'approved']);

        return $request->fresh()->load('seller');
    }

    public function reject(PayoutRequest $request, ?string $reason = null): PayoutRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Payout request is not pending.'],
            ]);
        }

        $request->update([
            'status' => 'rejected',
            'payout_info' => array_merge($request->payout_info ?? [], ['reject_reason' => $reason]),
        ]);

        return $request->fresh()->load('seller');
    }
}
