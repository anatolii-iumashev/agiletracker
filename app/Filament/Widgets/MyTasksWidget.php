<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MyTasksWidget extends BaseWidget
{
    protected static ?string $heading = 'My Tasks';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Item::query()
                    ->where('assignee_id', auth()->id())
                    ->whereNotIn('status', ['done'])
                    ->orderBy('due_date')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'project',
                        'warning' => 'epic',
                        'success' => 'task',
                        'gray' => 'case',
                    ]),

                Tables\Columns\TextColumn::make('title')->limit(50),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'todo',
                        'warning' => 'in_progress',
                        'info' => 'review',
                    ]),

                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'gray' => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger' => 'critical',
                    ]),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->color(fn ($record) => $record->due_date?->isPast() ? 'danger' : null),
            ]);
    }
}
