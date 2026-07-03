@extends('selloff-escrow::mail.layouts.escrow')

@section('content')
<div class="mailcontent">
    <h2>{{ $mail->subject }}</h2>
    <p>Hello {{ $mail->recipientName }},</p>
    <p>The buyer has paid for this item. Please ship to the delivery address below and confirm when sent.</p>
    @if($mail->deliveryAddress)
    <p><strong>Delivery Address:</strong><br><span style="color: {{ $mail->branding['emphasis'] }};">{{ $mail->deliveryAddress }}</span></p>
    @endif
    @include('selloff-escrow::mail.partials.cta-button', ['url' => $mail->agreementUrl, 'label' => 'Confirm Item sent or shipped', 'color' => $mail->ctaColor()])
    <hr>
    @include('selloff-escrow::mail.partials.product-block')
    <p>Don't hesitate to contact us via {{ $mail->branding['escrow_email'] }} if you have any issues or need support with your escrow transaction.</p>
    <p>Thank you,<br>Selloff.ng Escrow Team<br><a href="{{ $mail->branding['site_url'] }}">{{ $mail->branding['site_name'] }}</a></p>
</div>
@endsection
