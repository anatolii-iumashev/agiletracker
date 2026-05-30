<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Filament\Resources\ItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'mine' => Tab::make('Mine')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('assignee_id', auth()->id())),

            'overdue' => Tab::make('Overdue')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereDate('due_date', '<', today())),
        ];
    }
}
