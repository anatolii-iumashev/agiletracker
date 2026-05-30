<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource\RelationManagers;
use App\Models\Item;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-queue-list';

    protected static UnitEnum|string|null $navigationGroup = 'Collections';

    protected static ?string $slug = 'i';

    protected static ?int $navigationSort = 1;

    // ─── Global search ────────────────────────────────────────────────────────

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->title;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description'];
    }

    // ─── Form ─────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Overview')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\MarkdownEditor::make('description')
                        ->columnSpanFull()
                        ->toolbarButtons([
                            'bold', 'italic', 'strike',
                            'codeBlock', 'bulletList', 'orderedList',
                            'link', 'table',
                        ]),
                ]),

            Section::make('Labels')
                ->schema([
                    Forms\Components\Select::make('labels')
                        ->label('Labels')
                        ->relationship('labels', 'name')
                        ->multiple()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required(),
                            Forms\Components\ColorPicker::make('color')->default('#6b7280'),
                        ]),
                ]),

            Section::make('Participants')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('assignee_id')
                        ->label('Responsible')
                        ->relationship('assignee', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('reporter_id')
                        ->label('From')
                        ->relationship('reporter', 'name')
                        ->searchable()
                        ->preload()
                        ->default(fn () => auth()->id())
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\Select::make('to')
                        ->label('To')
                        ->relationship('to', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('cc')
                        ->label('CC')
                        ->relationship('cc', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                ]),

            Section::make('Hierarchy')
                ->schema([
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent')
                        ->relationship('parent', 'title')
                        ->searchable()
                        ->nullable(),
                ]),

            Section::make('Dates')
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('start_date')->nullable(),
                    Forms\Components\DatePicker::make('end_date')->nullable(),
                    Forms\Components\DatePicker::make('due_date')->nullable(),

                    Forms\Components\DatePicker::make('etd_date')
                        ->label('ETD')
                        ->nullable(),
                    Forms\Components\DatePicker::make('eta_date')
                        ->label('ETA')
                        ->nullable(),
                ]),

            Section::make('Time')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('estimated_minutes')
                        ->label('Estimate (minutes)')
                        ->numeric()
                        ->nullable(),

                    Forms\Components\TextInput::make('spent_minutes')
                        ->label('Spent (minutes)')
                        ->numeric()
                        ->default(0),
                ]),
        ]);
    }

    // ─── Infolist ─────────────────────────────────────────────────────────────

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Infolists\Components\TextEntry::make('description')
                    ->hiddenLabel()
                    ->markdown()
                    ->default('No description.')
                    ->columnSpanFull(),

                Infolists\Components\TextEntry::make('labels.name')
                    ->label('Labels')
                    ->badge()
                    ->color(fn ($record) => $record->labels->first()?->color ?? 'gray')
                    ->default('—'),

                Section::make('Participants')
                    ->schema([
                        Infolists\Components\TextEntry::make('reporter.name')
                            ->label('From')
                            ->default('—'),

                        Infolists\Components\TextEntry::make('assignee.name')
                            ->label('Responsible')
                            ->default('—'),

                        Infolists\Components\TextEntry::make('to_names')
                            ->label('To')
                            ->state(fn (Item $record): string => $record->to->pluck('name')->join(', '))
                            ->visible(fn (Item $record): bool => $record->to->isNotEmpty()),

                        Infolists\Components\TextEntry::make('cc_names')
                            ->label('CC')
                            ->state(fn (Item $record): string => $record->cc->pluck('name')->join(', '))
                            ->visible(fn (Item $record): bool => $record->cc->isNotEmpty()),
                    ]),

                Infolists\Components\TextEntry::make('parent.title')
                    ->label('Parent')
                    ->url(fn (?Item $record): ?string => $record?->parent
                        ? ItemResource::getUrl('view', ['record' => $record->parent])
                        : null
                    )
                    ->default('—'),

                Section::make('Dates')
                    ->schema([
                        Infolists\Components\TextEntry::make('due_date')
                            ->label('Due date')
                            ->state(fn ($record) => $record->due_date?->format('M j, Y') ?? '—')
                            ->visible(fn ($record) => $record->due_date !== null),

                        Infolists\Components\TextEntry::make('start_date')
                            ->label('Start date')
                            ->state(fn ($record) => $record->start_date?->format('M j, Y') ?? '—')
                            ->visible(fn ($record) => $record->start_date !== null),

                        Infolists\Components\TextEntry::make('end_date')
                            ->label('End date')
                            ->state(fn ($record) => $record->end_date?->format('M j, Y') ?? '—')
                            ->visible(fn ($record) => $record->end_date !== null),

                        Infolists\Components\TextEntry::make('etd_date')
                            ->label('ETD')
                            ->state(fn ($record) => $record->etd_date?->format('M j, Y') ?? '—')
                            ->visible(fn ($record) => $record->etd_date !== null),

                        Infolists\Components\TextEntry::make('eta_date')
                            ->label('ETA')
                            ->state(fn ($record) => $record->eta_date?->format('M j, Y') ?? '—')
                            ->visible(fn ($record) => $record->eta_date !== null),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M j, Y H:i'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime('M j, Y H:i'),
                    ])
                    ->columns(2),

                Infolists\Components\TextEntry::make('estimated_minutes')
                    ->label('Est. (min)')
                    ->default('—'),

                Infolists\Components\TextEntry::make('spent_minutes')
                    ->label('Spent (min)')
                    ->default('—'),
            ]);
    }

    // ─── Table ────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('labels.name')
                    ->label('Labels')
                    ->badge()
                    ->color(fn ($record) => $record->labels->first()?->color ?? 'gray'),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->url(fn (Item $record): string => ItemResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->due_date?->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('etd_date')
                    ->label('ETD')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('eta_date')
                    ->label('ETA')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Parent')
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('labels')
                    ->relationship('labels', 'name')
                    ->label('Label'),

                Tables\Filters\SelectFilter::make('assignee')
                    ->relationship('assignee', 'name'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('assign')
                    ->label('Assign to…')
                    ->form([
                        Forms\Components\Select::make('assignee_id')
                            ->label('Assignee')
                            ->options(User::pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(fn ($records, array $data) => $records->each->update(['assignee_id' => $data['assignee_id']])
                    ),

                DeleteBulkAction::make(),
            ])
            ->reorderable('position')
            ->defaultSort('position');
    }

    // ─── Pages & Relations ────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'view' => Pages\ViewItem::route('/{record}'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CommentsRelationManager::class,
            RelationManagers\ChildrenRelationManager::class,
        ];
    }
}
