@extends('selloff-notification::mail.layout')

@section('content')
    <div class="mailcontent">
        <p>Hello {{ $data['firstName'] ?? 'there' }},</p>
        <p>{{ $data['subject'] ?? $subject }}</p>

        @if(!empty($data['productImg']))
            <p style="text-align: center;">
                <img src="{{ $data['productImg'] }}" alt="{{ $data['productTitle'] ?? 'Product' }}" style="max-width: 280px; margin: 12px 0;">
            </p>
        @endif

        @if(!empty($data['productUrl']))
            <p style="text-align: center; margin-top: 30px;">
                <span class="btn-primary">
                    <a href="{{ $data['productUrl'] }}" style="background-color: #09b1ba; border-color: #09b1ba;">View your item on Selloff</a>
                </span>
            </p>
        @endif

        <p>
            Contact us via our
            <a href="{{ $branding['site_url'] }}/contact">Support page</a>
            if you have any issues or need support.
        </p>
        <p>
            Thank you,<br>
            {{ $branding['site_name'] }} Team<br>
            <a href="{{ $branding['site_url'] }}">{{ $branding['site_url'] }}</a>
        </p>
    </div>
@endsection
