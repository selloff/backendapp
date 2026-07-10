<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Notification\Services\EmailOptionGate;
use App\Modules\Selloff\Notification\Services\TransactionalEmailService;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use Illuminate\Support\Collection;

class MembershipExpiryNotificationService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly EmailOptionGate $gate,
    ) {}

    public function notifyDueSubscriptions(int $lookbackDays = 2): int
    {
        $sent = 0;

        foreach ($this->dueSubscriptions($lookbackDays) as $subscription) {
            if ($this->notify($subscription)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function notify(UserMembershipPlan $subscription): bool
    {
        $subscription->loadMissing(['user', 'membershipPlan']);
        $user = $subscription->user;
        $email = trim((string) ($user?->email ?? ''));

        if ($email === '' || ! $this->gate->isEnabled(TransactionalEmailType::MEMBERSHIP_EXPIRING)) {
            return false;
        }

        $planTitle = $subscription->membershipPlan?->title ?? 'Membership plan';
        $expiresAt = $subscription->expires_at?->timezone(config('app.timezone'))->format('F j, Y')
            ?? 'recently';
        $renewUrl = $this->renewUrl();

        $this->email->sendNow(
            TransactionalEmailType::MEMBERSHIP_EXPIRING,
            $email,
            [
                'title' => 'Membership expired',
                'planName' => $planTitle,
                'expiresAt' => $expiresAt,
                'renewUrl' => $renewUrl,
                'buttonText' => 'Renew now',
            ],
            subject: "Your {$planTitle} membership has expired",
            template: 'membership-expiry',
        );

        UserMembershipPlan::query()
            ->where('user_id', $subscription->user_id)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNull('expiry_notified_at')
            ->update(['expiry_notified_at' => now()]);

        return true;
    }

    /**
     * @return Collection<int, UserMembershipPlan>
     */
    public function dueSubscriptions(int $lookbackDays = 2): Collection
    {
        $lookbackDays = max(1, $lookbackDays);

        return UserMembershipPlan::query()
            ->with(['user', 'membershipPlan'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('expires_at', '>=', now()->subDays($lookbackDays))
            ->whereNull('expiry_notified_at')
            ->orderByDesc('expires_at')
            ->get()
            ->unique('user_id')
            ->values();
    }

    public function renewUrl(): string
    {
        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        return "{$base}/vendor/membership/subscribe";
    }
}
