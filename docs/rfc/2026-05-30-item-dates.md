# RFC: Dates — Item model extension

## TL;DR

Add date fields to `Item`: `start_date`, `end_date`, `etd_date`, `eta_date` (plus existing `due_date` and `created_at`/`updated_at`). Display on the view page, edit form, and table.

---

## Context

### What?

Add four new date fields to the `Item` model:

- `start_date` — planned start date
- `end_date` — planned end date
- `etd_date` — Estimated Time of Departure (executor's estimate: when I'll start)
- `eta_date` — Estimated Time of Arrival (executor's estimate: when I'll finish)
- `due_date` — already exists (deadline)

### Why?

Currently, a task only has `due_date` (deadline). This is insufficient for planning: we need to know when a task starts (`start_date`), when it should finish (`end_date`), and have fields for executor's real-time estimates (`etd_date` / `eta_date`). Splitting "plan vs estimate" surfaces schedule deviations.

### How?

1. Migration — adds 4 `date nullable` columns to `items`
2. `Item` model — `$fillable` + `$casts` as `date`
3. `ItemResource` — fields in the form (DatePicker) and infolist (TextEntry)
4. Table — toggleable columns (hidden by default)

---

## Components & Specifics

### Migration

```
items:
  + start_date   date nullable
  + end_date     date nullable
  + etd_date     date nullable
  + eta_date     date nullable
```

### Item model

```php
$fillable += ['start_date', 'end_date', 'etd_date', 'eta_date'];
$casts    += ['start_date' => 'date', 'end_date' => 'date', 'etd_date' => 'date', 'eta_date' => 'date'];
```

### Filament

**Form (Dates section):**
- `DatePicker::make('start_date')`
- `DatePicker::make('end_date')`
- `DatePicker::make('due_date')`
- `DatePicker::make('etd_date')->label('ETD')`
- `DatePicker::make('eta_date')->label('ETA')`

**Form (Time section):**
- `TextInput::make('estimated_minutes')`
- `TextInput::make('spent_minutes')`

**Infolist (Dates section):**
- Only **filled** dates are shown — null dates are hidden via `->visible()`
- `created_at` and `updated_at` are always visible (never null)
- Format: `M j, Y` for dates, `M j, Y H:i` for timestamps

**View vs Edit behavior:**
- On the **View** page: only non-null dates are rendered. Unfilled dates are not shown — keeps the UI clean and focused.
- On the **Edit** page: all date fields are available as DatePickers — user can fill in any missing dates.

**Table:**
- Columns `start_date`, `end_date`, `etd_date`, `eta_date` with `->toggleable(isToggledHiddenByDefault: true)`

### Out of scope

- Validation `etd ≤ eta` (can be added later)
- Calendar integration
- Automatic date calculation

---

## Acceptance Criteria

- [x] Migration: fields `start_date`, `end_date`, `etd_date`, `eta_date` added to `items`
- [x] Model `Item`: `$fillable` and `$casts` include new date fields
- [x] `ItemResource`: form — dates in «Dates» section, time in «Time» section
- [x] `ItemResource`: infolist — only filled dates visible in «Dates» section, nulls hidden
- [x] `ItemResource`: `created_at` / `updated_at` always visible in «Dates» section
- [x] `ItemResource`: table includes toggleable columns for new fields
- [x] `./vendor/bin/pint` — passes
- [x] `php artisan test` — passes

---

## Addenda

### Date semantics

| Field | Meaning | Example |
|------|---------|---------|
| `start_date` | Planned start date | "We start June 15" |
| `end_date` | Planned end date | "Must finish by June 30" |
| `etd_date` | Estimated Time of Departure — executor's guess: when I'll actually start | "I think I'll start on the 17th" |
| `eta_date` | Estimated Time of Arrival — executor's guess: when I'll actually finish | "I think I'll finish by the 28th" |
| `due_date` | Hard deadline | "Absolute deadline: July 1" |
