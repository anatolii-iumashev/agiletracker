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
                    ->orderBy('due_date')
                    ->limit(3)
            )
            ->columns([
                Tables\Columns\TextColumn::make('labels.name')
                    ->label('Labels')
                    ->badge()
                    ->color(fn ($record) => $record->labels->first()?->color ?? 'gray'),

                Tables\Columns\TextColumn::make('title')->limit(50),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->color(fn ($record) => $record->due_date?->isPast() ? 'danger' : null),
            ]);
    }
}
