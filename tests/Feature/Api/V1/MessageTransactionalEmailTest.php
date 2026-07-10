<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;
use App\Modules\Selloff\Notification\Mail\TransactionalMail;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    app(PlatformSettingsService::class)->flushCache();
    EmailJob::query()->delete();
});

function disableMessageEmailOption(string $key): void
{
    PlatformSetting::query()->updateOrCreate(
        ['key' => $key],
        ['value' => false, 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();
}

function sendMessageBetweenUsers(User $sender, User $receiver, string $body = 'Hello there'): Conversation
{
    Sanctum::actingAs($sender);

    test()->postJson('/api/v1/messages/send-new-conversation-message', [
        'receiver_id' => $receiver->id,
        'message' => $body,
        'subject' => 'Inquiry',
    ])->assertCreated();

    return Conversation::query()
        ->where('sender_id', $sender->id)
        ->where('receiver_id', $receiver->id)
        ->firstOrFail();
}

test('sending a message queues a delayed new message email job', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    sendMessageBetweenUsers($buyer, $vendor, 'Is this still available?');

    $job = EmailJob::query()->where('email_type', 'new_message')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('vendor@selloff.test')
        ->and($job->template)->toBe('new-message')
        ->and($job->status)->toBe('pending')
        ->and($job->scheduled_at)->not->toBeNull()
        ->and($job->scheduled_at->greaterThan(now()->addMinutes(4)))->toBeTrue()
        ->and($job->scheduled_at->lessThanOrEqualTo(now()->addMinutes(5)))->toBeTrue();
});

test('follow up messages in the same conversation reschedule a single pending email job', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    $conversation = sendMessageBetweenUsers($buyer, $vendor, 'First ping');
    $firstJob = EmailJob::query()->where('email_type', 'new_message')->firstOrFail();
    $firstScheduledAt = $firstJob->scheduled_at?->copy();

    Sanctum::actingAs($buyer);
    $this->travel(2)->minutes();

    $this->postJson('/api/v1/messages/send-conversation-message', [
        'conversation_id' => $conversation->id,
        'message' => 'Second ping',
    ])->assertCreated();

    expect(EmailJob::query()->where('email_type', 'new_message')->count())->toBe(1);

    $firstJob->refresh();

    expect($firstJob->scheduled_at?->greaterThan($firstScheduledAt))->toBeTrue()
        ->and((int) ($firstJob->metadata['message_id'] ?? 0))->toBe(
            (int) Message::query()->where('conversation_id', $conversation->id)->orderByDesc('id')->value('id'),
        );
});

test('new message email is skipped when the receiver is online before the worker runs', function () {
    Mail::fake();

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    sendMessageBetweenUsers($buyer, $vendor, 'Are you there?');
    $job = EmailJob::query()->where('email_type', 'new_message')->firstOrFail();

    Sanctum::actingAs($vendor);
    $this->postJson('/api/v1/auth/presence')->assertOk();

    $job->update(['scheduled_at' => now()->subMinute()]);

    $this->artisan('selloff:send-email-jobs')->assertSuccessful();

    $job->refresh();

    expect($job->status)->toBe('skipped')
        ->and($job->skipped_at)->not->toBeNull()
        ->and($job->last_error)->toBe('receiver_online');

    Mail::assertNothingSent();
});

test('new message email is skipped when the message was read before the worker runs', function () {
    Mail::fake();

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    $conversation = sendMessageBetweenUsers($buyer, $vendor, 'Please reply soon');
    $job = EmailJob::query()->where('email_type', 'new_message')->firstOrFail();

    Sanctum::actingAs($vendor);
    $this->getJson("/api/v1/messages/set-conversation-messages-as-read/{$conversation->id}")->assertOk();

    $job->update(['scheduled_at' => now()->subMinute()]);

    $this->artisan('selloff:send-email-jobs')->assertSuccessful();

    $job->refresh();

    expect($job->status)->toBe('skipped')
        ->and($job->skipped_at)->not->toBeNull()
        ->and($job->last_error)->toBe('message_already_read');

    Mail::assertNothingSent();
});

test('due unread message emails are delivered through the transactional mailer', function () {
    Mail::fake();

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    sendMessageBetweenUsers($buyer, $vendor, 'Delivery test message');

    $job = EmailJob::query()->where('email_type', 'new_message')->firstOrFail();
    $job->update(['scheduled_at' => now()->subMinute()]);

    $this->artisan('selloff:send-email-jobs')->assertSuccessful();

    $job->refresh();

    expect($job->status)->toBe('sent');

    Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail): bool {
        return $mail->hasTo('vendor@selloff.test')
            && $mail->template === 'new-message';
    });
});

test('new message emails are skipped when platform toggle is disabled', function () {
    disableMessageEmailOption('email_option_new_message');

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    sendMessageBetweenUsers($buyer, $vendor, 'Should not email');

    expect(EmailJob::query()->where('email_type', 'new_message')->count())->toBe(0);
});

test('new message emails are not queued when receiver opts out', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $vendor->update(['send_email_new_message' => false]);

    sendMessageBetweenUsers($buyer, $vendor, 'Opted out');

    expect(EmailJob::query()->where('email_type', 'new_message')->count())->toBe(0);
});

test('user can update send email new message preference via auth me', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->patchJson('/api/v1/auth/me', [
        'send_email_new_message' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.user.send_email_new_message', false);

    $buyer->refresh();
    expect($buyer->send_email_new_message)->toBeFalse();
});
