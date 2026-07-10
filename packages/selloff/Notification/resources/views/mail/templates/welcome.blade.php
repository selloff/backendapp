@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 21px; line-height: 28px; font-weight: bold; color: #444;">
        Say bye bye to online market scams!
    </h1>
    <div class="mailcontent">
        <p>Hello {{ $data['firstname'] ?? 'there' }},</p>
        <p>We are so excited to have you onboard. Selloff is a fast-growing online marketplace where you can buy or sell anything without worrying about scams.</p>
        <p>Selloff is fast, easy, and extremely secure. Our Escrow service protects you every step of the way.</p>
        <p>
            <strong>Next steps:</strong><br>
            1. <a href="{{ $data['site_url'] ?? $branding['site_url'] }}/how-to-buy-on-sell-off">Learn how to buy on Selloff</a><br>
            2. <a href="{{ $data['site_url'] ?? $branding['site_url'] }}/sell-on-selloff">Learn how to sell anything on Selloff</a>
        </p>
        <p>
            Please don't hesitate to contact us via our
            <a href="{{ $data['site_url'] ?? $branding['site_url'] }}/contact">Support page</a>
            if you have any issues or need support.
        </p>
        <p>
            Thank you,<br>
            {{ $branding['site_name'] }} Team<br>
            <a href="{{ $branding['site_url'] }}">{{ $branding['site_url'] }}</a>
        </p>
    </div>
@endsection
