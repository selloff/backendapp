<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminQuoteRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $quotes = QuoteRequest::query()
            ->with([
                'product.translations',
                'product.images',
                'buyer:id,first_name,last_name,email,slug,username',
                'seller:id,first_name,last_name,email,slug,username',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('message', 'like', $term)
                        ->orWhereHas('product.translations', fn ($translation) => $translation->where('title', 'like', $term))
                        ->orWhereHas('buyer', fn ($buyer) => $buyer->where('email', 'like', $term)->orWhere('username', 'like', $term))
                        ->orWhereHas('seller', fn ($seller) => $seller->where('email', 'like', $term)->orWhere('username', 'like', $term));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $quotes->getCollection()->transform(function (QuoteRequest $quote) {
            $product = $quote->product;
            $translation = $product?->translations->firstWhere('locale', 'en') ?? $product?->translations->first();
            $image = $product?->images->sortBy('sort_order')->first();
            $imageUrl = null;
            if ($image && $product) {
                $media = app(\App\Services\Media\MediaUploadService::class);
                $variantPaths = is_array($image->variant_paths) ? $image->variant_paths : null;
                $imageUrl = $media->urlForProductImageWithVariants($image->path, $image->disk, 'small', $variantPaths);
            }

            return [
                'id' => $quote->id,
                'status' => $quote->status,
                'message' => $quote->message,
                'quantity' => $quote->quantity,
                'quoted_price' => $quote->quoted_price,
                'currency_code' => $product?->currency_code,
                'created_at' => $quote->created_at,
                'updated_at' => $quote->updated_at,
                'product' => $product ? [
                    'id' => $product->id,
                    'title' => $translation?->title,
                    'slug' => $product->slug,
                    'currency_code' => $product->currency_code,
                    'image_url' => $imageUrl,
                ] : null,
                'buyer' => $quote->buyer ? [
                    'id' => $quote->buyer->id,
                    'first_name' => $quote->buyer->first_name,
                    'last_name' => $quote->buyer->last_name,
                    'email' => $quote->buyer->email,
                    'slug' => $quote->buyer->slug,
                    'username' => $quote->buyer->username ?? $quote->buyer->slug,
                ] : null,
                'seller' => $quote->seller ? [
                    'id' => $quote->seller->id,
                    'first_name' => $quote->seller->first_name,
                    'last_name' => $quote->seller->last_name,
                    'email' => $quote->seller->email,
                    'slug' => $quote->seller->slug,
                    'username' => $quote->seller->username ?? $quote->seller->slug,
                ] : null,
            ];
        });

        return ApiResponse::success($quotes);
    }

    public function destroy(QuoteRequest $quoteRequest): JsonResponse
    {
        $quoteRequest->delete();

        return ApiResponse::success(message: 'Deleted.');
    }
}
