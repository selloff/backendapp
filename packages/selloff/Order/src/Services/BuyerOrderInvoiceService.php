<?php

namespace App\Modules\Selloff\Order\Services;

use App\LegacyImport\Support\LegacyValueCoercer;
use App\Models\User;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payment\Models\PaymentTransaction;
use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use App\Services\Media\MediaUploadService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\MediaUrl;

class BuyerOrderInvoiceService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
        private readonly PaymentGatewaySettingsService $paymentSettings,
    ) {}

    /** @return array<string, mixed> */
    public function build(Order $order, User $viewer, ?string $type = null): array
    {
        $isAdmin = $viewer->can('admin_panel');

        if (! $isAdmin
            && (int) $order->buyer_id !== (int) $viewer->id
            && ! ($viewer->can('vendor') && $order->items()->where('seller_id', $viewer->id)->exists())) {
            abort(403);
        }

        if (! $isAdmin && $order->payment_status !== 'payment_received') {
            abort(404, 'Invoice is available after payment is received.');
        }

        if ($order->status === 'cancelled') {
            abort(404, 'Invoice is not available for cancelled orders.');
        }

        $order->loadMissing(['items.product.images', 'items.seller', 'buyer', 'paymentTransaction']);

        $platform = $this->platformSettings->all();
        $gateway = $this->paymentSettings->all();
        $snapshot = is_array($order->shipping_snapshot) ? $order->shipping_snapshot : [];
        $client = $this->clientBlock($order, $snapshot);
        $hasPhysical = $order->items->contains(fn ($item) => $item->product_type === 'physical');
        $transaction = $order->paymentTransaction;
        $paidSecondary = $transaction
            && $transaction->currency_code
            && $order->currency_code
            && $transaction->currency_code !== $order->currency_code
            ? [
                'amount' => $transaction->amount,
                'currency_code' => $transaction->currency_code,
            ]
            : null;

        return [
            'type' => 'order',
            'view_type' => $type,
            'invoice_number' => (string) $order->order_number,
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'issued_at' => $order->created_at,
            'company' => [
                'logo_url' => $this->logoUrl($platform),
                'address' => (string) ($platform['contact_address'] ?? ''),
                'email' => (string) ($platform['contact_email'] ?? ''),
                'phone' => (string) ($platform['contact_phone'] ?? ''),
                'additional_info' => (string) ($platform['contact_text'] ?? ''),
            ],
            'client' => $client,
            'payment' => [
                'status' => $order->payment_status,
                'method' => $order->payment_method,
                'currency_code' => $order->currency_code,
            ],
            'vat_enabled' => (bool) ($gateway['vat_status'] ?? false),
            'items' => $order->items->map(function ($item) {
                $imageUrl = null;
                if ($item->relationLoaded('product') && $item->product) {
                    $image = $item->product->relationLoaded('images')
                        ? ($item->product->images->firstWhere('is_primary', true) ?? $item->product->images->first())
                        : null;
                    if ($image) {
                        $imageUrl = app(MediaUploadService::class)->urlForProductImage($image->path, $image->disk, 'small');
                    }
                }

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_title' => $item->product_title,
                    'product_sku' => $item->product_sku,
                    'product_type' => $item->product_type,
                    'product_image_url' => $imageUrl,
                    'seller_username' => $item->seller?->username ?? $item->seller?->slug,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'product_vat' => $item->product_vat,
                    'product_vat_rate' => $item->product_vat_rate,
                    'total_price' => $item->total_price,
                    'product_options_summary' => $item->product_options_summary,
                ];
            })->values()->all(),
            'totals' => [
                'subtotal' => $order->price_subtotal,
                'referral_discount' => data_get($order->affiliate_data, 'discount'),
                'referral_discount_rate' => data_get($order->affiliate_data, 'discountRate'),
                'vat' => $order->price_vat,
                'shipping' => $hasPhysical ? $order->price_shipping : null,
                'coupon_discount' => $order->coupon_discount,
                'coupon_code' => $order->coupon_code,
                'global_taxes' => $this->globalTaxRows($order->global_taxes_data),
                'transaction_fee' => $order->transaction_fee,
                'transaction_fee_rate' => $order->transaction_fee_rate,
                'total' => $order->price_total,
                'paid_secondary' => $paidSecondary,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function clientBlock(Order $order, array $snapshot): array
    {
        $buyer = $order->buyer;
        $username = $buyer?->username ?? $buyer?->slug ?? ($order->buyer_id ? null : 'guest');

        return [
            'username' => $username ?? 'guest',
            'first_name' => $snapshot['bFirstName'] ?? $buyer?->first_name ?? $buyer?->name,
            'last_name' => $snapshot['bLastName'] ?? $buyer?->last_name,
            'email' => $snapshot['bEmail'] ?? $buyer?->email ?? $order->guest_email,
            'phone_number' => $snapshot['bPhoneNumber'] ?? $buyer?->phone_number,
            'tax_number' => $buyer?->tax_registration_number ?? null,
            'address' => $snapshot['bAddress'] ?? $buyer?->address ?? null,
            'state' => $snapshot['bState'] ?? null,
            'city' => $snapshot['bCity'] ?? null,
            'country' => $snapshot['bCountry'] ?? null,
        ];
    }

    /**
     * @return list<array{name: string, rate: mixed, total: mixed}>
     */
    private function globalTaxRows(mixed $globalTaxesData): array
    {
        if (! is_array($globalTaxesData) || $globalTaxesData === []) {
            return [];
        }

        $rows = [];
        foreach ($globalTaxesData as $taxItem) {
            if (! is_array($taxItem)) {
                continue;
            }

            $rows[] = [
                'name' => LegacyValueCoercer::localizedLabel($taxItem['taxNameArray'] ?? null, 'Tax'),
                'rate' => $taxItem['taxRate'] ?? null,
                'total' => $taxItem['taxTotal'] ?? 0,
            ];
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $platform */
    private function logoUrl(array $platform): ?string
    {
        $logo = $platform['site_logo_url'] ?? $platform['site_logo_email_url'] ?? null;
        if (! is_string($logo) || $logo === '') {
            return null;
        }

        return MediaUrl::resolve($logo);
    }
}
