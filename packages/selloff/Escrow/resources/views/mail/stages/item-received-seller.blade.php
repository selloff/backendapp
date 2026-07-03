@extends('selloff-escrow::mail.layouts.escrow')

@section('content')
<div class="mailcontent">
    <h2>{{ $mail->subject }}</h2>
    <p>Hello {{ $mail->recipientName }},</p>
    <p>The buyer confirmed receipt of the item.</p>
    <p>Your payment will be processed within 24 hours to the bank account provided to our Escrow department.</p>
    <hr>
    @include('selloff-escrow::mail.partials.product-block')
    <p>Don't hesitate to contact us via {{ $mail->branding['escrow_email'] }} if you have any issues regarding the item/transaction.</p>
    <p>Thank you,<br>Selloff.ng Escrow Team<br><a href="{{ $mail->branding['site_url'] }}">{{ $mail->branding['site_name'] }}</a></p>
</div>
@endsection
