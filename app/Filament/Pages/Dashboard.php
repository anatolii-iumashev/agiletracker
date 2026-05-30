<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\ItemResource;
use App\Filament\Widgets\FavoritesWidget;
use App\Filament\Widgets\InboxWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function getWidgets(): array
    {
        return [
            InboxWidget::class,
            FavoritesWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newItem')
                ->label('New Item')
                ->icon('heroicon-o-plus')
                ->url(ItemResource::getUrl('create')),
        ];
    }
}
