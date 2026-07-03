@extends('selloff-escrow::mail.layouts.escrow')

@section('content')
<div class="mailcontent">
    <h2>{{ $mail->subject }}</h2>
    <p>Hello {{ $mail->recipientName }},</p>
    <p>You have indicated that you would like to purchase the following item through our Escrow Service. Upon clicking the link below, we will notify the Seller. Once the Seller agrees to the sale, the contract will be established immediately and a payment link will be emailed to you.</p>
    <p>We will process your payment in our secure vault and release the funds to the Seller only after you confirm that you have received the item in good condition.</p>
    <p>Here is a breakdown of the item price and escrow fee:</p>
    @include('selloff-escrow::mail.partials.price-breakdown-inner')
    <hr>
    @include('selloff-escrow::mail.partials.product-block')
    <p>Please click the button below to agree to buy this item using Selloff Escrow service.</p>
    @include('selloff-escrow::mail.partials.cta-button', ['url' => $mail->agreementUrl, 'label' => 'Agree to Buy With Escrow', 'color' => $mail->ctaColor()])
    <p>Contact us via {{ $mail->branding['escrow_email'] }} if you have any issues or need support with your escrow transaction.</p>
    <p>Thank you,<br>Selloff.ng Escrow Team<br><a href="{{ $mail->branding['site_url'] }}">{{ $mail->branding['site_name'] }}</a></p>
</div>
@endsection
