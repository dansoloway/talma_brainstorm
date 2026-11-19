<?php

namespace App\Filament\Resources\Prompts\Pages;

use App\Filament\Resources\Prompts\PromptResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPrompts extends ListRecords
{
    protected static string $resource = PromptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export all responses (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('admin.export')),
        ];
    }
}
