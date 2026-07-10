@extends('selloff-notification::mail.layout')

@section('content')
    <h1 style="text-decoration: none; font-size: 24px; line-height: 28px; font-weight: bold; text-align: center;">
        {{ $data['title'] ?? $subject }}
    </h1>
    <div class="mailcontent" style="text-align: center;">
        <p>{!! $data['content'] ?? '' !!}</p>
    </div>
@endsection
