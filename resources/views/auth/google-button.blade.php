@php
    $errors = $errors ?? session()->get('errors');
@endphp

<div class="mt-6 space-y-4">
    <div class="flex flex-col items-center gap-3 pb-2">
        <span class="inline-flex rounded-2xl bg-white p-3 shadow-sm ring-1 ring-gray-200/80">
            <img src="{{ asset('brand/blue-a.png') }}" alt="" width="56" height="56" class="h-14 w-14 object-contain">
        </span>
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            {{ __('English for Change') }}
        </p>
    </div>

    @if ($errors?->has('email'))
        <div class="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-600 dark:bg-danger-400/10 dark:text-danger-400" role="alert">
            {{ $errors->first('email') }}
        </div>
    @endif

    <x-filament::button
        tag="a"
        :href="route('auth.google.redirect')"
        color="primary"
        class="w-full justify-center"
        size="lg"
    >
        {{ __('Sign in with Google') }}
    </x-filament::button>
</div>
