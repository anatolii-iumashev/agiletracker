---
kind: agents
---

# AgileTracker — Agent Guidelines

## Project Overview
A minimal project & task manager (Jira/Redmine alternative) built with **Laravel 11 + FilamentPHP 3**.

## Stack & Conventions

### Backend
- **PHP 8.3+** with strict types (`declare(strict_types=1)`)
- **Laravel 11** — follow Laravel conventions: Eloquent, migrations, seeders, Form Requests
- **FilamentPHP 3** — all admin UI via Filament Resources, Pages, Widgets
- **Spatie Laravel Permission** — roles: `admin`, `project_manager`, `developer`, `viewer`
- **Spatie Laravel Activitylog** — log all model changes

### Frontend
- **Tailwind CSS** (via Filament)
- **Vite** for asset bundling
- Minimal custom JS — prefer Filament built-in components

### Database
- Migrations for schema changes (never edit old migrations — make new ones)
- Seeders for test data and default config
- Models in `app/Models/`

## Code Style
- Follow **PSR-12**
- Use **Laravel Pint** for auto-formatting: `./vendor/bin/pint`
- Type-hint everything (return types, parameter types)
- Use Eloquent relationships, not raw queries
- Filament resources use `->columns()`, `->filters()`, `->actions()` pattern

## Build & Test
- `php artisan migrate --seed` before starting work
- `./vendor/bin/phpunit` to run tests
- `npm run build` for production assets
- `composer check` for static analysis (if configured)

## Key Models
- **Item** — task/issue/bug (title, description, status, priority, assignee, labels)
- **Label** — tag/label for items
- **Comment** — comment on an item
- **User** — standard Laravel user with Spatie roles

## Filament Panel
- Panel ID: `admin`
- Path: `/admin`
- Default admin: `admin@example.com`
