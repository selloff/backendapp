<?php

use App\Models\User;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Models\FeedbackDispute;
use App\Modules\Selloff\Support\Models\FeedbackReply;
use App\Modules\Selloff\User\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('guest cannot submit vendor feedback', function () {
    $vendor = User::query()->whereHas('vendorProfile')->firstOrFail();

    $this->postJson("/api/v1/vendors/{$vendor->vendorProfile->slug}/feedback", [
        'feedback_type' => 'positive',
        'feedback' => 'Great seller experience overall.',
    ])->assertUnauthorized();
});

test('vendor cannot leave feedback on own shop', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendors/{$vendor->vendorProfile->slug}/feedback", [
        'feedback_type' => 'positive',
        'feedback' => 'Great seller experience overall.',
    ])->assertStatus(422);
});

test('buyer submits pending feedback and admin approval publishes it', function () {
    Mail::shouldReceive('raw')
        ->once()
        ->withArgs(fn (string $body) => str_contains($body, 'seller feedback'));

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

    Feedback::query()->where('vendor_id', $vendor->id)->where('user_id', $buyer->id)->delete();

    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/vendors/{$vendor->vendorProfile->slug}/feedback", [
        'feedback_type' => 'positive',
        'feedback' => 'Excellent communication and fast delivery.',
        'rating' => 5,
    ])->assertCreated()
        ->assertJsonPath('data.moderation_status', 'pending');

    $this->getJson("/api/v1/vendors/{$vendor->vendorProfile->slug}/feedback")
        ->assertOk()
        ->assertJsonPath('data.total', 0);

    Sanctum::actingAs($admin);

    $feedbackId = Feedback::query()
        ->where('vendor_id', $vendor->id)
        ->where('user_id', $buyer->id)
        ->value('id');

    $this->patchJson("/api/v1/admin/feedback/{$feedbackId}", [
        'action' => 'approve',
    ])->assertOk()
        ->assertJsonPath('data.moderation_status', 'approved');

    $this->getJson("/api/v1/vendors/{$vendor->vendorProfile->slug}/feedback")
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    $profile = VendorProfile::query()->where('user_id', $vendor->id)->firstOrFail();
    expect($profile->feedback_total_count)->toBeGreaterThanOrEqual(1);
});

test('vendor and buyer reply limits', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    $feedback = Feedback::query()->updateOrCreate(
        [
            'vendor_id' => $vendor->id,
            'user_id' => $buyer->id,
        ],
        [
            'feedback_type' => 'positive',
            'feedback' => 'Approved feedback for reply test.',
            'moderation_status' => 'approved',
            'status' => 'unread',
            'rating' => 5,
        ],
    );

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/feedback/{$feedback->id}/reply", [
        'body' => 'Thank you for your kind words.',
    ])->assertOk();

    $this->postJson("/api/v1/vendor/feedback/{$feedback->id}/reply", [
        'body' => 'Second reply should fail.',
    ])->assertStatus(422);

    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/account/feedback/{$feedback->id}/reply", [
        'body' => 'Appreciate the quick response.',
    ])->assertOk();

    expect(FeedbackReply::query()->where('feedback_id', $feedback->id)->count())->toBe(2);
});

test('vendor can open dispute and admin can resolve', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

    $feedback = Feedback::query()->updateOrCreate(
        [
            'vendor_id' => $vendor->id,
            'user_id' => $buyer->id,
        ],
        [
            'feedback_type' => 'negative',
            'feedback' => 'Disputed feedback sample text.',
            'moderation_status' => 'approved',
            'status' => 'unread',
        ],
    );

    FeedbackDispute::query()->where('feedback_id', $feedback->id)->delete();

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/feedback/{$feedback->id}/dispute", [
        'reason' => 'This feedback contains inaccurate claims about my shop.',
    ])->assertCreated();

    Sanctum::actingAs($admin);

    $disputeId = FeedbackDispute::query()->where('feedback_id', $feedback->id)->value('id');

    $this->patchJson("/api/v1/admin/feedback-disputes/{$disputeId}", [
        'resolution' => 'dismissed',
        'admin_note' => 'Feedback stands after review.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'dismissed');
});

test('admin can hide approved feedback', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

    $feedback = Feedback::query()->updateOrCreate(
        [
            'vendor_id' => $vendor->id,
            'user_id' => $buyer->id,
        ],
        [
            'feedback_type' => 'negative',
            'feedback' => 'Feedback to hide after approval.',
            'moderation_status' => 'approved',
            'status' => 'unread',
            'rating' => 2,
        ],
    );

    Sanctum::actingAs($admin);

    $this->patchJson("/api/v1/admin/feedback/{$feedback->id}", [
        'action' => 'hide',
        'rejection_reason' => 'Violates marketplace guidelines.',
    ])->assertOk()
        ->assertJsonPath('data.moderation_status', 'rejected');

    $this->getJson("/api/v1/vendors/{$vendor->vendorProfile->slug}/feedback")
        ->assertOk()
        ->assertJsonPath('data.total', 0);
});

test('feedback image upload is stored', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

    Feedback::query()->where('vendor_id', $vendor->id)->where('user_id', $admin->id)->delete();

    Sanctum::actingAs($admin);

    $this->post("/api/v1/vendors/{$vendor->vendorProfile->slug}/feedback", [
        'feedback_type' => 'positive',
        'feedback' => 'Item matched the photos I attached.',
        'rating' => 5,
        'image' => UploadedFile::fake()->image('item.jpg'),
    ], [
        'Accept' => 'application/json',
    ])->assertCreated()
        ->assertJsonPath('data.moderation_status', 'pending');

    expect(Feedback::query()
        ->where('vendor_id', $vendor->id)
        ->where('user_id', $admin->id)
        ->value('image_path'))->not->toBeNull();
});

test('vendor can report buyer feedback', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $feedback = Feedback::query()
        ->where('vendor_id', $vendor->id)
        ->where('user_id', '!=', $vendor->id)
        ->where('moderation_status', 'approved')
        ->firstOrFail();

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/feedbacks/{$feedback->id}/report", [
        'description' => 'This feedback contains false claims about my shop.',
        'context' => 'vendor',
    ])->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('abuse_reports', [
        'reporter_id' => $vendor->id,
        'item_id' => $feedback->id,
        'report_type' => 'feedback',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/feedbacks/{$feedback->id}/report", [
        'description' => 'Attempting to report own feedback should fail.',
        'context' => 'buyer',
    ])->assertStatus(422);
});
