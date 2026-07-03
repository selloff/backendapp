@extends('selloff-escrow::mail.layouts.escrow')

@section('content')
<div class="mailcontent">
    <h2>{{ $mail->subject }}</h2>
    <p>Here is a breakdown of the item price and escrow fee.</p>
    @include('selloff-escrow::mail.partials.price-breakdown-inner', ['showParties' => true, 'adminDeliveryNote' => true])
    <hr>
    @include('selloff-escrow::mail.partials.product-block')
    <p>Thank you,<br>Selloff.ng Escrow Bot<br><a href="{{ $mail->branding['site_url'] }}">{{ $mail->branding['site_name'] }}</a></p>
</div>
@endsection
