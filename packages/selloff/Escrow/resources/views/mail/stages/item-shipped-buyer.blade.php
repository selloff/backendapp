@extends('selloff-escrow::mail.layouts.escrow')

@section('content')
<div class="mailcontent">
    <h2>{{ $mail->subject }}</h2>
    <p>Hello {{ $mail->recipientName }},</p>
    <p>The seller has shipped your item.</p>
    @if($mail->deliveryAddress)
    <p><strong>Delivery Address:</strong><br><span style="color: {{ $mail->branding['emphasis'] }};">{{ $mail->deliveryAddress }}</span></p>
    @endif
    @include('selloff-escrow::mail.partials.cta-button', ['url' => $mail->agreementUrl, 'label' => 'I have received the item.', 'color' => $mail->ctaColor()])
    <hr>
    @include('selloff-escrow::mail.partials.product-block')
    <p>Don't hesitate to contact us via {{ $mail->branding['escrow_email'] }} if you have any issues regarding the item/transaction.</p>
    <p>Thank you,<br>Selloff.ng Escrow Team<br><a href="{{ $mail->branding['site_url'] }}">{{ $mail->branding['site_name'] }}</a></p>
</div>
@endsection
