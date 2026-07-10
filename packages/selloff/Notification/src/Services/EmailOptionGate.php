<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Services\Platform\PlatformSettingsService;

class EmailOptionGate
{
    /** @var array<string, string|null> */
    private const TOGGLE_MAP = [
        'activation' => 'email_verification',
        'email_verification' => 'email_verification',
        'welcome' => 'email_option_welcome',
        'reset_password' => 'email_option_reset_password',
        'product_approved' => 'email_option_product_moderation',
        'product_rejected' => 'email_option_product_moderation',
        'new_product' => 'email_option_new_product',
        'new_order' => 'email_option_new_order',
        'new_order_seller' => 'email_option_new_order',
        'order_confirmation' => 'email_option_new_order',
        'order_shipped' => 'email_option_order_shipped',
        'quote_request' => 'email_option_bidding_system',
        'quote_submitted' => 'email_option_bidding_system',
        'quote_accepted' => 'email_option_bidding_system',
        'quote_rejected' => 'email_option_bidding_system',
        'refund_submitted' => 'email_option_refund',
        'refund_approved' => 'email_option_refund',
        'refund_rejected' => 'email_option_refund',
        'refund_message' => 'email_option_refund',
        'support_ticket' => 'email_option_support_system',
        'support_reply' => 'email_option_support_system',
        'contact_message' => 'email_option_contact_messages',
        'shop_opening_submitted' => 'email_option_shop_opening_request',
        'shop_opening_approved' => 'email_option_shop_opening_request',
        'shop_opening_rejected' => 'email_option_shop_opening_request',
        'shop_opening_admin_alert' => 'email_option_shop_opening_request',
        'new_message' => 'email_option_new_message',
        'vendor_feedback_received' => 'email_option_vendor_feedback',
        'vendor_feedback_approved' => 'email_option_vendor_feedback',
        'membership_subscribed' => 'email_option_membership_purchase',
        'membership_expiring' => 'email_option_membership_expiry',
        'promotion_applied' => 'email_option_promotion_applied',
        'vip_boost_applied' => 'email_option_promotion_applied',
        'escrow' => 'email_option_escrow',
        'test' => null,
    ];

    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function isEnabled(string $emailType): bool
    {
        $toggleKey = $this->toggleKeyFor($emailType);

        if ($toggleKey === null) {
            return true;
        }

        $all = $this->settings->all();
        $defaults = config('selloff.platform_settings', []);

        return (bool) ($all[$toggleKey] ?? $defaults[$toggleKey] ?? true);
    }

    public function toggleKeyFor(string $emailType): ?string
    {
        return self::TOGGLE_MAP[$emailType] ?? null;
    }
}
