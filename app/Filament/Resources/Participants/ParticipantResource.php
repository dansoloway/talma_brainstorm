<?php

namespace App\Filament\Resources\Participants;

use App\Filament\Resources\Participants\Pages\EditParticipant;
use App\Filament\Resources\Participants\Pages\ListParticipants;
use App\Filament\Resources\Participants\Pages\ViewParticipant;
use App\Filament\Resources\Participants\RelationManagers\ResponsesRelationManager;
use App\Models\Participant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ParticipantResource extends Resource
{
    protected static ?string $model = Participant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Results';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->maxLength(255),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('email')->placeholder('—'),
            TextEntry::make('finished_at')->label('Pressed Done')->dateTime()->placeholder('Not yet'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount(['responses as answered_count' => fn ($r) => $r->where('is_selected', true)->where('body', '!=', '')])
                ->withMax('responses', 'last_saved_at'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                TextColumn::make('email')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('answered_count')->label('Prompts answered')->sortable(),
                TextColumn::make('responses_max_last_saved_at')->label('Last typed')->since()->sortable()->placeholder('Never'),
                TextColumn::make('finished_at')->label('Done')->dateTime('H:i')->sortable()->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('finished')
                    ->label('Pressed Done')
                    ->nullable()
                    ->attribute('finished_at'),
            ])
            ->recordActions([
                ViewAction::make()->label('Read'),
                Action::make('viewAs')
                    ->label('View as')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Participant $record) => route('admin.view-as', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Participant $record) => $record->responses()->exists()),
            ]);
    }

    public static function getRelations(): array
    {
        return [ResponsesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParticipants::route('/'),
            'view' => ViewParticipant::route('/{record}'),
            'edit' => EditParticipant::route('/{record}/edit'),
        ];
    }
}
