@props(['prompt', 'snapshotBody' => null, 'snapshotDetails' => null])
@php
    $body = $snapshotBody ?? $prompt->body;
    $details = $snapshotDetails ?? $prompt->details;
@endphp
<span {{ $attributes->merge(['class' => 'prompt-copy']) }} dir="{{ $prompt->language->dir() }}" lang="{{ $prompt->language->value }}">
    <span class="prompt-text">{{ $body }}</span>
    @if (filled($details))
        <span class="prompt-details">{{ $details }}</span>
    @endif
</span>
