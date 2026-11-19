<?php

namespace App\Filament\Resources\Stages\Pages;

use App\Filament\Resources\Stages\StageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditStage extends EditRecord
{
    protected static string $resource = StageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn () => route('admin.preview', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
