<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\ItemResource;
use App\Models\Item;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class Inbox extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static UnitEnum|string|null $navigationGroup = 'My';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament-panels::pages.page';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Item::query()
                ->whereHas('to', fn (Builder $q) => $q->where('user_id', auth()->id()))
            )
            ->defaultSort('position')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'project',
                        'warning' => 'epic',
                        'success' => 'task',
                        'gray' => 'case',
                    ]),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->url(fn (Item $record): string => ItemResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'todo',
                        'warning' => 'in_progress',
                        'info' => 'review',
                        'success' => 'done',
                    ]),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger' => 'critical',
                    ]),

                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('From')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->due_date?->isPast() ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'task' => 'Task',
                        'epic' => 'Epic',
                        'project' => 'Project',
                        'case' => 'Case',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'todo' => 'To Do',
                        'in_progress' => 'In Progress',
                        'review' => 'Review',
                        'done' => 'Done',
                    ]),

                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->url(fn (Item $record): string => ItemResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
