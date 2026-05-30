# RFC: Label-Based Tagging System — Replace Type, Status, Priority

## TL;DR

Remove hardcoded `type`, `status`, and `priority` columns from `Item` and replace them with a flexible label-based tagging system. Any label can be assigned to any item — users define their own categories, workflows, and priorities as labels. No new columns needed: existing `Label` model (`name` + `color`) is sufficient.

## Context

### What?

- Drop columns `type`, `status`, `priority` from the `items` table
- Remove all hardcoded enum-like dropdowns for type/status/priority in Filament forms, tables, filters, and widgets
- Reuse the existing `Label` model + `item_label` pivot as the unified tagging mechanism — no new columns needed
- Labels are flat: just `name` + `color`. Default color is gray (`#6b7280`)
- Labels resource is already in the admin panel under Settings

### Why?

- **Rigid structure**: type (task/epic/project/case), status (todo/in_progress/review/done), priority (low/medium/high/critical) are hardcoded. Every team has its own workflow.
- **Type ≠ label**: an item must have a type AND can have labels — two competing categorization systems. Unify into one.
- **Simplicity**: fewer columns, simpler model, simpler form. The label system already exists.
- **Flexibility**: labels can represent anything — type, status, priority, sprint, component, etc.

### How?

1. **New migration** to drop `type`, `status`, `priority` columns from `items`
2. **No changes to `Label` model** — existing `name` + `color` is sufficient
3. **Update `Item` model**: remove type/status/priority from `$fillable`, `$casts`, scopes, `convertTo()`, activity log
4. **Rewire `ItemResource`**: replace type/status/priority selects with a single labels multi-select
5. **Update widgets**: filter by labels instead of hardcoded status values
6. **Update tests, factory, seeder**

## Components & Specifics

### Components affected

| Component | Change |
|-----------|--------|
| `database/migrations/` | **New migration** — drop `type`, `status`, `priority` from `items` |
| `app/Models/Item.php` | Remove type/status/priority from fillable, casts, scopes, `convertTo()`, activity log |
| `app/Models/Label.php` | No changes |
| `app/Filament/Resources/ItemResource.php` | Replace type/status/priority selects with label multi-select; update table columns, filters, infolist, global search |
| `app/Filament/Resources/ItemResource/Pages/ListItems.php` | Simplify tabs to All / Mine / Overdue |
| `app/Filament/Resources/ItemResource/RelationManagers/ChildrenRelationManager.php` | Remove type/status/priority from form & table |
| `app/Filament/Widgets/StatsOverviewWidget.php` | Rewrite stats — generic counts, no hardcoded status |
| `app/Filament/Widgets/MyTasksWidget.php` | Replace badge columns with labels; remove `whereNotIn('status', ['done'])` |
| `app/Filament/Widgets/InboxWidget.php` | Labels column after title |
| `app/Filament/Resources/LabelResource.php` | Default color → `#6b7280` |
| `database/factories/ItemFactory.php` | Remove type/status/priority defaults; remove `task()`, `project()`, `epic()`, `case()` states |
| `database/seeders/DatabaseSeeder.php` | Remove hardcoded type/status/priority; seed 12 default labels; attach labels to seeded items |
| `tests/Feature/ModelSmokeTest.php` | Update assertions |

### Key architectural decisions

1. **Flat labels, no groups** — labels have just `name` and `color`. Simple and flexible. No extra columns.
2. **No hardcoded label names** — the system ships with a seeder that creates sensible defaults, but all are editable/deletable by users.
3. **`scopeWithLabel()`** — a single generic scope: `Item::withLabel('Task')` queries by label `name`.
4. **Global search** title changes from `[$record->type] $record->title` to just `$record->title`.
5. **Activity log** tracks label changes via the existing `BelongsToMany` relationship.

### Constraints & edge cases

- **Existing data**: migration drops columns — existing type/status/priority data is **lost**. The seeder provides default labels for fresh installs.
- **Color coding**: badge colors in tables/widgets come from the label's `color` field. Default is gray `#6b7280`.

### Out of scope

- Label groups / mutual exclusivity (unnecessary complexity)
- Automatic workflow transitions
- Label-based permissions
- Migration helper to preserve existing type/status/priority

### Dependencies

- None — `Label` model and `item_label` pivot already exist.

## Acceptance Criteria

- [ ] Migration drops `type`, `status`, `priority` columns from `items` table
- [ ] `Item` model no longer references `type`, `status`, `priority` in fillable/casts/scopes/convertTo/activity log
- [ ] `ItemResource` form has label multi-select instead of type/status/priority selects
- [ ] `ItemResource` table shows labels as badges (with label color) instead of type/status/priority columns
- [ ] `ItemResource` filters use label-based filtering instead of type/status/priority SelectFilters
- [ ] `ItemResource` infolist shows labels instead of type/status/priority badges
- [ ] `ListItems` tabs are All / Mine / Overdue (no status-based tabs)
- [ ] `ChildrenRelationManager` form and table updated — no type/status/priority
- [ ] `StatsOverviewWidget` uses generic queries (no hardcoded status)
- [ ] `MyTasksWidget` uses labels, no `whereNotIn('status', ['done'])` filter
- [ ] `InboxWidget` has labels column after title
- [ ] `LabelResource` default color is `#6b7280`
- [ ] `ItemFactory` no longer defines type/status/priority; `task()`/`project()`/`epic()`/`case()` states removed
- [ ] `DatabaseSeeder` seeds default labels (Task, Bug, Feature, Epic, To Do, In Progress, Review, Done, Low, Medium, High, Critical)
- [ ] All existing tests pass
- [ ] `./vendor/bin/pint` passes
- [ ] `npm run build` succeeds

## Addenda

### Proposed migration

```php
Schema::table('items', function (Blueprint $table) {
    $table->dropColumn(['type', 'status', 'priority']);
});
```

### Default labels (seeder)

| Name | Color |
|------|-------|
| Task | #3b82f6 |
| Bug | #ef4444 |
| Feature | #8b5cf6 |
| Epic | #f59e0b |
| To Do | #6b7280 |
| In Progress | #f59e0b |
| Review | #3b82f6 |
| Done | #10b981 |
| Low | #6b7280 |
| Medium | #3b82f6 |
| High | #f59e0b |
| Critical | #ef4444 |
