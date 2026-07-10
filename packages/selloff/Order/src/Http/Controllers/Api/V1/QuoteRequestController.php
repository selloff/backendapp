<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Services\QuoteEmailService;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $quotes = QuoteRequest::query()
            ->with(['product.translations', 'seller'])
            ->where('buyer_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($quotes);
    }

    public function store(Request $request, QuoteEmailService $emails): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);

        $quote = QuoteRequest::query()->create([
            'product_id' => $product->id,
            'buyer_id' => $request->user()->id,
            'seller_id' => $product->vendor_id,
            'quantity' => $data['quantity'] ?? 1,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        $fresh = $quote->load(['product.translations', 'seller']);
        $emails->queueRequest($fresh);

        return ApiResponse::success($fresh, 201);
    }

    public function update(Request $request, QuoteRequest $quoteRequest, QuoteEmailService $emails): JsonResponse
    {
        abort_unless((int) $quoteRequest->buyer_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
        ]);

        abort_unless($quoteRequest->status === 'quoted', 422, 'Only quoted requests can be accepted or rejected.');

        $quoteRequest->update(['status' => $data['status']]);

        $fresh = $quoteRequest->fresh()->load(['product.translations', 'seller']);

        if ($data['status'] === 'accepted') {
            $emails->queueAccepted($fresh);
        } elseif ($data['status'] === 'rejected') {
            $emails->queueRejected($fresh);
        }

        return ApiResponse::success($fresh);
    }
}
