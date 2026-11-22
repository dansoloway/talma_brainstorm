<x-layout :title="__('Not ready yet')">
<main class="page page-center">
    <div class="brand-hero">
        <div class="brand-plate">
            <img class="brand-mark" src="{{ asset('brand/blue-a.png') }}" alt="" width="64" height="64">
            <img class="brand-wordmark" src="{{ asset('brand/wordmark.png') }}" alt="TALMA">
        </div>
        <span class="brand-tag">English for Change</span>
    </div>
    <h1>{{ __('Not ready yet') }}</h1>
    <p class="intro">{{ __('No stages have been set up yet. Please check back once an organizer has prepared the session.') }}</p>
</main>
</x-layout>
