<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Models\User;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\MonetizationMailViewDataFactory;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;

class MembershipEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly MonetizationMailViewDataFactory $viewData,
    ) {}

    public function queueSubscribed(
        User $user,
        MembershipPlan $plan,
        UserMembershipPlan $subscription,
        string $purchaseType,
        int $months,
        float $amountPaid,
    ): ?EmailJob {
        $to = trim((string) ($user->email ?? ''));

        if ($to === '') {
            return null;
        }

        $planName = trim((string) ($plan->title ?? 'Membership plan'));
        $subject = "Your {$planName} membership is active";

        return $this->email->queue(
            TransactionalEmailType::MEMBERSHIP_SUBSCRIBED,
            $to,
            $this->viewData->forMembership($user, $plan, $subscription, $purchaseType, $months, $amountPaid),
            subject: $subject,
            template: 'membership-subscribed',
        );
    }
}
