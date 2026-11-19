<x-layout :title="__('Personal link needed')">
<main class="page page-center">
    <div class="brand-hero">
        <div class="brand-plate">
            <img class="brand-mark" src="{{ asset('brand/blue-a.png') }}" alt="" width="64" height="64">
            <img class="brand-wordmark" src="{{ asset('brand/wordmark.png') }}" alt="TALMA">
        </div>
        <span class="brand-tag">English for Change</span>
    </div>
    <h1>{{ __('Please use your personal link') }}</h1>
    <p class="intro">{{ __('Open the personal link you were given to start writing. If you lost it, ask an organizer.') }}</p>
</main>
</x-layout>
