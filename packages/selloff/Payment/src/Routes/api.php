<?php

use App\Modules\Selloff\Payment\Http\Controllers\Api\V1\PaymentController;
use App\Modules\Selloff\Payment\Http\Controllers\Api\V1\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/payments/methods', [PaymentController::class, 'methods']);
Route::get('/membership-plans', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\MembershipController::class, 'index']);

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/wallet', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\WalletController::class, 'show']);
    Route::put('/wallet/payout-account', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\WalletController::class, 'updatePayoutAccount']);
    Route::post('/wallet/deposits', [PaymentController::class, 'storeWalletDeposit']);
    Route::post('/wallet/deposits/{walletDeposit}/paystack/complete', [PaymentController::class, 'completeWalletDepositPaystack']);
    Route::post('/wallet/deposits/{walletDeposit}/complete', [PaymentController::class, 'completeWalletDeposit']);
    Route::post('/payments/bank-transfers/{bankTransferRequest}/approve', [PaymentController::class, 'approveBankTransfer']);

    Route::get('/account/membership', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\MembershipController::class, 'myPlan']);
    Route::get('/membership-plans/{membershipPlan}/quote', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\MembershipController::class, 'quote']);
    Route::post('/membership-plans/{membershipPlan}/subscribe', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\MembershipController::class, 'subscribe']);
    Route::post('/membership-plans/{membershipPlan}/purchase', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\MembershipController::class, 'purchase']);
    Route::get('/service-payments/completion', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\ServicePaymentCompletionController::class, 'show']);
});

Route::prefix('vendor')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/membership/status', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\MembershipController::class, 'status']);
    Route::get('/membership/entitlements', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\MembershipController::class, 'entitlements']);
    Route::get('/membership/top-credits', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\VendorMembershipTopController::class, 'topCredits']);
    Route::post('/products/{product}/apply-top-boost', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\VendorMembershipTopController::class, 'apply']);
    Route::get('/membership/transactions/{membershipTransaction}/invoice', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\VendorMembershipTransactionController::class, 'invoice']);
    Route::post('/membership/transactions/{membershipTransaction}/paystack/complete', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\VendorMembershipTransactionController::class, 'completePaystack']);
    Route::post('/membership/transactions/{membershipTransaction}/resume-payment', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\VendorMembershipTransactionController::class, 'resumePayment']);
    Route::get('/membership/transactions', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\VendorMembershipTransactionController::class, 'index']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:membership'])->group(function (): void {
    Route::get('/membership-plans', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminMembershipController::class, 'index']);
    Route::post('/membership-plans', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminMembershipController::class, 'store']);
    Route::put('/membership-plans/{membershipPlan}', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminMembershipController::class, 'update']);
    Route::delete('/membership-plans/{membershipPlan}', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminMembershipController::class, 'destroy']);
    Route::get('/membership-term-discounts', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminMembershipTermDiscountController::class, 'index']);
    Route::put('/membership-term-discounts', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminMembershipTermDiscountController::class, 'update']);
    Route::get('/membership/transactions', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminMembershipTransactionController::class, 'index']);
    Route::get('/membership/transactions/{membershipTransaction}/invoice', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminMembershipTransactionController::class, 'invoice']);
    Route::post('/membership/transactions/{membershipTransaction}/approve', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminMembershipTransactionController::class, 'approve']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:payments'])->group(function (): void {
    Route::get('/transactions/export', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminTransactionController::class, 'export']);
    Route::get('/transactions', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminTransactionController::class, 'index']);
    Route::delete('/transactions/{transaction}', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminTransactionController::class, 'destroy']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:payment_settings'])->group(function (): void {
    Route::get('/payments/settings', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminPaymentController::class, 'settings']);
    Route::put('/payments/gateways', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminPaymentController::class, 'updateGateways']);
    Route::put('/payments/gateways/legacy', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminPaymentController::class, 'updateLegacyGateway']);
    Route::get('/tax-rules', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminTaxRuleController::class, 'index']);
    Route::post('/tax-rules', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminTaxRuleController::class, 'store']);
    Route::put('/tax-rules/{taxRule}', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminTaxRuleController::class, 'update']);
    Route::delete('/tax-rules/{taxRule}', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminTaxRuleController::class, 'destroy']);
    Route::get('/payments/wallet-deposits', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminPaymentController::class, 'walletDeposits']);
    Route::post('/payments/wallet-deposits/{walletDeposit}/approve', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminPaymentController::class, 'approveWalletDeposit']);
    Route::get('/payments/bank-transfers', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminPaymentController::class, 'bankTransfers']);
    Route::delete('/payments/bank-transfers/{bankTransferRequest}', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminPaymentController::class, 'destroyBankTransfer']);
    Route::get('/payments/wallet-deposits/{walletDeposit}/invoice', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminPaymentController::class, 'walletDepositInvoice']);
    Route::post('/payments/bank-transfers/{bankTransferRequest}/decline', [\App\Modules\Selloff\Payment\Http\Controllers\Api\V1\Admin\AdminPaymentController::class, 'declineBankTransfer']);
});
