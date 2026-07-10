@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 24px; line-height: 28px; font-weight: bold;">
        {{ $data['title'] ?? 'Thank you for your order' }}
    </h1>
    <div class="mailcontent">
        <p>Your order has been received.</p>
        <h2 style="margin-bottom: 10px; font-size: 16px; font-weight: 600;">Order information</h2>
        <p style="color: #555;">
            Order: #{{ $data['orderNumber'] }}<br>
            Payment status: {{ $data['paymentStatus'] }}<br>
            Payment method: {{ $data['paymentMethod'] }}<br>
            Date: {{ $data['orderDate'] }}
        </p>

        @if(!empty($data['shippingAddress']) || !empty($data['billingAddress']))
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top: 30px; width: 100%;">
                <tr>
                    @if(!empty($data['shippingAddress']))
                        <td style="vertical-align: top; width: 50%;">
                            <h3 style="margin-bottom: 10px; font-size: 16px; font-weight: 600;">Shipping address</h3>
                            <p style="color: #555;">
                                @foreach($data['shippingAddress'] as $label => $value)
                                    {{ ucfirst($label) }}: {{ $value }}<br>
                                @endforeach
                            </p>
                        </td>
                    @endif
                    @if(!empty($data['billingAddress']))
                        <td style="vertical-align: top; width: 50%;">
                            <h3 style="margin-bottom: 10px; font-size: 16px; font-weight: 600;">Billing address</h3>
                            <p style="color: #555;">
                                @foreach($data['billingAddress'] as $label => $value)
                                    {{ ucfirst($label) }}: {{ $value }}<br>
                                @endforeach
                            </p>
                        </td>
                    @endif
                </tr>
            </table>
        @endif

        @include('selloff-notification::mail.partials.order-lines', ['data' => $data])

        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="text-align: right; margin-top: 40px; width: 100%;">
            <tr>
                <td style="width: 70%">Subtotal</td>
                <td style="width: 30%; padding-right: 15px; font-weight: 600;">{{ $data['subtotal'] }}</td>
            </tr>
            @if(!empty($data['vat']))
                <tr>
                    <td style="width: 70%">VAT</td>
                    <td style="width: 30%; padding-right: 15px; font-weight: 600;">{{ $data['vat'] }}</td>
                </tr>
            @endif
            <tr>
                <td style="width: 70%">Shipping</td>
                <td style="width: 30%; padding-right: 15px; font-weight: 600;">{{ $data['shipping'] }}</td>
            </tr>
            @if(!empty($data['couponDiscount']))
                <tr>
                    <td style="width: 70%">Coupon [{{ $data['couponCode'] }}]</td>
                    <td style="width: 30%; padding-right: 15px; font-weight: 600;">-{{ $data['couponDiscount'] }}</td>
                </tr>
            @endif
            <tr>
                <td style="width: 70%; font-weight: bold">Total</td>
                <td style="width: 30%; padding-right: 15px; font-weight: 600;">{{ $data['total'] }}</td>
            </tr>
        </table>

        @if(($data['showOrderButton'] ?? true) && !empty($data['orderUrl']))
            <p style="text-align: center; margin-top: 40px;">
                <span class="btn-primary">
                    <a href="{{ $data['orderUrl'] }}">{{ $data['buttonText'] ?? 'See order details' }}</a>
                </span>
            </p>
        @endif
    </div>
@endsection
