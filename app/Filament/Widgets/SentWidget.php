<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\ItemResource;
use App\Models\Item;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class SentWidget extends BaseWidget
{
    protected static ?string $heading = 'Sent';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Item::query()
                ->where('reporter_id', auth()->id())
                ->orderBy('position')
                ->limit(3)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->sortable()
                    ->limit(60)
                    ->url(fn (Item $record): string => ItemResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('To')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cc.name')
                    ->label('CC')
                    ->badge()
                    ->state(fn (Item $record) => $record->cc->pluck('name')->join(', ')),

                Tables\Columns\TextColumn::make('labels.name')
                    ->label('Labels')
                    ->badge()
                    ->color(fn ($record) => $record->labels->first()?->color ?? 'gray'),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->due_date?->isPast() ? 'danger' : null),
            ])
            ->paginated(false)
            ->poll('30s');
    }
}
