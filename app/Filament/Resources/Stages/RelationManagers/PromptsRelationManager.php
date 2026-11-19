<?php

namespace App\Filament\Resources\Stages\RelationManagers;

use App\Enums\Language;
use App\Models\Prompt;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PromptsRelationManager extends RelationManager
{
    protected static string $relationship = 'prompts';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('Prompt')
                ->required()
                ->rows(3)
                ->extraInputAttributes(['dir' => 'auto'])
                ->columnSpanFull(),
            Select::make('language')
                ->options(Language::class)
                ->default(Language::English)
                ->required(),
            Toggle::make('is_hidden')
                ->label('Hidden from participants')
                ->inline(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Prompt $record) => Str::limit($record->body, 40))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->withCount('responses'))
            ->columns([
                TextColumn::make('number')->label('#')->state(fn ($rowLoop) => $rowLoop->iteration),
                TextColumn::make('body')
                    ->label('Prompt')
                    ->wrap()
                    ->extraAttributes(fn (Prompt $record) => ['dir' => $record->language->dir()]),
                TextColumn::make('language')->badge(),
                IconColumn::make('is_hidden')->label('Hidden')->boolean()
                    ->trueIcon(Heroicon::OutlinedEyeSlash)->falseIcon(Heroicon::OutlinedEye)
                    ->trueColor('warning')->falseColor('gray'),
                TextColumn::make('responses_count')->label('Responses'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add prompt'),
                $this->bulkAddAction(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalDescription(fn (Prompt $record) => $record->responses_count
                        ? "Careful: {$record->responses_count} response(s) already exist for this prompt. Their original prompt text is kept with each response, but fix typos only; don't change the meaning."
                        : null),
                Action::make('toggleHidden')
                    ->label(fn (Prompt $record) => $record->is_hidden ? 'Show' : 'Hide')
                    ->icon(fn (Prompt $record) => $record->is_hidden ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->action(fn (Prompt $record) => $record->update(['is_hidden' => ! $record->is_hidden])),
                DeleteAction::make()
                    ->hidden(fn (Prompt $record) => $record->responses_count > 0),
            ]);
    }

    private function bulkAddAction(): Action
    {
        return Action::make('bulkAdd')
            ->label('Bulk add')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->modalHeading('Paste prompts, one per line')
            ->schema([
                Textarea::make('lines')
                    ->label('Prompts')
                    ->rows(12)
                    ->required()
                    ->extraInputAttributes(['dir' => 'auto'])
                    ->helperText('Each non-empty line becomes one prompt, added after the existing ones.'),
                Select::make('language')
                    ->options(Language::class)
                    ->default(Language::English)
                    ->required(),
            ])
            ->action(function (array $data) {
                $lines = collect(preg_split('/\r\n|\r|\n/', $data['lines']))
                    ->map(fn ($l) => trim($l))
                    ->filter();

                foreach ($lines as $line) {
                    $this->getOwnerRecord()->prompts()->create([
                        'body' => $line,
                        'language' => $data['language'],
                    ]);
                }

                Notification::make()->success()->title("Added {$lines->count()} prompts")->send();
            });
    }
}
