<?php

namespace App\Filament\Resources\Participants\Pages;

use App\Filament\Resources\Participants\ParticipantResource;
use App\Models\Participant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListParticipants extends ListRecords
{
    protected static string $resource = ParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add participant'),
            Action::make('bulkAdd')
                ->label('Bulk add')
                ->color('gray')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->modalHeading('Paste names, one per line')
                ->schema([
                    Textarea::make('names')->rows(12)->required()->extraInputAttributes(['dir' => 'auto']),
                ])
                ->action(function (array $data) {
                    $names = collect(preg_split('/\r\n|\r|\n/', $data['names']))->map(fn ($n) => trim($n))->filter();
                    $names->each(fn ($name) => Participant::create(['name' => $name]));
                    Notification::make()->success()->title("Added {$names->count()} participants")->send();
                }),
            Action::make('exportLinks')
                ->label('Download links (CSV)')
                ->color('gray')
                ->icon(Heroicon::OutlinedLink)
                ->action(fn () => response()->streamDownload(function () {
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, ['name', 'personal_link'], escape: '');
                    Participant::orderBy('name')->each(fn ($p) => fputcsv($out, [$p->name, $p->link()], escape: ''));
                    fclose($out);
                }, 'participant-links.csv')),
            Action::make('export')
                ->label('Export all responses (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('admin.export')),
        ];
    }
}
