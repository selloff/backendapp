{{ $data['title'] ?? $subject }}

{{ strip_tags((string) ($data['content'] ?? '')) }}

@if(!empty($data['url']))
{{ $data['url'] }}
@endif

{{ $branding['site_name'] }} — {{ $branding['site_url'] }}
