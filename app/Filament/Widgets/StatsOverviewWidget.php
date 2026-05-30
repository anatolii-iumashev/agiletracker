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
            Stat::make('Open Tasks', Item::tasks()->whereNotIn('status', ['done'])->count())
                ->description('Not yet done')
                ->color('warning')
                ->icon('heroicon-o-clipboard-document-list'),

            Stat::make('In Progress', Item::tasks()->where('status', 'in_progress')->count())
                ->color('primary')
                ->icon('heroicon-o-arrow-path'),

            Stat::make('Completed Today', Item::tasks()
                ->where('status', 'done')
                ->whereDate('updated_at', today())
                ->count())
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Overdue', Item::tasks()
                ->whereNotIn('status', ['done'])
                ->whereDate('due_date', '<', today())
                ->count())
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
