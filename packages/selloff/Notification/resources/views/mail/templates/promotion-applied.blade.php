@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 24px; line-height: 28px; font-weight: bold;">
        {{ $data['headline'] ?? 'Promotion applied' }}
    </h1>
    <div class="mailcontent">
        <p>Hi {{ $data['firstName'] ?? 'there' }},</p>
        <p>{{ $data['summary'] ?? 'Your listing promotion is now active.' }}</p>

        @if(!empty($data['productImg']))
            <p style="text-align: center; margin: 24px 0;">
                <img src="{{ $data['productImg'] }}" alt="{{ $data['productTitle'] ?? 'Product' }}" style="max-width: 220px; border-radius: 8px;">
            </p>
        @endif

        <h2 style="margin: 24px 0 10px; font-size: 16px; font-weight: 600;">{{ $data['productTitle'] ?? 'Your listing' }}</h2>
        <p style="color: #555;">
            Plan: {{ $data['planLabel'] }}<br>
            Amount paid: {{ $data['amountPaid'] }}<br>
            Active until: {{ $data['expiresAt'] }}
        </p>

        @if(!empty($data['productUrl']))
            <p style="text-align: center; margin-top: 40px;">
                <span class="btn-primary">
                    <a href="{{ $data['productUrl'] }}">{{ $data['buttonText'] ?? 'View listing' }}</a>
                </span>
            </p>
        @endif
    </div>
@endsection
