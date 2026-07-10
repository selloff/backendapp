<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Notification\Services\QuoteEmailService;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorQuoteRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $quotes = QuoteRequest::query()
            ->with(['product.translations', 'buyer'])
            ->where('seller_id', $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($quotes);
    }

    public function update(Request $request, QuoteRequest $quoteRequest, QuoteEmailService $emails): JsonResponse
    {
        abort_unless((int) $quoteRequest->seller_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'quoted_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:pending,quoted,accepted,rejected,closed'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousStatus = $quoteRequest->status;
        $quoteRequest->update($data);

        $fresh = $quoteRequest->fresh()->load(['product.translations', 'buyer']);

        if (($data['status'] ?? null) === 'quoted' && $previousStatus !== 'quoted') {
            $emails->queueQuoted($fresh);
        }

        return ApiResponse::success($fresh);
    }
}
