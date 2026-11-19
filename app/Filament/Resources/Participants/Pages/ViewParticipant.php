<?php

namespace App\Filament\Resources\Participants\Pages;

use App\Filament\Resources\Participants\ParticipantResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewParticipant extends ViewRecord
{
    protected static string $resource = ParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewAs')
                ->label('View as')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn () => route('admin.view-as', $this->record))
                ->openUrlInNewTab(),
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn () => route('admin.export', ['participant' => $this->record->id])),
            EditAction::make(),
        ];
    }
}
