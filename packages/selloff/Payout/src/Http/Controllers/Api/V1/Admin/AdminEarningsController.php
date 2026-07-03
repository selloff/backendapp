<?php

namespace App\Modules\Selloff\Payout\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Modules\Selloff\Payout\Services\VendorEarningService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEarningsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $earnings = VendorEarning::query()
            ->with([
                'seller:id,first_name,last_name,email,slug,username',
                'order:id,order_number,price_total,price_shipping,payment_method,currency_code,affiliate_data',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim((string) $request->input('q'));
                $query->where(function ($inner) use ($term) {
                    $inner->whereHas('order', fn ($order) => $order->where('order_number', 'like', '%'.$term.'%'))
                        ->orWhere('id', 'like', '%'.$term.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $earnings->getCollection()->transform(function (VendorEarning $earning) {
            $items = OrderItem::query()
                ->where('order_id', $earning->order_id)
                ->where('seller_id', $earning->seller_id)
                ->get(['total_price', 'product_vat', 'product_vat_rate', 'commission_rate']);

            $saleAmount = (float) $items->sum('total_price');
            $vatAmount = (float) $items->sum('product_vat');
            $vatRate = $items->first()?->product_vat_rate;
            $commissionRate = $earning->commission_rate ?? $items->first()?->commission_rate;
            $commission = round(max($saleAmount - (float) $earning->earned_amount, 0), 2);
            $affiliate = is_array($earning->order?->affiliate_data) ? $earning->order->affiliate_data : [];

            return [
                'id' => $earning->id,
                'order_id' => $earning->order_id,
                'order_number' => $earning->order?->order_number,
                'sale_amount' => $saleAmount > 0 ? $saleAmount : (float) $earning->earned_amount,
                'vat_amount' => $vatAmount,
                'vat_rate' => $vatRate,
                'commission' => $commission,
                'commission_rate' => $commissionRate,
                'affiliate_commission' => isset($affiliate['commission']) ? (float) $affiliate['commission'] : null,
                'affiliate_commission_rate' => $affiliate['commission_rate'] ?? null,
                'affiliate_discount' => isset($affiliate['discount']) ? (float) $affiliate['discount'] : null,
                'affiliate_discount_rate' => $affiliate['discount_rate'] ?? null,
                'coupon_discount' => isset($affiliate['coupon_discount']) ? (float) $affiliate['coupon_discount'] : null,
                'coupon_code' => $affiliate['coupon_code'] ?? null,
                'shipping_cost' => (float) ($earning->order?->price_shipping ?? 0),
                'earned_amount' => (float) $earning->earned_amount,
                'currency_code' => $earning->currency_code ?? $earning->order?->currency_code ?? 'NGN',
                'payment_method' => $earning->order?->payment_method,
                'seller' => $earning->seller ? [
                    'id' => $earning->seller->id,
                    'first_name' => $earning->seller->first_name,
                    'last_name' => $earning->seller->last_name,
                    'name' => $earning->seller->name,
                    'email' => $earning->seller->email,
                    'slug' => $earning->seller->slug,
                    'username' => $earning->seller->username ?? $earning->seller->slug,
                ] : null,
                'created_at' => $earning->created_at,
            ];
        });

        return ApiResponse::success($earnings);
    }

    public function summary(Request $request): JsonResponse
    {
        $query = VendorEarning::query()
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')));

        $byVendor = (clone $query)
            ->select('seller_id', \Illuminate\Support\Facades\DB::raw('SUM(earned_amount) as total_earned'), \Illuminate\Support\Facades\DB::raw('COUNT(*) as order_count'))
            ->groupBy('seller_id')
            ->orderByDesc('total_earned')
            ->get()
            ->map(function ($row) {
                $seller = User::query()->find($row->seller_id);

                return [
                    'seller_id' => $row->seller_id,
                    'seller_name' => $seller?->name,
                    'seller_email' => $seller?->email,
                    'total_earned' => (float) $row->total_earned,
                    'order_count' => (int) $row->order_count,
                ];
            });

        return ApiResponse::success([
            'total_earned' => (float) (clone $query)->sum('earned_amount'),
            'order_count' => (int) (clone $query)->count(),
            'by_vendor' => $byVendor,
        ]);
    }

    public function sellerBalances(Request $request, VendorEarningService $earnings): JsonResponse
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $sellers = User::query()
            ->role('vendor')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('q')).'%';
                $query->where(function ($inner) use ($term, $request) {
                    $inner->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('slug', 'like', $term);

                    if (is_numeric($request->input('q'))) {
                        $inner->orWhere('id', (int) $request->input('q'));
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $sellers->getCollection()->transform(fn (User $seller) => [
            'seller_id' => $seller->id,
            'seller_name' => $seller->name,
            'seller_email' => $seller->email,
            'username' => $seller->username ?? $seller->slug,
            'slug' => $seller->slug,
            'avatar' => $seller->avatar,
            'number_of_sales' => $earnings->salesCount($seller),
            'balance' => $earnings->availableBalance($seller),
            'total_earned' => $earnings->totalEarned($seller),
            'reserved_for_payouts' => $earnings->reservedForPayouts($seller),
            'available_balance' => $earnings->availableBalance($seller),
        ]);

        return ApiResponse::success($sellers);
    }

    public function payoutSettings(PlatformSettingsService $settings): JsonResponse
    {
        $all = $settings->all();

        return ApiResponse::success([
            'min_payout_amount' => (float) ($all['min_payout_amount'] ?? 1000),
            'payout_paypal_enabled' => filter_var($all['payout_paypal_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'payout_bitcoin_enabled' => filter_var($all['payout_bitcoin_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'payout_iban_enabled' => filter_var($all['payout_iban_enabled'] ?? true, FILTER_VALIDATE_BOOL),
            'payout_swift_enabled' => filter_var($all['payout_swift_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'payout_bank_enabled' => filter_var($all['payout_bank_enabled'] ?? true, FILTER_VALIDATE_BOOL),
            'min_payout_paypal' => (float) ($all['min_payout_paypal'] ?? $all['min_payout_amount'] ?? 1000),
            'min_payout_bitcoin' => (float) ($all['min_payout_bitcoin'] ?? $all['min_payout_amount'] ?? 1000),
            'min_payout_iban' => (float) ($all['min_payout_iban'] ?? $all['min_payout_amount'] ?? 1000),
            'min_payout_swift' => (float) ($all['min_payout_swift'] ?? $all['min_payout_amount'] ?? 1000),
            'payout_description' => (string) ($all['payout_description'] ?? ''),
        ]);
    }

    public function updatePayoutSettings(Request $request, PlatformSettingsService $settings): JsonResponse
    {
        $data = $request->validate([
            'method' => ['nullable', 'in:paypal,bitcoin,iban,swift'],
            'enabled' => ['nullable', 'boolean'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'min_payout_amount' => ['nullable', 'numeric', 'min:0'],
            'payout_paypal_enabled' => ['nullable', 'boolean'],
            'payout_bitcoin_enabled' => ['nullable', 'boolean'],
            'payout_iban_enabled' => ['nullable', 'boolean'],
            'payout_swift_enabled' => ['nullable', 'boolean'],
            'payout_bank_enabled' => ['nullable', 'boolean'],
            'min_payout_paypal' => ['nullable', 'numeric', 'min:0'],
            'min_payout_bitcoin' => ['nullable', 'numeric', 'min:0'],
            'min_payout_iban' => ['nullable', 'numeric', 'min:0'],
            'min_payout_swift' => ['nullable', 'numeric', 'min:0'],
            'payout_description' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($data['method'])) {
            $method = $data['method'];
            $payload = [];
            if (array_key_exists('enabled', $data)) {
                $payload['payout_'.$method.'_enabled'] = $data['enabled'] ? '1' : '0';
            }
            if (array_key_exists('min_amount', $data)) {
                $payload['min_payout_'.$method] = (string) $data['min_amount'];
            }
            $settings->upsertMany($payload, 'payout');

            return $this->payoutSettings($settings);
        }

        $current = $settings->all();

        $settings->upsertMany([
            'min_payout_amount' => (string) ($data['min_payout_amount'] ?? $current['min_payout_amount'] ?? 1000),
            'payout_paypal_enabled' => isset($data['payout_paypal_enabled'])
                ? ($data['payout_paypal_enabled'] ? '1' : '0')
                : ($current['payout_paypal_enabled'] ?? '0'),
            'payout_bitcoin_enabled' => isset($data['payout_bitcoin_enabled'])
                ? ($data['payout_bitcoin_enabled'] ? '1' : '0')
                : ($current['payout_bitcoin_enabled'] ?? '0'),
            'payout_iban_enabled' => isset($data['payout_iban_enabled'])
                ? ($data['payout_iban_enabled'] ? '1' : '0')
                : ($current['payout_iban_enabled'] ?? '1'),
            'payout_swift_enabled' => isset($data['payout_swift_enabled'])
                ? ($data['payout_swift_enabled'] ? '1' : '0')
                : ($current['payout_swift_enabled'] ?? '0'),
            'payout_bank_enabled' => isset($data['payout_bank_enabled'])
                ? ($data['payout_bank_enabled'] ? '1' : '0')
                : ($current['payout_bank_enabled'] ?? '1'),
            'min_payout_paypal' => (string) ($data['min_payout_paypal'] ?? $current['min_payout_paypal'] ?? 1000),
            'min_payout_bitcoin' => (string) ($data['min_payout_bitcoin'] ?? $current['min_payout_bitcoin'] ?? 1000),
            'min_payout_iban' => (string) ($data['min_payout_iban'] ?? $current['min_payout_iban'] ?? 1000),
            'min_payout_swift' => (string) ($data['min_payout_swift'] ?? $current['min_payout_swift'] ?? 1000),
            'payout_description' => $data['payout_description'] ?? ($current['payout_description'] ?? ''),
        ], 'payout');

        return $this->payoutSettings($settings);
    }

    public function destroy(VendorEarning $vendorEarning): JsonResponse
    {
        $vendorEarning->delete();

        return ApiResponse::success(null, message: 'Earning deleted.');
    }

    public function updateSellerBalance(Request $request, User $seller, VendorEarningService $earnings): JsonResponse
    {
        abort_unless($seller->can('vendor'), 422, 'User is not a vendor.');

        $data = $request->validate([
            'balance' => ['required', 'numeric', 'min:0'],
        ]);

        $earnings->setAvailableBalance($seller, (float) $data['balance']);

        return ApiResponse::success([
            'seller_id' => $seller->id,
            'balance' => $earnings->availableBalance($seller),
            'available_balance' => $earnings->availableBalance($seller),
        ]);
    }
}
