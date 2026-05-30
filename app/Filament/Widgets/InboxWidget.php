<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\ItemResource;
use App\Models\Item;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class InboxWidget extends BaseWidget
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('openInbox')
                ->label('Open Inbox')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url('/inbox'),
        ];
    }

    protected static ?string $heading = 'Inbox';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Item::query()
                ->whereHas('to', fn (Builder $q) => $q->where('user_id', auth()->id()))
                ->orderBy('position')
                ->limit(3)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->sortable()
                    ->limit(60)
                    ->url(fn (Item $record): string => ItemResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('From')
                    ->sortable(),

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
