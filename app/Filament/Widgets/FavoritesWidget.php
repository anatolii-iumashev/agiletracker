<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\ItemResource;
use App\Models\Favorite;
use App\Models\Item;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class FavoritesWidget extends BaseWidget
{
    protected static ?string $heading = 'Favorites';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Favorite::query()
                ->where('user_id', auth()->id())
                ->latest()
                ->limit(10),
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->limit(60)
                    ->url(fn (Favorite $record): ?string => match (true) {
                        $record->url !== null => $record->url,
                        $record->favoritable_type === Item::class => ItemResource::getUrl('view', ['record' => $record->favoritable_id]),
                        $record->favoritable_type === User::class => null,
                        default => null,
                    })
                    ->openUrlInNewTab(fn (Favorite $record): bool => $record->url !== null),

                Tables\Columns\TextColumn::make('type_badge')
                    ->label('Type')
                    ->badge()
                    ->state(fn (Favorite $record): string => match (true) {
                        $record->url !== null => 'Link',
                        $record->favoritable_type !== null => class_basename($record->favoritable_type),
                        default => 'Unknown',
                    })
                    ->colors([
                        'success' => 'Link',
                        'primary' => 'Item',
                        'warning' => 'User',
                    ]),

                Tables\Columns\TextColumn::make('details')
                    ->label('Details')
                    ->state(fn (Favorite $record): string => $record->url
                        ? parse_url($record->url, PHP_URL_HOST) ?? $record->url
                        : (string) ($record->favoritable?->title ?? $record->favoritable?->name ?? '—'))
                    ->url(fn (Favorite $record): ?string => match (true) {
                        $record->url !== null => $record->url,
                        $record->favoritable_type === Item::class => ItemResource::getUrl('view', ['record' => $record->favoritable_id]),
                        $record->favoritable_type === User::class => null,
                        default => null,
                    })
                    ->openUrlInNewTab(fn (Favorite $record): bool => $record->url !== null),
            ])
            ->paginated(false);
    }
}
