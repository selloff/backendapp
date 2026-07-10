<?php

namespace App\Modules\Selloff\Notification\Support;

final class TransactionalEmailType
{
    public const ACTIVATION = 'activation';

    public const EMAIL_VERIFICATION = 'email_verification';

    public const WELCOME = 'welcome';

    public const RESET_PASSWORD = 'reset_password';

    public const ORDER_CONFIRMATION = 'order_confirmation';

    public const NEW_ORDER = 'new_order';

    public const NEW_ORDER_SELLER = 'new_order_seller';

    public const ORDER_SHIPPED = 'order_shipped';

    public const QUOTE_REQUEST = 'quote_request';

    public const QUOTE_SUBMITTED = 'quote_submitted';

    public const QUOTE_ACCEPTED = 'quote_accepted';

    public const QUOTE_REJECTED = 'quote_rejected';

    public const REFUND_SUBMITTED = 'refund_submitted';

    public const REFUND_APPROVED = 'refund_approved';

    public const REFUND_REJECTED = 'refund_rejected';

    public const REFUND_MESSAGE = 'refund_message';

    public const NEW_MESSAGE = 'new_message';

    public const PRODUCT_APPROVED = 'product_approved';

    public const PRODUCT_REJECTED = 'product_rejected';

    public const NEW_PRODUCT = 'new_product';

    public const SHOP_OPENING_SUBMITTED = 'shop_opening_submitted';

    public const SHOP_OPENING_APPROVED = 'shop_opening_approved';

    public const SHOP_OPENING_REJECTED = 'shop_opening_rejected';

    public const SHOP_OPENING_ADMIN_ALERT = 'shop_opening_admin_alert';

    public const MEMBERSHIP_SUBSCRIBED = 'membership_subscribed';

    public const PROMOTION_APPLIED = 'promotion_applied';

    public const VIP_BOOST_APPLIED = 'vip_boost_applied';

    public const VENDOR_FEEDBACK_RECEIVED = 'vendor_feedback_received';

    public const VENDOR_FEEDBACK_APPROVED = 'vendor_feedback_approved';

    public const CONTACT_MESSAGE = 'contact_message';

    public const SUPPORT_TICKET = 'support_ticket';

    public const SUPPORT_REPLY = 'support_reply';

    public const MEMBERSHIP_EXPIRING = 'membership_expiring';

    public const ESCROW = 'escrow';

    public const TEST = 'test';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ACTIVATION,
            self::EMAIL_VERIFICATION,
            self::WELCOME,
            self::RESET_PASSWORD,
            self::ORDER_CONFIRMATION,
            self::NEW_ORDER,
            self::NEW_ORDER_SELLER,
            self::ORDER_SHIPPED,
            self::QUOTE_REQUEST,
            self::QUOTE_SUBMITTED,
            self::QUOTE_ACCEPTED,
            self::QUOTE_REJECTED,
            self::REFUND_SUBMITTED,
            self::REFUND_APPROVED,
            self::REFUND_REJECTED,
            self::REFUND_MESSAGE,
            self::NEW_MESSAGE,
            self::PRODUCT_APPROVED,
            self::PRODUCT_REJECTED,
            self::NEW_PRODUCT,
            self::SHOP_OPENING_SUBMITTED,
            self::SHOP_OPENING_APPROVED,
            self::SHOP_OPENING_REJECTED,
            self::SHOP_OPENING_ADMIN_ALERT,
            self::MEMBERSHIP_SUBSCRIBED,
            self::PROMOTION_APPLIED,
            self::VIP_BOOST_APPLIED,
            self::VENDOR_FEEDBACK_RECEIVED,
            self::VENDOR_FEEDBACK_APPROVED,
            self::CONTACT_MESSAGE,
            self::SUPPORT_TICKET,
            self::SUPPORT_REPLY,
            self::MEMBERSHIP_EXPIRING,
            self::ESCROW,
            self::TEST,
        ];
    }
}
