<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource\RelationManagers;
use App\Models\Item;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'Work';
    protected static ?int $navigationSort = 1;

    // ─── Global search ────────────────────────────────────────────────────────

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "[{$record->type}] {$record->title}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description'];
    }

    // ─── Form ─────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Overview')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->options([
                            'task'    => 'Task',
                            'epic'    => 'Epic',
                            'project' => 'Project',
                            'case'    => 'Case',
                        ])
                        ->required()
                        ->live()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),

                    Forms\Components\MarkdownEditor::make('description')
                        ->columnSpanFull()
                        ->toolbarButtons([
                            'bold', 'italic', 'strike',
                            'codeBlock', 'bulletList', 'orderedList',
                            'link', 'table',
                        ]),
                ]),

            Forms\Components\Section::make('Status & Priority')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'todo'        => 'To Do',
                            'in_progress' => 'In Progress',
                            'review'      => 'Review',
                            'done'        => 'Done',
                        ])
                        ->required()
                        ->default('todo'),

                    Forms\Components\Select::make('priority')
                        ->options([
                            'low'      => 'Low',
                            'medium'   => 'Medium',
                            'high'     => 'High',
                            'critical' => 'Critical',
                        ])
                        ->required()
                        ->default('medium'),
                ]),

            Forms\Components\Section::make('People')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('assignee_id')
                        ->label('Assignee')
                        ->relationship('assignee', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('reporter_id')
                        ->label('Reporter')
                        ->relationship('reporter', 'name')
                        ->searchable()
                        ->preload()
                        ->default(fn () => auth()->id())
                        ->nullable(),
                ]),

            Forms\Components\Section::make('Hierarchy')
                ->schema([
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent')
                        ->options(fn (Forms\Get $get) => Item::query()
                            ->whereIn('type', match ($get('type')) {
                                'task'  => ['epic', 'project'],
                                'epic'  => ['project'],
                                default => [],
                            })
                            ->pluck('title', 'id')
                        )
                        ->searchable()
                        ->nullable(),

                    Forms\Components\Select::make('labels')
                        ->label('Labels')
                        ->relationship('labels', 'name')
                        ->multiple()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required(),
                            Forms\Components\ColorPicker::make('color')->default('#6366f1'),
                        ]),
                ]),

            Forms\Components\Section::make('Scheduling')
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('due_date')->nullable(),

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

    // ─── Table ────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'project',
                        'warning' => 'epic',
                        'success' => 'task',
                        'gray'    => 'case',
                    ]),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'todo',
                        'warning' => 'in_progress',
                        'info'    => 'review',
                        'success' => 'done',
                    ]),

                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'gray'    => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger'  => 'critical',
                    ]),

                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->due_date?->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Parent')
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'task'    => 'Task',
                        'epic'    => 'Epic',
                        'project' => 'Project',
                        'case'    => 'Case',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'todo'        => 'To Do',
                        'in_progress' => 'In Progress',
                        'review'      => 'Review',
                        'done'        => 'Done',
                    ]),

                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low'      => 'Low',
                        'medium'   => 'Medium',
                        'high'     => 'High',
                        'critical' => 'Critical',
                    ]),

                Tables\Filters\SelectFilter::make('assignee')
                    ->relationship('assignee', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('convert')
                    ->label('Convert type')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Select::make('new_type')
                            ->label('Convert to')
                            ->options(fn ($record) => match ($record->type) {
                                'task'    => ['epic' => 'Epic', 'project' => 'Project'],
                                'epic'    => ['task' => 'Task', 'project' => 'Project'],
                                'project' => ['epic' => 'Epic'],
                                default   => [],
                            })
                            ->required(),
                    ])
                    ->action(fn ($record, array $data) => $record->convertTo($data['new_type'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('assign')
                    ->label('Assign to…')
                    ->form([
                        Forms\Components\Select::make('assignee_id')
                            ->label('Assignee')
                            ->options(User::pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(fn ($records, array $data) =>
                        $records->each->update(['assignee_id' => $data['assignee_id']])
                    ),

                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->reorderable('position')
            ->defaultSort('position');
    }

    // ─── Pages & Relations ────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit'   => Pages\EditItem::route('/{record}/edit'),
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
