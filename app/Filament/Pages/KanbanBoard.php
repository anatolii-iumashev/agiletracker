<?php

namespace App\Filament\Pages;

use App\Models\Item;
use App\Models\Label;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\On;
use UnitEnum;

class KanbanBoard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Kanban';

    protected static UnitEnum|string|null $navigationGroup = 'Collections';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.kanban-board';

    public ?int $projectId = null;

    public array $columns = [
        'todo' => 'To Do',
        'in_progress' => 'In Progress',
        'review' => 'Review',
        'done' => 'Done',
    ];

    public function getItemsByStatus(): array
    {
        $query = Item::with(['assignee', 'labels'])
            ->orderBy('position');

        if ($this->projectId) {
            $query->where('parent_id', $this->projectId);
        }

        return $query
            ->get()
            ->groupBy(fn (Item $item) => $item->labels->pluck('name')->sort()->join(', ') ?: 'no-label')
            ->map(fn ($group) => $group->toArray())
            ->toArray();
    }

    /** Called via Alpine drag-drop → Livewire event */
    #[On('item-moved')]
    public function moveItem(int $itemId, string $newStatus, int $position): void
    {
        $item = Item::find($itemId);
        $item?->update(['position' => $position]);

        // Attach label matching the target column name
        $label = Label::firstOrCreate(
            ['name' => $newStatus],
            ['color' => '#6b7280']
        );
        $item?->labels()->syncWithoutDetaching([$label->id]);
    }
}
