<?php

use App\Modules\Selloff\Escrow\Mail\EscrowStageMail;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowMailStage;
use Illuminate\Support\Facades\Mail;

uses(\Tests\Feature\Api\V1\Concerns\InteractsWithDemoEscrow::class);

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('full escrow workflow sends expected emails and completes eleven stages', function () {
    Mail::fake();
    config([
        'selloff.escrow_admin_email' => 'escrow-admin@selloff.test',
        'selloff.escrow_bank.account_number' => '1023373630',
    ]);

    $product = $this->demoClassifiedProduct();
    $this->actAsDemoBuyer();

    $initiate = $this->postJson('/api/v1/initiate-escrow', ['product_id' => $product->id])
        ->assertCreated();

    $transactionId = (int) $initiate->json('data.id');
    $buyerToken = tokenFromUrl_in_EscrowWorkflowEmail((string) $initiate->json('data.agreement_urls.buyer'));
    $sellerToken = tokenFromUrl_in_EscrowWorkflowEmail((string) $initiate->json('data.agreement_urls.seller'));

    Mail::assertSent(EscrowStageMail::class, 2);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::BuyerAgreement);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::SellerAgreement);

    Mail::fake();

    $this->postJson("/api/v1/escrow/token/{$buyerToken}/confirm")->assertOk();
    Mail::assertNothingSent();

    $this->postJson("/api/v1/escrow/token/{$sellerToken}/confirm")->assertOk();
    Mail::assertSent(EscrowStageMail::class, 1);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::AdminEscrowInitiation
        && $mail->hasTo('escrow-admin@selloff.test'));

    Mail::fake();
    $this->actAsDemoAdmin();

    $this->patchJson("/api/v1/admin/escrow/transactions/{$transactionId}/stages", [
        'delivery_cost' => 2500,
        'delivery_address' => '12 Admiralty Way, Lekki, Lagos',
    ])->assertOk();

    Mail::assertNothingSent();

    $this->patchJson("/api/v1/admin/escrow/transactions/{$transactionId}/stages", [
        'payment_link_sent' => true,
        'payment_link_url' => 'https://paystack.com/pay/test-escrow',
    ])->assertOk();

    Mail::assertSent(EscrowStageMail::class, 1);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::PaymentLink
        && $mail->hasTo('buyer@selloff.test'));

    Mail::fake();

    $this->patchJson("/api/v1/admin/escrow/transactions/{$transactionId}/stages", [
        'payment_received' => true,
        'payment_reference' => 'DEMO-PAY-REF',
    ])->assertOk();

    Mail::assertSent(EscrowStageMail::class, 2);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::BuyerPaidBuyer);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::BuyerPaidSeller);

    Mail::fake();

    $transaction = EscrowTransaction::query()->findOrFail($transactionId);
    $sellerToken = (string) $transaction->seller_agreement_token;

    $this->postJson("/api/v1/escrow/token/{$sellerToken}/confirm-shipped")->assertOk();

    Mail::assertSent(EscrowStageMail::class, 2);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::ItemShippedBuyer);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::AdminItemShipped);

    Mail::fake();

    $buyerToken = (string) $transaction->fresh()->buyer_agreement_token;
    $this->postJson("/api/v1/escrow/token/{$buyerToken}/confirm-delivery")->assertOk();

    Mail::assertSent(EscrowStageMail::class, 2);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::ItemReceivedSeller);
    Mail::assertSent(EscrowStageMail::class, fn (EscrowStageMail $mail): bool => $mail->data->stage === EscrowMailStage::AdminItemReceived);

    Mail::fake();
    $this->actAsDemoAdmin();

    $this->patchJson("/api/v1/admin/escrow/transactions/{$transactionId}/stages", [
        'seller_received_payment' => true,
        'transaction_complete' => true,
    ])->assertOk();

    Mail::assertNothingSent();

    $stages = $this->getJson("/api/v1/admin/escrow/transactions/{$transactionId}")
        ->assertOk()
        ->json('data.stages');

    expect($stages)->toHaveCount(11);
    foreach ($stages as $stage) {
        expect($stage['done'])->toBeTrue("Stage {$stage['key']} should be done");
    }
});

test('payment link email html contains bank and branding', function () {
    $transaction = $this->demoEscrowTransaction();
    $transaction->update([
        'buyer_agreed' => true,
        'seller_agreed' => true,
        'delivery_cost' => 1500,
        'delivery_address' => 'Lagos',
    ]);

    $factory = app(\App\Modules\Selloff\Escrow\Services\EscrowMailViewDataFactory::class);
    $data = $factory->forStage(
        $transaction->fresh(['product.translations', 'product.images', 'buyer', 'seller']),
        EscrowMailStage::PaymentLink,
        'Test payment',
        'Buyer',
        paymentUrl: 'https://paystack.com/pay/test',
    );

    $html = view($data->stage->htmlView(), ['mail' => $data])->render();

    $this->assertStringContainsString('1023373630', $html);
    $this->assertStringContainsString('#0075bb', $html);
    $this->assertStringContainsString('https://paystack.com/pay/test', $html);
    $this->assertStringContainsString('selloff-logo', $html);
});

function tokenFromUrl_in_EscrowWorkflowEmail(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);

    return (string) basename((string) $path);
}