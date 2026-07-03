<?php

namespace App\Modules\Selloff\Admin\Services;

use App\Http\Resources\Api\V1\UserAdminResource;
use App\Models\User;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductAdminResource;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Order\Http\Resources\Api\V1\OrderResource;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payment\Models\PaymentTransaction;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Modules\Selloff\Review\Http\Resources\Api\V1\ProductCommentResource;
use App\Modules\Selloff\Review\Http\Resources\Api\V1\ProductReviewResource;
use App\Modules\Selloff\Review\Models\ProductComment;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Modules\Selloff\User\Models\ReferralProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminDashboardService
{
    private const LIST_LIMIT = 15;

    private const MEMBERS_LIMIT = 6;

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $capabilities = [
            'orders' => $this->userCanAny($user, ['orders', 'admin_panel']),
            'products' => $this->userCanAny($user, ['products', 'admin_panel']),
            'membership' => $this->userCanAny($user, ['membership', 'admin_panel']),
            'reviews' => $this->userCanAny($user, ['reviews', 'admin_panel']),
            'comments' => $this->userCanAny($user, ['comments', 'admin_panel']),
        ];

        $now = Carbon::now();
        $startOfThisMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $counts = [
            'orders' => Order::query()->count(),
            'products' => Product::query()->adminItemsForSale()->count(),
            'pending_products' => Product::query()->adminPendingModeration()->count(),
            'members' => User::query()->count(),
            'vendors' => User::role('vendor')->count(),
            'logged_in_users_30_days' => User::query()
                ->where('last_seen_at', '>=', $now->copy()->subDays(30))
                ->count(),
            'signup_this_month' => User::query()
                ->where('created_at', '>=', $startOfThisMonth)
                ->count(),
            'signup_last_month' => User::query()
                ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
                ->count(),
            'escrows' => EscrowTransaction::query()->count(),
            'referrals' => ReferralProfile::query()->whereNotNull('referral_user_id')->count(),
        ];

        $payload = [
            'capabilities' => $capabilities,
            'counts' => $counts,
        ];

        if ($capabilities['orders']) {
            $payload['latest_orders'] = OrderResource::collection(
                Order::query()
                    ->with(['items', 'buyer'])
                    ->orderByDesc('id')
                    ->limit(self::LIST_LIMIT)
                    ->get()
            )->resolve();

            $payload['latest_transactions'] = PaymentTransaction::query()
                ->orderByDesc('id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (PaymentTransaction $tx) => [
                    'id' => $tx->id,
                    'order_id' => $tx->order_id,
                    'order_display' => $tx->order_id !== null ? '#'.($tx->order_id + 10000) : null,
                    'amount' => $tx->amount,
                    'currency_code' => $tx->currency_code,
                    'payment_method' => $tx->payment_method,
                    'payment_status' => $tx->payment_status,
                    'created_at' => $tx->created_at,
                ])
                ->values()
                ->all();
        }

        if ($capabilities['products']) {
            $payload['latest_products'] = ProductAdminResource::collection(
                Product::query()
                    ->with(['translations', 'vendor.vendorProfile', 'category.translations', 'brand', 'images'])
                    ->adminItemsForSale()
                    ->orderByDesc('id')
                    ->limit(self::LIST_LIMIT)
                    ->get()
            )->resolve();

            $payload['latest_pending_products'] = ProductAdminResource::collection(
                Product::query()
                    ->with(['translations', 'vendor.vendorProfile', 'category.translations', 'brand', 'images'])
                    ->adminPendingModeration()
                    ->orderByDesc('id')
                    ->limit(self::LIST_LIMIT)
                    ->get()
            )->resolve();

            $payload['latest_promoted_transactions'] = PromotionTransaction::query()
                ->with(['user:id,first_name,last_name,email', 'product:id,slug'])
                ->with(['product.translations'])
                ->orderByDesc('id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (PromotionTransaction $tx) => [
                    'id' => $tx->id,
                    'amount' => $tx->amount,
                    'currency_code' => $tx->currency_code,
                    'status' => $tx->status,
                    'payment_method' => 'promotion',
                    'created_at' => $tx->created_at,
                ])
                ->values()
                ->all();
        }

        if ($capabilities['reviews']) {
            $payload['latest_reviews'] = ProductReviewResource::collection(
                ProductReview::query()
                    ->with(['user', 'product.translations'])
                    ->orderByDesc('id')
                    ->limit(self::LIST_LIMIT)
                    ->get()
            )->resolve();
        }

        if ($capabilities['comments']) {
            $payload['latest_comments'] = ProductCommentResource::collection(
                ProductComment::query()
                    ->with(['user', 'product'])
                    ->orderByDesc('id')
                    ->limit(self::LIST_LIMIT)
                    ->get()
            )->resolve();
        }

        if ($capabilities['membership']) {
            $payload['latest_members'] = User::query()
                ->with('roles')
                ->orderByDesc('id')
                ->limit(self::MEMBERS_LIMIT)
                ->get()
                ->map(fn (User $member) => array_merge(
                    (new UserAdminResource($member))->resolve(),
                    ['created_at' => $member->created_at],
                ))
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userCanAny(User $user, array $permissions): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
