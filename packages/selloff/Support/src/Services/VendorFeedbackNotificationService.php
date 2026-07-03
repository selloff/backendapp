<?php

namespace App\Modules\Selloff\Support\Services;

use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\User\Models\VendorProfile;
use Illuminate\Support\Facades\Mail;

class VendorFeedbackNotificationService
{
    public function sendApprovedToVendor(Feedback $feedback): void
    {
        $feedback->loadMissing(['vendor', 'user']);

        $vendorEmail = $feedback->vendor?->email;
        if ($vendorEmail === null || $vendorEmail === '') {
            return;
        }

        $shopName = VendorProfile::query()
            ->where('user_id', $feedback->vendor_id)
            ->value('shop_name') ?? $feedback->vendor->name;

        $authorName = $feedback->user?->name ?? 'A buyer';
        $type = ucfirst((string) $feedback->feedback_type);
        $rating = $feedback->rating !== null ? " ({$feedback->rating}/5 stars)" : '';

        $body = <<<TEXT
Hello {$shopName},

New seller feedback has been approved on your Selloff shop.

From: {$authorName}
Type: {$type}{$rating}

"{$feedback->feedback}"

Sign in to your vendor dashboard to view, reply, or dispute this feedback if needed.

— Selloff
TEXT;

        Mail::raw($body, function ($message) use ($vendorEmail, $shopName): void {
            $message->to($vendorEmail)
                ->subject("New seller feedback — {$shopName}");
        });
    }
}
