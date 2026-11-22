<x-layout :title="__('Sign in')">
<main class="page page-center">
    <div class="brand-hero">
        <div class="brand-plate">
            <img class="brand-mark" src="{{ asset('brand/blue-a.png') }}" alt="" width="64" height="64">
            <img class="brand-wordmark" src="{{ asset('brand/wordmark.png') }}" alt="TALMA">
        </div>
        <span class="brand-tag">English for Change</span>
    </div>
    <h1>{{ __('Sign in to write') }}</h1>
    <p class="intro">{{ __('Use your @:domain Google account.', ['domain' => config('services.google.allowed_domain', 'talmaisrael.com')]) }}</p>

    @if ($errors->has('email'))
        <p class="intro" role="alert" style="color: #b42318;">{{ $errors->first('email') }}</p>
    @endif

    <p style="margin-top: 1.5rem;">
        <a class="btn btn-primary" href="{{ route('auth.google.participant') }}">{{ __('Sign in with Google') }}</a>
    </p>
</main>
</x-layout>
