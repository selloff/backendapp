@extends('selloff-escrow::mail.layouts.escrow')

@section('content')
<div class="mailcontent">
    <h2>{{ $mail->subject }}</h2>
    <p>Hello {{ $mail->recipientName }},</p>
    <p>We have recorded your escrow payment. The seller will ship your item soon.</p>
    <p>Here is a breakdown of the item price and escrow fee.</p>
    @include('selloff-escrow::mail.partials.price-breakdown-inner')
    <hr>
    @include('selloff-escrow::mail.partials.product-block')
    <p>Don't hesitate to contact us via {{ $mail->branding['escrow_email'] }} if you have any issues or need support with your escrow transaction.</p>
    <p>Thank you,<br>Selloff.ng Escrow Team<br><a href="{{ $mail->branding['site_url'] }}">{{ $mail->branding['site_name'] }}</a></p>
</div>
@endsection
