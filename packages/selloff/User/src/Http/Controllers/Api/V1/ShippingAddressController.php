<?php

namespace App\Modules\Selloff\User\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\User\Http\Resources\Api\V1\ShippingAddressResource;
use App\Modules\Selloff\User\Models\ShippingAddress;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = ShippingAddress::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success([
            'addresses' => ShippingAddressResource::collection($addresses),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if ($data['is_default'] ?? false) {
            ShippingAddress::query()
                ->where('user_id', $request->user()->id)
                ->update(['is_default' => false]);
        }

        $address = ShippingAddress::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success(new ShippingAddressResource($address), 201);
    }

    public function update(Request $request, ShippingAddress $shippingAddress): JsonResponse
    {
        abort_unless((int) $shippingAddress->user_id === (int) $request->user()->id, 403);

        $data = $this->validated($request);

        if ($data['is_default'] ?? false) {
            ShippingAddress::query()
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $shippingAddress->id)
                ->update(['is_default' => false]);
        }

        $shippingAddress->update($data);

        return ApiResponse::success(new ShippingAddressResource($shippingAddress->fresh()));
    }

    public function destroy(Request $request, ShippingAddress $shippingAddress): JsonResponse
    {
        abort_unless((int) $shippingAddress->user_id === (int) $request->user()->id, 403);

        $shippingAddress->delete();

        return ApiResponse::success(message: 'Address deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'address_2' => ['nullable', 'string', 'max:500'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer'],
            'state_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }
}
