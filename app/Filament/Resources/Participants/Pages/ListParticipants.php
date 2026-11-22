<?php

namespace App\Filament\Resources\Participants\Pages;

use App\Filament\Resources\Participants\ParticipantResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListParticipants extends ListRecords
{
    protected static string $resource = ParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add participant'),
            Action::make('export')
                ->label('Export all responses (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('admin.export')),
        ];
    }
}
