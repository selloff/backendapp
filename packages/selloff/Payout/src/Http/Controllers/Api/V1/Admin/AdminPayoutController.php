<?php

namespace App\Modules\Selloff\Payout\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Selloff\Payout\Http\Resources\Api\V1\AdminPayoutResource;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Services\PayoutService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPayoutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $status = $request->input('status');
        if ($status === 'all' || $status === '') {
            $status = null;
        }

        $query = PayoutRequest::query()
            ->with(['seller.vendorProfile'])
            ->when($status === 'completed', fn (Builder $q) => $q->whereIn('status', ['approved', 'completed']))
            ->when($status && $status !== 'completed', fn (Builder $q) => $q->where('status', $status))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = trim((string) $request->input('q'));
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('seller_id', 'like', $term.'%')
                        ->orWhereHas('seller', function (Builder $seller) use ($term) {
                            $seller->where('email', 'like', '%'.$term.'%')
                                ->orWhere('username', 'like', '%'.$term.'%')
                                ->orWhere('slug', 'like', '%'.$term.'%')
                                ->orWhere('first_name', 'like', '%'.$term.'%')
                                ->orWhere('last_name', 'like', '%'.$term.'%');
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage);
        $paginator->through(fn (PayoutRequest $payout) => new AdminPayoutResource($payout));

        return ApiResponse::success($paginator);
    }

    public function store(Request $request, PayoutService $payouts): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payout_method' => ['required', 'string', 'in:paypal,bitcoin,iban,swift'],
            'status' => ['required', 'string', 'in:pending,completed'],
        ]);

        $seller = User::query()->findOrFail($data['user_id']);
        $payout = $payouts->createAdminPayout(
            $seller,
            (float) $data['amount'],
            $data['payout_method'],
            $data['status'],
        );

        return ApiResponse::success(new AdminPayoutResource($payout->load(['seller.vendorProfile'])), 201);
    }

    public function approve(PayoutRequest $payoutRequest, PayoutService $payouts): JsonResponse
    {
        $payout = $payouts->approve($payoutRequest);

        return ApiResponse::success([
            'id' => $payout->id,
            'status' => $payout->status,
            'seller_id' => $payout->seller_id,
        ]);
    }

    public function reject(Request $request, PayoutRequest $payoutRequest, PayoutService $payouts): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $payout = $payouts->reject($payoutRequest, $data['reason'] ?? null);

        return ApiResponse::success([
            'id' => $payout->id,
            'status' => $payout->status,
        ]);
    }

    public function destroy(PayoutRequest $payoutRequest): JsonResponse
    {
        $payoutRequest->delete();

        return ApiResponse::success(null, message: 'Payout deleted.');
    }
}
