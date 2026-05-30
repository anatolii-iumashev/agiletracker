<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Items', Item::count())
                ->color('primary')
                ->icon('heroicon-o-clipboard-document-list'),

            Stat::make('Assigned to me', Item::where('assignee_id', auth()->id())->count())
                ->color('warning')
                ->icon('heroicon-o-arrow-path'),

            Stat::make('Updated Today', Item::whereDate('updated_at', today())->count())
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Overdue', Item::whereDate('due_date', '<', today())->count())
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
