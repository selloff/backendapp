<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class MembershipExpiryNotificationService
{
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

        if ($email === '') {
            return false;
        }

        $planTitle = $subscription->membershipPlan?->title ?? 'Membership plan';
        $expiresAt = $subscription->expires_at?->timezone(config('app.timezone'))->format('F j, Y')
            ?? 'recently';
        $renewUrl = $this->renewUrl();

        Mail::raw(
            $this->buildBody($planTitle, $expiresAt, $renewUrl),
            function ($message) use ($email, $planTitle): void {
                $message->to($email)->subject("Your {$planTitle} membership has expired");
            },
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

    private function buildBody(string $planTitle, string $expiresAt, string $renewUrl): string
    {
        return implode("\n", [
            'Hello,',
            '',
            "Your {$planTitle} membership expired on {$expiresAt}.",
            'Renew your plan to continue enjoying vendor benefits such as listing products and promotions.',
            '',
            "Renew now: {$renewUrl}",
            '',
            'Thank you,',
            'Selloff',
        ]);
    }
}
