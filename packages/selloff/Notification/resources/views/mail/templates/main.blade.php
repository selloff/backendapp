@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 24px; line-height: 28px; font-weight: bold; text-align: center;">
        {{ $data['title'] ?? $data['mailTitle'] ?? $subject }}
    </h1>
    <div class="mailcontent" style="text-align: center;">
        @if(!empty($data['content']))
            <p>{!! $data['content'] !!}</p>
        @endif
        @if(!empty($data['url']))
            <p style="margin-top: 30px;">
                <span class="btn-primary">
                    <a href="{{ $data['url'] }}">{{ $data['buttonText'] ?? 'View details' }}</a>
                </span>
            </p>
        @endif
    </div>
@endsection
