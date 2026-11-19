<?php

namespace App\Filament\Resources\Stages;

use App\Filament\Resources\Stages\Pages\CreateStage;
use App\Filament\Resources\Stages\Pages\EditStage;
use App\Filament\Resources\Stages\Pages\ListStages;
use App\Filament\Resources\Stages\RelationManagers\PromptsRelationManager;
use App\Models\Stage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StageResource extends Resource
{
    protected static ?string $model = Stage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Setup';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount(['prompts as visible_prompts_count' => fn ($q) => $q->where('is_hidden', false)])
                ->withCount('responses'))
            ->columns([
                TextColumn::make('number')
                    ->label('#')
                    ->state(fn ($rowLoop) => $rowLoop->iteration),
                TextColumn::make('title')->searchable()->weight('medium'),
                TextColumn::make('visible_prompts_count')
                    ->label('Prompts')
                    ->badge()
                    ->color(fn (int $state) => $state === 10 ? 'success' : 'warning')
                    ->tooltip(fn (int $state) => $state === 10 ? null : 'Each stage should have 10 visible prompts'),
                TextColumn::make('responses_count')->label('Responses'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Stage $record) => route('admin.preview', $record))
                    ->openUrlInNewTab(),
                EditAction::make()->label('Prompts'),
                DeleteAction::make()
                    ->hidden(fn (Stage $record) => $record->prompts()->exists())
                    ->tooltip('Only empty stages can be deleted'),
            ]);
    }

    public static function getRelations(): array
    {
        return [PromptsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStages::route('/'),
            'create' => CreateStage::route('/create'),
            'edit' => EditStage::route('/{record}/edit'),
        ];
    }
}
