@extends('selloff-escrow::mail.layouts.escrow')

@section('content')
<div class="mailcontent">
    <h2>{{ $mail->subject }}</h2>
    <p>Hello {{ $mail->recipientName }},</p>
    <p>Please pay for this item using the secure payment link below.</p>
    @include('selloff-escrow::mail.partials.cta-button', ['url' => $mail->paymentUrl, 'label' => 'Make Payment Now', 'color' => $mail->ctaColor()])
    <p>Alternatively, you can transfer payment to the following bank account and email proof of payment to {{ $mail->branding['escrow_email'] }}.</p>
    <p>
        {{ $mail->bank['account_number'] ?? '' }}<br>
        {{ $mail->bank['bank_name'] ?? '' }}<br>
        {{ $mail->bank['account_name'] ?? '' }}
    </p>
    <p><span style="color: {{ $mail->branding['emphasis'] }};">You have to make payment within 48 hours.</span></p>
    <p>Here is a breakdown of the item price and escrow fee.</p>
    @include('selloff-escrow::mail.partials.price-breakdown-inner')
    <hr>
    @include('selloff-escrow::mail.partials.product-block')
    <p>We process your payment in our secure vault, and will release the funds to the seller only after you confirm that you have received the item in good condition.</p>
    <p>Don't hesitate to contact us via {{ $mail->branding['escrow_email'] }} if you have any issues or need support with your escrow transaction.</p>
    <p>Thank you,<br>Selloff.ng Escrow Team<br><a href="{{ $mail->branding['site_url'] }}">{{ $mail->branding['site_name'] }}</a></p>
</div>
@endsection
