<x-layout :title="__('Overview')">
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ route('participant.home') }}">
            <span class="brand-plate" aria-hidden="true">
                <img class="brand-mark" src="{{ asset('brand/blue-a.png') }}" alt="" width="28" height="28">
                <img class="brand-wordmark" src="{{ asset('brand/wordmark.png') }}" alt="">
            </span>
            <span class="brand-meta">
                <span class="visually-hidden">TALMA</span>
                <span class="brand-tag">English for Change</span>
            </span>
        </a>
        <div class="topbar-end">
            <span class="who">{{ $participant->name }}</span>
            <nav class="stage-nav" aria-label="{{ __('Stages') }}">
                @foreach ($stages as $i => $s)
                    <a href="{{ route('participant.stage', $s) }}"
                       class="stage-dot"
                       title="{{ $s->title }}">{{ $i + 1 }}</a>
                @endforeach
            </nav>
        </div>
    </div>
</header>

<main class="page">
    <p class="eyebrow">{{ __('Overview') }}</p>
    <h1>{{ __('What you will write') }}</h1>
    <p class="intro">
        {{ __('Here are all the prompts, grouped by stage. This page is read-only — start when you are ready.') }}
    </p>

    <div class="toc">
        @foreach ($stages as $i => $stage)
            <section class="toc-stage" aria-labelledby="toc-stage-{{ $stage->id }}">
                <header class="toc-stage-head">
                    <p class="eyebrow">{{ __('Stage :n of :total', ['n' => $i + 1, 'total' => $stages->count()]) }}</p>
                    <h2 id="toc-stage-{{ $stage->id }}">{{ $stage->title }}</h2>
                </header>

                <ol class="toc-prompts">
                    @forelse ($stage->prompts as $prompt)
                        <li class="toc-prompt" dir="{{ $prompt->language->dir() }}">
                            <span class="toc-num" aria-hidden="true">{{ $loop->iteration }}</span>
                            <x-prompt-text :prompt="$prompt" />
                        </li>
                    @empty
                        <li class="empty">{{ __('No prompts on this stage yet.') }}</li>
                    @endforelse
                </ol>
            </section>
        @endforeach
    </div>

    <footer class="pager">
        <span></span>
        <a class="btn btn-primary" href="{{ route('participant.stage', $firstStage) }}">{{ __('Start writing') }}</a>
    </footer>
</main>
</x-layout>
