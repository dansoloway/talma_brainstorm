<?php

namespace App\Filament\Widgets;

use App\Models\Participant;
use App\Models\Response;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Event-day glance: who is writing right now. Polls every 15s. */
class LiveActivity extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $activeNow = Response::where('last_saved_at', '>=', now()->subMinutes(5))->distinct()->count('participant_id');
        $lastSave = Response::max('last_saved_at');

        return [
            Stat::make('Participants', Participant::count()),
            Stat::make('Writing in the last 5 min', $activeNow),
            Stat::make('Pressed Done', Participant::whereNotNull('finished_at')->count()),
            Stat::make('Responses with text', Response::where('is_selected', true)->where('body', '!=', '')->count())
                ->description($lastSave ? 'Last save '.\Illuminate\Support\Carbon::parse($lastSave)->diffForHumans() : 'No saves yet'),
        ];
    }
}
