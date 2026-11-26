<x-layout :title="__('Stage :n', ['n' => $number]).' · '.$stage->title">
@php
    $stageUrl = function ($s) use ($preview, $viewingAs, $participant) {
        if ($viewingAs) {
            return route('admin.view-as.stage', [$participant, $s]);
        }

        return $preview ? route('admin.preview', $s) : route('participant.stage', $s);
    };
@endphp

@if ($viewingAs)
    <div class="preview-banner">
        {{ __('Viewing as :name (read-only)', ['name' => $participant->name]) }}
        <a href="{{ \App\Filament\Resources\Participants\ParticipantResource::getUrl('view', ['record' => $participant]) }}">{{ __('Leave') }}</a>
    </div>
@elseif ($preview)
    <div class="preview-banner">{{ __('Preview: this is what participants see. Nothing you do here is saved.') }}</div>
@endif

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ $viewingAs ? route('admin.view-as', $participant) : ($preview ? route('admin.preview', $stage) : route('participant.home')) }}" @if (! $preview && ! $viewingAs) data-nav @endif>
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
            @if ($participant)
                <span class="who">{{ $participant->name }}</span>
            @endif
            <nav class="stage-nav" aria-label="{{ __('Stages') }}">
                @foreach ($stages as $i => $s)
                    <a href="{{ $stageUrl($s) }}"
                       class="stage-dot {{ $s->id === $stage->id ? 'is-current' : '' }}"
                       data-nav
                       title="{{ $s->title }}"
                       @if ($s->id === $stage->id) aria-current="page" @endif>{{ $i + 1 }}</a>
                @endforeach
            </nav>
        </div>
    </div>
</header>

<main class="page">
    <p class="eyebrow">{{ __('Stage :n of :total', ['n' => $number, 'total' => $stages->count()]) }}</p>
    <h1>{{ $stage->title }}</h1>
    <p class="intro">
        {{ __('Choose between 1 and :max prompts and write a response to each. Your writing saves automatically as you type.', ['max' => $maxPerStage]) }}
    </p>

    <p class="counter" id="counter" aria-live="polite"></p>
    <div class="toast" id="toast" role="alert" hidden></div>

    <ol class="prompts">
        @forelse ($prompts as $prompt)
            @php($r = $responses->get($prompt->id))
            @php($selected = (bool) $r?->is_selected)
            @php($answers = $r ? $r->answersList() : [''])
            <li class="prompt {{ $selected ? 'is-selected' : '' }}" dir="{{ $prompt->language->dir() }}"
                data-prompt-id="{{ $prompt->id }}"
                data-selected="{{ $selected ? 1 : 0 }}"
                data-saved-at="{{ $r?->last_saved_at?->getTimestampMs() ?? 0 }}">
                <label class="pick">
                    <input type="checkbox" class="pick-box" @checked($selected) @disabled($preview)>
                    <x-prompt-text :prompt="$prompt" />
                </label>
                <div class="writer" @if (! $selected) hidden @endif>
                    <div class="answers">
                        @foreach ($answers as $index => $answer)
                            <div class="answer-block" data-answer-index="{{ $index }}">
                                @if (count($answers) > 1)
                                    <p class="answer-label">{{ __('Answer :n', ['n' => $index + 1]) }}</p>
                                @endif
                                <textarea class="answer"
                                          rows="5"
                                          dir="{{ $prompt->language->dir() }}"
                                          lang="{{ $prompt->language->value }}"
                                          aria-label="{{ __('Your response') }}"
                                          placeholder="{{ $prompt->language->value === 'he' ? 'כתבו כאן…' : 'Write here…' }}"
                                          @readonly($preview)>{{ $answer }}</textarea>
                                <span class="status" aria-live="polite"></span>
                            </div>
                        @endforeach
                    </div>
                    @if (! $preview)
                        <button type="button" class="btn btn-secondary add-answer" @disabled(count($answers) >= $maxAnswers)>
                            {{ __('Add Another Answer') }}
                        </button>
                    @endif
                </div>
                <p class="kept-note" @if ($selected || ! ($r?->hasText() ?? false)) hidden @endif>
                    {{ __('Your text is kept. Choose this prompt again to bring it back.') }}
                </p>
            </li>
        @empty
            <li class="empty">{{ __('No prompts on this stage yet.') }}</li>
        @endforelse
    </ol>

    <footer class="pager">
        @if ($previous)
            <a class="btn btn-secondary" data-nav href="{{ $stageUrl($previous) }}">{{ __('Back') }}</a>
        @elseif (! $preview)
            <a class="btn btn-secondary" data-nav href="{{ route('participant.home') }}">{{ __('Overview') }}</a>
        @else
            <span></span>
        @endif

        @if ($next)
            <a class="btn btn-primary" data-nav href="{{ $stageUrl($next) }}">{{ __('Next') }}</a>
        @elseif (! $preview)
            <form method="post" action="{{ route('participant.done') }}" data-done>
                @csrf
                <button class="btn btn-primary" type="submit">{{ __('Done') }}</button>
            </form>
        @endif
    </footer>
</main>

@php($stageConfig = [
    'preview' => $preview,
    'participantId' => $participant?->id,
    'max' => $maxPerStage,
    'maxAnswers' => $maxAnswers,
    'urls' => [
        'select' => url('/api/prompts/__ID__/select'),
        'deselect' => url('/api/prompts/__ID__/deselect'),
        'save' => url('/api/prompts/__ID__/response'),
        'ping' => url('/api/ping'),
    ],
    'text' => [
        'saving' => __('Saving…'),
        'saved' => __('Saved'),
        'retrying' => __('Not saved, retrying…'),
        'signedOut' => __('Signed out. Sign in with Google again. Your text is kept on this device.'),
        'previewNote' => __('Preview: not saved'),
        'counter' => __(':n of :max chosen'),
        'counterNone' => __('Choose at least one prompt.'),
        'atMax' => __('You can choose up to :max prompts. Drop one before picking another.', ['max' => $maxPerStage]),
        'network' => __('Could not reach the server. Please try again.'),
        'leaveUnsaved' => __('Some text has not reached the server yet. It is kept on this device and will be saved when you come back to this stage. Leave anyway?'),
        'addAnswer' => __('Add Another Answer'),
        'answerLabel' => __('Answer :n'),
        'answersAtMax' => __('You can add up to :max answers for one prompt.', ['max' => $maxAnswers]),
    ],
])
<script type="application/json" id="stage-config">{!! json_encode($stageConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
<script src="{{ asset('js/stage.js') }}?v={{ filemtime(public_path('js/stage.js')) }}"></script>
</x-layout>
