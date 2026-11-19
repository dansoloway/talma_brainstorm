@props(['prompt'])
<span {{ $attributes->merge(['class' => 'prompt-text']) }} dir="{{ $prompt->language->dir() }}" lang="{{ $prompt->language->value }}">{{ $prompt->body }}</span>
