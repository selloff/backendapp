<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\Wishlist;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileWishlistController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $existing = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return MobileResponse::success([
                'product_id' => $product->id,
                'is_in_wishlist' => false,
            ], 200, 'Removed from wishlist.');
        }

        $wishlist = Wishlist::query()->create([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return MobileResponse::success([
            'id' => $wishlist->id,
            'product_id' => $product->id,
            'is_in_wishlist' => true,
        ], 201, 'Added to wishlist.');
    }
}
