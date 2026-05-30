# Development Guide

## Quick start from scratch

```bash
# 1. Create a Laravel project and copy these files into it
composer create-project laravel/laravel agile-tracker
cd agile-tracker

# 2. Install dependencies
composer require filament/filament:"^3.0" -W
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog

# 3. Publish Filament panel
php artisan filament:install --panels

# 4. Publish vendor configs
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish \
    --provider="Spatie\Activitylog\ActivitylogServiceProvider" \
    --tag="activitylog-migrations"

# 5. Configure .env (DB, APP_URL, etc.)
cp .env.example .env
php artisan key:generate

# 6. Run migrations + seed
php artisan migrate --seed

# 7. Start dev server
php artisan serve
```

Open **http://localhost:8000** and log in with:
- Email: `admin@example.com`
- Password: `password`

## Setup (existing project)

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
