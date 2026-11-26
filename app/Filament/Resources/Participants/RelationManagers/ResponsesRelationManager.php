<?php

namespace App\Filament\Resources\Participants\RelationManagers;

use App\Models\Response;
use App\Models\Stage;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** Everything one person wrote, in stage order then prompt order. */
class ResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->join('stages', 'stages.id', '=', 'responses.stage_id')
                ->join('prompts', 'prompts.id', '=', 'responses.prompt_id')
                ->select('responses.*')
                ->with(['stage', 'prompt'])
                ->orderBy('stages.sort')->orderBy('prompts.sort'))
            ->paginated([25, 50, 'all'])
            ->columns([
                TextColumn::make('stage.title')->label('Stage')->wrap(),
                TextColumn::make('prompt_body_snapshot')
                    ->label('Main question')
                    ->wrap()
                    ->color('gray')
                    ->description(fn (Response $record) => $record->prompt_details_snapshot)
                    ->extraAttributes(fn (Response $record) => ['dir' => $record->prompt->language->dir()]),
                TextColumn::make('body')
                    ->label('Response')
                    ->searchable()
                    ->wrap()
                    ->state(fn (Response $record) => $record->displayBody())
                    ->extraAttributes(fn (Response $record) => [
                        'dir' => $record->prompt->language->dir(),
                        'style' => 'white-space: pre-line',
                    ]),
                IconColumn::make('is_selected')->label('Selected')->boolean(),
                TextColumn::make('last_saved_at')->label('Last saved')->since(),
            ])
            ->filters([
                SelectFilter::make('stage_id')
                    ->label('Stage')
                    ->options(fn () => Stage::ordered()->pluck('title', 'id')),
                TernaryFilter::make('is_selected')
                    ->label('Selected')
                    ->trueLabel('Selected only')
                    ->falseLabel('Dropped only')
                    ->placeholder('All')
                    ->default(true),
            ]);
    }
}
