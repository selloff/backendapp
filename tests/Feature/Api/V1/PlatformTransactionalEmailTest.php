<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\Selloff\Escrow\Mail\EscrowStageMail;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowMailStage;
use App\Modules\Selloff\Notification\Mail\TransactionalMail;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipExpiryNotificationService;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Models\SupportTicket;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    app(PlatformSettingsService::class)->flushCache();
    EmailJob::query()->delete();

    PlatformSetting::query()->updateOrCreate(
        ['key' => 'mail_options_account'],
        ['value' => 'ops@selloff.test', 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();
});

function disablePlatformEmailOption(string $key): void
{
    PlatformSetting::query()->updateOrCreate(
        ['key' => $key],
        ['value' => false, 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();
}

test('contact form queues admin contact message email job', function () {
    $this->postJson('/api/v1/contact', [
        'name' => 'Ada Buyer',
        'email' => 'ada@example.test',
        'subject' => 'Need help',
        'message' => 'Please call me back about my order.',
    ])->assertCreated();

    $job = EmailJob::query()->where('email_type', 'contact_message')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('ops@selloff.test')
        ->and($job->template)->toBe('contact-message')
        ->and($job->template_data['senderName'] ?? null)->toBe('Ada Buyer');
});

test('contact message email is skipped when contact toggle is disabled', function () {
    disablePlatformEmailOption('email_option_contact_messages');

    $this->postJson('/api/v1/contact', [
        'name' => 'Ada Buyer',
        'email' => 'ada@example.test',
        'subject' => 'Need help',
        'message' => 'Please call me back about my order.',
    ])->assertCreated();

    expect(EmailJob::query()->where('email_type', 'contact_message')->count())->toBe(0);
});

test('vendor feedback submission queues vendor feedback received email job', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    Feedback::query()->where('vendor_id', $vendor->id)->where('user_id', $buyer->id)->delete();

    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/vendors/{$vendor->vendorProfile->slug}/feedback", [
        'feedback_type' => 'positive',
        'feedback' => 'Excellent communication and fast delivery.',
        'rating' => 5,
    ])->assertCreated();

    $job = EmailJob::query()->where('email_type', 'vendor_feedback_received')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('vendor@selloff.test')
        ->and($job->template)->toBe('feedback-message');
});

test('vendor feedback approve sends approved email through transactional mail', function () {
    Mail::fake();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

    Feedback::query()->where('vendor_id', $vendor->id)->where('user_id', $buyer->id)->delete();
    EmailJob::query()->delete();

    Sanctum::actingAs($buyer);
    $this->postJson("/api/v1/vendors/{$vendor->vendorProfile->slug}/feedback", [
        'feedback_type' => 'positive',
        'feedback' => 'Excellent communication and fast delivery.',
        'rating' => 5,
    ])->assertCreated();

    $feedbackId = Feedback::query()
        ->where('vendor_id', $vendor->id)
        ->where('user_id', $buyer->id)
        ->value('id');

    Sanctum::actingAs($admin);
    $this->patchJson("/api/v1/admin/feedback/{$feedbackId}", [
        'action' => 'approve',
    ])->assertOk();

    Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail): bool {
        return $mail->hasTo('vendor@selloff.test')
            && str_contains($mail->mailSubject, 'seller feedback');
    });
});

test('support ticket creation queues admin and user support ticket email jobs', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/support/tickets', [
        'subject' => 'Payment issue',
        'message' => 'My wallet payment failed twice.',
    ])->assertCreated();

    $jobs = EmailJob::query()->where('email_type', 'support_ticket')->get();

    expect($jobs)->toHaveCount(2)
        ->and($jobs->pluck('to_email')->all())->toEqualCanonicalizing([
            'ops@selloff.test',
            'buyer@selloff.test',
        ]);
});

test('admin support reply queues support reply email job for user', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

    Sanctum::actingAs($buyer);
    $ticketId = $this->postJson('/api/v1/support/tickets', [
        'subject' => 'Need help',
        'message' => 'Please assist.',
    ])->assertCreated()->json('data.id');

    EmailJob::query()->delete();

    Sanctum::actingAs($admin);
    $this->postJson("/api/v1/admin/support/tickets/{$ticketId}/reply", [
        'message' => 'We are looking into this now.',
    ], superAdminPinHeaders())->assertOk();

    $job = EmailJob::query()->where('email_type', 'support_reply')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('buyer@selloff.test');
});

test('membership expiry notification sends branded transactional mail when enabled', function () {
    Mail::fake();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

    $subscription = UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subHours(6),
            'is_active' => true,
            'expiry_notified_at' => null,
        ],
    );

    app(MembershipExpiryNotificationService::class)->notify($subscription->fresh());

    Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail): bool {
        return $mail->hasTo('vendor@selloff.test')
            && $mail->template === 'membership-expiry'
            && str_contains($mail->mailSubject, 'Demo Vendor Pro');
    });
});

test('membership expiry notification is skipped when expiry toggle is disabled', function () {
    Mail::fake();
    disablePlatformEmailOption('email_option_membership_expiry');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

    $subscription = UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subHours(6),
            'is_active' => true,
            'expiry_notified_at' => null,
        ],
    );

    expect(app(MembershipExpiryNotificationService::class)->notify($subscription->fresh()))->toBeFalse();

    Mail::assertNothingSent();
});

test('escrow notification is skipped when escrow toggle is disabled', function () {
    Mail::fake();
    disablePlatformEmailOption('email_option_escrow');
    config(['selloff.escrow_admin_email' => 'escrow-admin@selloff.test']);

    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $transaction->update([
        'buyer_agreed' => true,
        'seller_agreed' => false,
        'status' => 'buyer_agreed',
    ]);

    $this->postJson('/api/v1/escrow/token/demo-seller-escrow-token/confirm')
        ->assertOk();

    Mail::assertNothingSent();
});

test('escrow notification still sends when escrow toggle is enabled', function () {
    Mail::fake();
    config(['selloff.escrow_admin_email' => 'escrow-admin@selloff.test']);

    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $transaction->update([
        'buyer_agreed' => true,
        'seller_agreed' => false,
        'status' => 'buyer_agreed',
    ]);

    $this->postJson('/api/v1/escrow/token/demo-seller-escrow-token/confirm')
        ->assertOk();

    Mail::assertSent(EscrowStageMail::class, function (EscrowStageMail $mail): bool {
        return $mail->data->stage === EscrowMailStage::AdminEscrowInitiation;
    });
});
