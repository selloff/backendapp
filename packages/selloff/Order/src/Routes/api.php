<?php

use App\Modules\Selloff\Order\Http\Controllers\Api\V1\AccountDigitalDownloadController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\AccountDownloadController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\Admin\AdminDigitalSaleController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\Admin\AdminQuoteRequestController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\Admin\AdminRefundController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\CheckoutController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\GuestCheckoutController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\OrderController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\OrderInvoiceController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\QuoteRequestController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\RefundRequestController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\VendorOrderController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\VendorQuoteRequestController;
use App\Modules\Selloff\Order\Http\Controllers\Api\V1\VendorRefundController;
use Illuminate\Support\Facades\Route;

Route::post('/checkout/guest', [GuestCheckoutController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/invoices/orders/{orderNumber}', [OrderInvoiceController::class, 'showByOrderNumber']);

    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/checkout/wallet', [CheckoutController::class, 'completeWallet']);
    Route::post('/checkout/paystack/initiate', [CheckoutController::class, 'initiatePaystack']);
    Route::post('/checkout/paystack/complete', [CheckoutController::class, 'completePaystack']);
    Route::post('/checkout/bank-transfer', [CheckoutController::class, 'submitBankTransfer']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/gtm-events', [OrderController::class, 'gtmEvents']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice']);

    Route::get('/refund-requests', [RefundRequestController::class, 'index']);
    Route::post('/orders/{order}/refund-requests', [RefundRequestController::class, 'store']);

    Route::get('/quote-requests', [QuoteRequestController::class, 'index']);
    Route::post('/quote-requests', [QuoteRequestController::class, 'store']);
    Route::patch('/quote-requests/{quoteRequest}', [QuoteRequestController::class, 'update']);

    Route::get('/account/downloads', [AccountDownloadController::class, 'index']);
    Route::post('/account/downloads/{digitalSale}/file', [AccountDigitalDownloadController::class, 'download']);
});

Route::prefix('vendor')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/orders', [VendorOrderController::class, 'index']);
    Route::get('/orders/{order}', [VendorOrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [VendorOrderController::class, 'updateStatus']);
    Route::get('/refunds', [VendorRefundController::class, 'index']);
    Route::post('/refunds/{refundRequest}/approve', [VendorRefundController::class, 'approve']);
    Route::post('/refunds/{refundRequest}/reject', [VendorRefundController::class, 'reject']);
    Route::get('/quote-requests', [VendorQuoteRequestController::class, 'index']);
    Route::patch('/quote-requests/{quoteRequest}', [VendorQuoteRequestController::class, 'update']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:orders'])->group(function (): void {
    Route::get('/orders/export', [AdminOrderController::class, 'export']);
    Route::get('/orders/{order}/invoice', [AdminOrderController::class, 'invoice']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::post('/orders/{order}/mark-paid', [AdminOrderController::class, 'markPaid']);
    Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);
    Route::patch('/orders/{order}/items/{item}', [AdminOrderController::class, 'updateItemStatus']);
    Route::post('/orders/{order}/items/{item}/approve-guest', [AdminOrderController::class, 'approveGuestItem']);
    Route::delete('/orders/{order}/items/{item}', [AdminOrderController::class, 'destroyItem']);
    Route::post('/orders/{order}/refunds', [AdminOrderController::class, 'storeRefund']);

    Route::get('/quote-requests', [AdminQuoteRequestController::class, 'index']);
    Route::delete('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'destroy']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:refund_requests'])->group(function (): void {
    Route::get('/refunds', [AdminRefundController::class, 'index']);
    Route::get('/refunds/{refundRequest}', [AdminRefundController::class, 'show']);
    Route::post('/refunds/{refundRequest}/approve', [AdminRefundController::class, 'approve']);
    Route::post('/refunds/{refundRequest}/reject', [AdminRefundController::class, 'reject']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:digital_sales'])->group(function (): void {
    Route::get('/digital-sales/export', [AdminDigitalSaleController::class, 'export']);
    Route::get('/digital-sales', [AdminDigitalSaleController::class, 'index']);
    Route::delete('/digital-sales/{digitalSale}', [AdminDigitalSaleController::class, 'destroy']);
});
