<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Payment\Gateways\PaystackGateway;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Payment\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletDepositService
{
    public function __construct(
        private readonly PaystackGateway $paystack,
        private readonly PaymentGatewaySettingsService $gatewaySettings,
    ) {}

    public function createDeposit(User $user, float $amount, string $paymentMethod): WalletDeposit
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than zero.'],
            ]);
        }

        $minDeposit = (float) ($this->gatewaySettings->all()['wallet_min_deposit'] ?? 0);
        if ($minDeposit > 0 && $amount < $minDeposit) {
            throw ValidationException::withMessages([
                'amount' => ['Minimum deposit amount is '.number_format($minDeposit, 2).'.'],
            ]);
        }

        if (! in_array($paymentMethod, ['bank_transfer', 'stripe', 'demo', 'paystack'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Unsupported wallet deposit method.'],
            ]);
        }

        if ($paymentMethod === 'paystack' && ! $this->paystack->isEnabled()) {
            throw ValidationException::withMessages([
                'payment_method' => ['Paystack is not enabled.'],
            ]);
        }

        $transactionId = match ($paymentMethod) {
            'paystack' => 'WAL-'.Str::upper(Str::random(12)),
            default => strtoupper(substr($paymentMethod, 0, 3)).now()->format('ymdHis').random_int(1000, 9999),
        };

        $deposit = WalletDeposit::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency_code' => 'NGN',
            'payment_method' => $paymentMethod,
            'status' => $paymentMethod === 'demo' ? 'completed' : 'pending',
            'transaction_id' => $transactionId,
        ]);

        if ($paymentMethod === 'demo') {
            $this->creditWallet($user, $deposit);
        }

        return $deposit->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function paystackCheckout(WalletDeposit $deposit, User $user): array
    {
        abort_unless($deposit->payment_method === 'paystack', 422, 'Deposit is not a Paystack payment.');
        abort_unless($deposit->status === 'pending', 422, 'Deposit is not pending.');

        $config = $this->paystack->enabledConfig();
        if ($config === null) {
            throw ValidationException::withMessages([
                'payment_method' => ['Paystack is not enabled.'],
            ]);
        }

        return [
            'type' => 'paystack_inline',
            'public_key' => $config['public_key'],
            'email' => $user->email,
            'amount_kobo' => (int) round(((float) $deposit->amount) * 100),
            'reference' => (string) $deposit->transaction_id,
            'currency' => strtoupper((string) ($deposit->currency_code ?? 'NGN')),
        ];
    }

    public function completePaystackDeposit(User $user, WalletDeposit $deposit, string $paymentReference): WalletDeposit
    {
        abort_unless((int) $deposit->user_id === (int) $user->id, 403);
        abort_unless($deposit->status === 'pending', 422, 'Deposit is not pending.');
        abort_unless($deposit->payment_method === 'paystack', 422, 'Deposit is not a Paystack payment.');

        $verified = $this->paystack->verify($paymentReference);
        $expectedKobo = (int) round(((float) $deposit->amount) * 100);
        $paidKobo = (int) ($verified->amount ?? 0);
        $currency = strtoupper((string) ($verified->currency ?? ''));

        if ($paidKobo !== $expectedKobo || $currency !== strtoupper((string) ($deposit->currency_code ?? 'NGN'))) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Paystack payment amount does not match deposit total.'],
            ]);
        }

        return DB::transaction(function () use ($user, $deposit, $paymentReference) {
            $deposit->update([
                'transaction_id' => $paymentReference,
            ]);

            $this->creditWallet($user->fresh() ?? $user, $deposit);

            return $deposit->fresh();
        });
    }

    public function completeDeposit(WalletDeposit $deposit): WalletDeposit
    {
        if ($deposit->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Deposit is not pending.'],
            ]);
        }

        return DB::transaction(function () use ($deposit) {
            $user = $deposit->user;
            $this->creditWallet($user, $deposit);

            return $deposit->fresh();
        });
    }

    private function creditWallet(User $user, WalletDeposit $deposit): void
    {
        $newBalance = round((float) $user->wallet_balance + (float) $deposit->amount, 2);
        $user->update(['wallet_balance' => $newBalance]);

        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $deposit->amount,
            'balance_after' => $newBalance,
            'description' => 'Wallet deposit #'.$deposit->id,
        ]);

        $deposit->update(['status' => 'completed']);
    }
}
