# AGENTS.md — AgileTracker

## Project overview
- **AgileTracker** — minimal project & task manager, Jira/Redmine alternative
- Stack: Laravel 13, FilamentPHP 3, Tailwind CSS 4, Vite 8, PHP 8.3+
- Key packages: `spatie/laravel-permission`, `spatie/laravel-activitylog`
- Database: SQLite (dev/test), MySQL/PostgreSQL (prod)
- License: MIT

## Setup commands
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm run build
```
Or use the bundled script: `composer run setup`

## Dev server
```bash
composer run dev
```
Starts: `artisan serve` + `queue:listen` + `pail` (logs) + `vite dev` concurrently.

## Build & test
```bash
# Lint PHP (PSR-12)
./vendor/bin/pint

# Run all tests (uses SQLite :memory:)
php artisan test

# Run a specific testsuite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Build frontend
npm run build
```

## Code style

### PHP
- `declare(strict_types=1)` in every file
- PSR-12 — enforced by `./vendor/bin/pint`
- Return type hints on all methods
- Parameter type hints on all methods
- No `mixed` where a specific type is known

### Laravel
- **Never edit existing migrations** — always create a new one
- Use Seeders for test data and default values
- Use Form Requests for validation (not inline in controllers)
- Use Policies for authorization
- Eloquent over raw SQL queries

### Filament
- Resources → `app/Filament/Resources/`
- Pages → `app/Filament/Pages/`
- Widgets → `app/Filament/Widgets/`
- Custom theme → `resources/css/filament/admin/theme.css`

### Frontend
- Tailwind CSS 4 via Vite
- No custom CSS unless unavoidable — prefer Tailwind utilities

## Project structure
```
app/
  Filament/
    Pages/          — custom Filament pages
    Resources/      — Filament resources (CRUD)
    Widgets/        — dashboard widgets
  Models/
    Comment.php     — comments on items
    Item.php        — tasks/issues (core entity)
    Label.php       — labels/tags
    User.php        — users with Spatie roles
  Providers/        — service providers
```

## Architecture notes
- `Item` is the core model — supports `type` (task/bug/feature), `status`, `priority`, `parent_id` (subtasks), assignee, reporter, due date, time tracking
- Soft deletes and activity logging on `Item` via Spatie
- Permission system via `spatie/laravel-permission` — roles defined through seeders
- Every model change is logged via `spatie/laravel-activitylog`

## Before committing
1. `./vendor/bin/pint` — must pass
2. `php artisan test` — must be green
3. If you changed frontend: `npm run build`
4. Migrations: always create new, never edit existing
