<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSandboxController extends Controller
{
    /**
     * Legacy one-off migration utility — bulk reassign products from old_category to new_category.
     * Restricted to admin users (legacy exposed this publicly; rewrite requires admin_panel).
     */
    public function updateProductCategory(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin_panel'), 403);

        $data = $request->validate([
            'old_category' => ['required', 'integer', 'exists:categories,id'],
            'new_category' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $count = Product::query()
            ->where('category_id', $data['old_category'])
            ->update(['category_id' => $data['new_category']]);

        return MobileResponse::success(
            ['updated_count' => $count],
            200,
            $count.' products successfully updated.',
        );
    }
}
