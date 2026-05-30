# Development Guide

## Setup

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

## Code style

- `declare(strict_types=1)` in every PHP file
- PSR-12 — enforced by `./vendor/bin/pint`
- Return type hints on all methods
- Parameter type hints on all methods
- Curly braces for all control structures (even single-line)
- TitleCase for Enum keys

## Before committing

1. `./vendor/bin/pint --dirty --format agent`
2. `php artisan test`
3. If frontend changed: `npm run build`

## Running tests

```bash
php artisan test                  # all tests
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=testName
```

Tests use SQLite `:memory:`.

## Conventions

- **Never edit existing migrations** — always create a new one
- Use Filament Artisan commands (`php artisan make:filament-*`) to scaffold
- Use static `make()` to initialize Filament components
- Follow existing patterns in neighboring files
- `BelongsTo` fields use `Select::make('author_id')->relationship('author', 'name')`
