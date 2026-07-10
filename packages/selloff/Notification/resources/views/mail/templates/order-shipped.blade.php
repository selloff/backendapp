@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 24px; line-height: 28px; font-weight: bold;">
        {{ $data['title'] ?? 'Your order has shipped' }}
    </h1>
    <div class="mailcontent">
        <h2 style="margin-bottom: 10px; font-size: 16px; font-weight: 600;">Order information</h2>
        <p style="color: #555;">
            Order: #{{ $data['orderNumber'] }}<br>
            Payment status: {{ $data['paymentStatus'] }}<br>
            Payment method: {{ $data['paymentMethod'] }}<br>
            Date: {{ $data['orderDate'] }}
        </p>

        @if(!empty($data['trackingNumber']) || !empty($data['trackingUrl']))
            <h2 style="margin-bottom: 10px; font-size: 16px; font-weight: 600; margin-top: 24px;">Shipping</h2>
            <p style="color: #555;">
                @if(!empty($data['trackingNumber']))
                    Tracking code: {{ $data['trackingNumber'] }}<br>
                @endif
                @if(!empty($data['trackingUrl']))
                    Tracking URL: <a href="{{ $data['trackingUrl'] }}">{{ $data['trackingUrl'] }}</a><br>
                @endif
            </p>
        @endif

        <h3 style="margin-bottom: 10px; font-size: 16px; font-weight: 600; border-bottom: 1px solid #d1d1d1; padding-bottom: 5px; margin-top: 45px;">
            Shipped product
        </h3>

        @include('selloff-notification::mail.partials.order-lines', ['data' => $data])

        @if(!empty($data['orderUrl']))
            <p style="text-align: center; margin-top: 40px;">
                <span class="btn-primary">
                    <a href="{{ $data['orderUrl'] }}">{{ $data['buttonText'] ?? 'See order details' }}</a>
                </span>
            </p>
        @endif
    </div>
@endsection
