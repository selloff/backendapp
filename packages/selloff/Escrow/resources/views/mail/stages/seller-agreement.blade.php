@extends('selloff-escrow::mail.layouts.escrow')

@section('content')
<div class="mailcontent">
    <h2>{{ $mail->subject }}</h2>
    <p>Hello {{ $mail->recipientName }},</p>
    <p>A buyer wants to purchase your item through Selloff Escrow. Please review the details below and confirm your agreement to sell.</p>
    <p>Here is a breakdown of the item price and escrow fee:</p>
    @include('selloff-escrow::mail.partials.price-breakdown-inner')
    <hr>
    @include('selloff-escrow::mail.partials.product-block')
    <p>Please click the button below to agree to sell this item using Selloff Escrow service.</p>
    @include('selloff-escrow::mail.partials.cta-button', ['url' => $mail->agreementUrl, 'label' => 'Agree to Sell With Escrow', 'color' => $mail->ctaColor()])
    <p>Contact us via {{ $mail->branding['escrow_email'] }} if you have any issues or need support with your escrow transaction.</p>
    <p>Thank you,<br>Selloff.ng Escrow Team<br><a href="{{ $mail->branding['site_url'] }}">{{ $mail->branding['site_name'] }}</a></p>
</div>
@endsection
