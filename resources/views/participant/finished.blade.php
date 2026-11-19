<x-layout :title="__('Thank you')">
<main class="page page-center">
    <div class="brand-hero">
        <div class="brand-plate">
            <img class="brand-mark" src="{{ asset('brand/blue-a.png') }}" alt="" width="64" height="64">
            <img class="brand-wordmark" src="{{ asset('brand/wordmark-logo.png') }}" alt="TALMA · English for Change">
        </div>
    </div>
    <h1>{{ __('Thank you, :name!', ['name' => $participant->name]) }}</h1>
    <p class="intro">{{ __('Your responses are saved. You can still go back and change anything until the session ends.') }}</p>
    @if ($lastStage)
        <a class="btn btn-secondary" href="{{ route('participant.stage', $lastStage) }}">{{ __('Back to my responses') }}</a>
    @endif
</main>
</x-layout>
