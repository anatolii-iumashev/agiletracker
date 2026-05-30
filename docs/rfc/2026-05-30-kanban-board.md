# RFC: Kanban Board for AgileTracker

## TL;DR

Add a drag-and-drop Kanban board to AgileTracker using the `mokhosh/filament-kanban` plugin, enabling visual workflow management of tasks and other item types with status columns.

## Context

### What?

Integrate the `mokhosh/filament-kanban` Filament plugin to provide a Kanban board page within the AgileTracker admin panel. Items (tasks, epics, cases) will be displayed as cards in columns grouped by status, with drag-and-drop support for changing statuses and reordering within columns.

### Why?

The current interface is purely list/table-based. A Kanban view is essential for visual workflow management — it lets users see work distribution at a glance, move items between statuses intuitively, and reorder priorities by dragging. This is a core feature expected by anyone evaluating AgileTracker as a Jira/Redmine alternative.

### How?

1. Install `mokhosh/filament-kanban` and `spatie/eloquent-sortable` via Composer.
2. Create a `ItemStatus` string-backed Enum with `IsKanbanStatus` trait — mapping to the existing `status` values: `todo`, `in_progress`, `review`, `done`.
3. Add `Spatie\EloquentSortable\Sortable` trait to the `Item` model (reusing the existing `position` column).
4. Create a `ItemsKanbanBoard` via `php artisan make:kanban`, registered as a Filament page.
5. Configure per-type kanban boards (or a single board with a type filter) — TBD during implementation.
6. Optionally create a new migration to add the `order_column` config for Spatie Sortable if the default `position` is insufficient.

## Components & Specifics

### Affected components

| Component | Change |
|---|---|
| `composer.json` | Add `mokhosh/filament-kanban` and `spatie/eloquent-sortable` |
| `app/Enums/ItemStatus.php` | **New** — string-backed enum with `IsKanbanStatus` trait |
| `app/Models/Item.php` | Add `Sortable` trait, configure `orderColumnName` to `position` |
| `app/Filament/Pages/ItemsKanbanBoard.php` | **New** — Kanban board page |
| `config/filament.php` | Register the Kanban page in the panel |
| `database/migrations/` | **New migration** — publish and configure `eloquent-sortable` config, set `ignore_timestamps` |
| `app/Models/Item.php` | Add `buildSortQuery()` to scope ordering per board context |

### Key architectural decisions

1. **Single Kanban board with type filter** — one `ItemsKanbanBoard` page with a dropdown/tabs to filter by `type` (tasks, epics, cases). Projects are excluded from Kanban (they're containers, not workflow items). Alternative: separate boards per type — simpler UX but more code.
2. **Status Enum** — `ItemStatus` enum with cases `Todo`, `InProgress`, `Review`, `Done`. Values match the existing DB strings. The `IsKanbanStatus` trait handles the mapping for the plugin automatically.
3. **Sorting** — reuse the existing `position` column. Spatie Sortable is configured with `'order_column_name' => 'position'`. Set `ignore_timestamps` to `true` to avoid all cards flashing on reorder.
4. **Edit modal** — enabled by default. Show a compact form: title, assignee, priority, due date. Full edit via existing ItemResource remains available.
5. **Board scoping** — `buildSortQuery()` limits the scope to items of the same type, so ordering is independent per type.

### Constraints

- The `Item` model's `status` column is `varchar`, already matches the enum approach.
- Kanban is read-only for relationships (labels, comments) — full editing is done via the existing Resource pages.
- Must not break existing list/table views.

### Out of scope

- Swimlanes (grouping by assignee/epic within columns) — v2.
- WIP limits per column.
- Board templates or custom columns per user.
- Multi-board per project (this is for a project manager view).
- Real-time collaboration (broadcasting).

### Dependencies

- `mokhosh/filament-kanban` ^2.0 — requires Filament 3.x+, compatible with v5.
- `spatie/eloquent-sortable` ^4.0 — for drag-and-drop reordering.

## Acceptance Criteria

- [ ] `composer require mokhosh/filament-kanban spatie/eloquent-sortable` installs without conflicts.
- [ ] `php artisan filament-kanban:install` publishes assets.
- [ ] `ItemStatus` enum exists with 4 cases: `Todo`, `InProgress`, `Review`, `Done`.
- [ ] `Item` model uses `Sortable` trait; `position` column is the order column.
- [ ] `ItemsKanbanBoard` page is registered in the Filament panel and accessible from navigation.
- [ ] Kanban board displays items grouped by status in 4 columns.
- [ ] Dragging a card from one status column to another updates the item's `status` in the database.
- [ ] Dragging a card within the same column updates the `position` (reordering).
- [ ] Edit modal opens on card click with title, assignee, priority, due date fields.
- [ ] Board filters by item type (task/epic/case) — each type has independent ordering.
- [ ] Existing ItemResource list/table views continue to work unchanged.
- [ ] `./vendor/bin/pint` passes.
- [ ] `php artisan test` passes (including new Kanban feature tests).

## Addenda

### Item Statuses Enum

```php
namespace App\Enums;

use Mokhosh\FilamentKanban\Concerns\IsKanbanStatus;

enum ItemStatus: string
{
    use IsKanbanStatus;

    case Todo       = 'todo';
    case InProgress = 'in_progress';
    case Review     = 'review';
    case Done       = 'done';

    public function getTitle(): string
    {
        return match ($this) {
            self::Todo       => 'To Do',
            self::InProgress => 'In Progress',
            self::Review     => 'Review',
            self::Done       => 'Done',
        };
    }
}
```

### Kanban Board (conceptual)

```php
namespace App\Filament\Pages;

use App\Enums\ItemStatus;
use App\Models\Item;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;

class ItemsKanbanBoard extends KanbanBoard
{
    protected static string $model = Item::class;
    protected static string $statusEnum = ItemStatus::class;
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static string $recordTitleAttribute = 'title';
    protected static string $recordStatusAttribute = 'status';
}
```

### Sortable config

```php
// config/eloquent-sortable.php
return [
    'order_column_name' => 'position',
    'ignore_timestamps' => true,
];
```
