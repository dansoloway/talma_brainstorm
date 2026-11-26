<?php

namespace App\Filament\Resources\Prompts;

use App\Enums\Language;
use App\Filament\Resources\Prompts\Pages\ListPrompts;
use App\Filament\Resources\Prompts\Pages\ViewPrompt;
use App\Filament\Resources\Prompts\RelationManagers\ResponsesRelationManager;
use App\Models\Prompt;
use App\Models\Stage;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Read-only view of prompts with their responses. Prompts are edited inside Stages. */
class PromptResource extends Resource
{
    protected static ?string $model = Prompt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Responses by prompt';

    protected static string|\UnitEnum|null $navigationGroup = 'Results';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('body')
                ->label('Main question')
                ->columnSpanFull()
                ->extraAttributes(fn (Prompt $record) => ['dir' => $record->language->dir()]),
            TextEntry::make('details')
                ->label('Supporting details')
                ->placeholder('—')
                ->columnSpanFull()
                ->extraAttributes(fn (Prompt $record) => ['dir' => $record->language->dir()]),
            TextEntry::make('stage.title')->label('Stage'),
            TextEntry::make('language')->badge(),
            TextEntry::make('chosen')
                ->label('Chosen by')
                ->state(fn (Prompt $record) => $record->responses()->where('is_selected', true)->count().' people'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->join('stages', 'stages.id', '=', 'prompts.stage_id')
                ->select('prompts.*')
                ->with('stage')
                ->withCount(['responses as chosen_count' => fn ($q) => $q->where('is_selected', true)]))
            ->defaultSort(fn (Builder $query) => $query->orderBy('stages.sort')->orderBy('prompts.sort'))
            ->columns([
                TextColumn::make('stage.title')->label('Stage')->wrap(),
                TextColumn::make('body')
                    ->label('Main question')
                    ->searchable()
                    ->wrap()
                    ->limit(160)
                    ->description(fn (Prompt $record) => $record->details ? \Illuminate\Support\Str::limit($record->details, 80) : null)
                    ->extraAttributes(fn (Prompt $record) => ['dir' => $record->language->dir()]),
                TextColumn::make('language')->badge(),
                TextColumn::make('chosen_count')->label('Chosen by')->sortable(),
                TextColumn::make('is_hidden')
                    ->label('')
                    ->formatStateUsing(fn ($state) => $state ? 'hidden' : null)
                    ->badge()->color('warning'),
            ])
            ->filters([
                SelectFilter::make('stage_id')
                    ->label('Stage')
                    ->options(fn () => Stage::ordered()->pluck('title', 'id')),
                SelectFilter::make('language')->options(Language::class),
            ])
            ->recordActions([ViewAction::make()->label('Read responses')])
            ->recordUrl(fn (Prompt $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [ResponsesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrompts::route('/'),
            'view' => ViewPrompt::route('/{record}'),
        ];
    }
}
