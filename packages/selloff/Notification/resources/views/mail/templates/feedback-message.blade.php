@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 24px; line-height: 28px; font-weight: bold;">
        {{ $data['title'] ?? 'Feedback details' }}
    </h1>
    <div class="mailcontent" style="text-align: left;">
        <p>Hello {{ $data['shopName'] ?? 'there' }},</p>
        <p>A buyer left new feedback on your shop. It is pending moderation and will appear publicly once approved.</p>

        <h2 style="margin: 24px 0 10px; font-size: 16px; font-weight: 600;">Buyer details</h2>
        <p style="color: #555;">
            @if(!empty($data['authorUsername']))
                <strong>Username:</strong> {{ $data['authorUsername'] }}<br>
            @endif
            <strong>Name:</strong> {{ $data['authorName'] ?? 'A buyer' }}<br>
            @if(!empty($data['authorPhone']))
                <strong>Phone:</strong> {{ $data['authorPhone'] }}<br>
            @endif
            @if(!empty($data['authorEmail']))
                <strong>Email:</strong> {{ $data['authorEmail'] }}<br>
            @endif
        </p>

        <h2 style="margin: 24px 0 10px; font-size: 16px; font-weight: 600;">Feedback</h2>
        <p style="color: #555;">
            <strong>Type:</strong> {{ $data['feedbackType'] ?? 'Feedback' }}
            @if(!empty($data['rating']))
                ({{ $data['rating'] }}/5 stars)
            @endif
            <br>
            <strong>Comment:</strong> {{ $data['feedbackContent'] ?? '' }}
        </p>

        @if(!empty($data['url']))
            <p style="text-align: center; margin-top: 40px;">
                <span class="btn-primary">
                    <a href="{{ $data['url'] }}">{{ $data['buttonText'] ?? 'View feedback' }}</a>
                </span>
            </p>
        @endif
    </div>
@endsection
