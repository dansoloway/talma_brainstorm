<?php

namespace App\Filament\Resources\Prompts\RelationManagers;

use App\Models\Response;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

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
            ->modifyQueryUsing(fn ($query) => $query->with('participant'))
            ->defaultSort('last_saved_at', 'desc')
            ->columns([
                TextColumn::make('participant.name')->label('Participant')->searchable()->sortable(),
                TextColumn::make('body')
                    ->label('Response')
                    ->searchable()
                    ->wrap()
                    ->state(fn (Response $record) => $record->displayBody())
                    ->extraAttributes(fn (Response $record) => [
                        'dir' => $this->getOwnerRecord()->language->dir(),
                        'style' => 'white-space: pre-line',
                    ]),
                TextColumn::make('words')
                    ->label('Words')
                    ->state(fn (Response $record) => count(preg_split('/\s+/u', trim($record->displayBody()), -1, PREG_SPLIT_NO_EMPTY))),
                IconColumn::make('is_selected')->label('Selected')->boolean(),
                TextColumn::make('last_saved_at')->label('Last saved')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_selected')
                    ->label('Selected')
                    ->trueLabel('Selected only')
                    ->falseLabel('Dropped only')
                    ->placeholder('All')
                    ->default(true),
            ]);
    }
}
