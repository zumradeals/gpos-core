@props(['title', 'body' => null])
<div {{ $attributes->merge(['class' => 'gp-empty']) }}>
    <strong>{{ $title }}</strong>
    @if($body)
        <span>{{ $body }}</span>
    @endif
    {{ $slot }}
</div>
