@extends('selloff-notification::mail.layout')

@section('content')
    <div class="mailcontent">
        <p>Hello {{ $data['firstName'] ?? 'there' }},</p>
        <p style="color: #e10000; font-weight: bold;">{{ $data['subject'] ?? $subject }}</p>

        @if(!empty($data['rejectReason']))
            <p>Rejection reason(s):</p>
            <p style="color: #e10000;">{{ $data['rejectReason'] }}</p>
        @endif

        @if(!empty($data['productImg']))
            <p>
                <img src="{{ $data['productImg'] }}" alt="{{ $data['productTitle'] ?? 'Product' }}" style="max-width: 280px; margin: 12px 0;">
            </p>
        @endif

        @if(!empty($data['editUrl']))
            <p style="margin-top: 30px;">
                <span class="btn-primary">
                    <a href="{{ $data['editUrl'] }}" style="background-color: #ef340e; border-color: #ef340e;">Update your item</a>
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
