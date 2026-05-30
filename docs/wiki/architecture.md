# Architecture

## Stack

- **PHP** 8.3+
- **Laravel** 13
- **FilamentPHP** 3 (admin panel)
- **Livewire** 4
- **Tailwind CSS** 4, Vite 8
- **SQLite** (dev/test), MySQL/PostgreSQL (prod)
- **spatie/laravel-permission** — roles and permissions
- **spatie/laravel-activitylog** — change logging

## Core models

| Model | Purpose |
|-------|---------|
| `Item` | Core entity — tasks, bugs, features. Supports `type`, `status`, `priority`, `parent_id` (subtasks), assignee, reporter, dates, time tracking. Soft deletes + activity log. |
| `User` | Spatie permission roles, favoritable items. |
| `Label` | Tags that can be attached to items. |
| `Comment` | Polymorphic comments on items. |
| `Favorite` | Polymorphic bookmarks for items and other models. |

## Directory structure

```
app/
  Filament/
    Pages/          — custom Filament pages
    Resources/      — Filament CRUD resources (ItemResource, UserResource, etc.)
    Widgets/        — dashboard widgets
  Models/
    Item.php
    User.php
    Label.php
    Comment.php
    Favorite.php
    Concerns/
      Favoritable.php  — polymorphic favorites trait
  Providers/          — service providers
database/
  migrations/
  seeders/
docs/
  rfc/    — design proposals
  wiki/   — knowledge base
```

## Patterns

- **Filament resources** for CRUD
- **Policies** for authorization (not inline checks)
- **Form Requests** for validation
- **Eloquent** over raw SQL
- **New migrations only** — never edit existing ones
- **Factories + Seeders** for test data
- **Soft deletes** on key models
