# CHANGELOG

Use date format for releases: `YYYY.MM.I` (e.g. `2026.05.1-alpha`).
Use flat lists for changes
If changes many - use categories (Added, Changed, Fixed, etc.)

## [2026.11.1-alpha] — 2026-11-13

Dependency refresh: every Composer and npm package is on its latest release.

### Changed
- Pest `4.7` → `5.3`, `pestphp/pest-plugin-laravel` `4.1` → `5.0`, PHPUnit `12.5` → `13.4` (Pest 5 caps PHPUnit at `13.4.5`)
- `spatie/laravel-activitylog` `4.12` → `5.1`, `spatie/laravel-permission` `7.4` → `8.5`
- PHP requirement `^8.3` → `^8.4` (Pest 5 and activitylog 5 require it)
- npm: Vite `8.0` → `8.3`, Tailwind CSS `4.3.0` → `4.3.5`, `laravel-vite-plugin` `3.1` → `3.2`, `concurrently` `9` → `10`

### Added
- `config/activitylog.php` published from v5, plus the legacy `table_name`/`database_connection` keys the v4 migrations still read
- Migration `upgrade_activity_log_to_v5_schema` — adds `attribute_changes`, drops `batch_uuid`, moves recorded changes out of `properties`, and creates the consolidated v5 table on fresh installs
- `ActivityLoggingTest`/`ItemActivityLogOptionsTest` — tracked changes land in `attribute_changes`, untracked attributes are not logged, item log options stay dirty-only

### Fixed
- Item activity logging adapted to the v5 API: `Models\Concerns\LogsActivity`, `Support\LogOptions`, `dontLogEmptyChanges()`

## [2026.06.2-alpha] — 2026-06-01

Second alpha. Form layout restructuring, settings management, meta field, and UI polish.

### Added
- `ManageSettings` page for general settings management
- `meta` JSON field on Item model (form + infolist)

### Changed
- ItemResource slug changed from `i` to `items`
- ItemResource form reorganized into sections: General, Participants, Dates, Meta
- Items table: Labels column moved after Title
- Items table: Assignee column replaced with From (reporter) and To columns
- README and development guide refactored for clarity

### Removed
- `estimated_minutes` and `spent_minutes` fields from Item

## [2026.05.1-alpha] — 2026-05-30

First alpha release. Core project scaffolding, Filament admin panel with item management, Kanban board, and settings system.

- Laravel 13 project scaffolding with FilamentPHP 5, Livewire 4, Tailwind CSS 4
- `.agents/` protocol configuration: agents, skills, memories, MCP config, models
- AI agent skills: `code-review`, `laravel-best-practices`, `laravel-filament-dev`, `laravel-permission-development`, `rfc-writer`, `tailwindcss-development`
- Project documentation: `AGENTS.md`, `MARKETING.md`, `PRODUCT.md`, `README.md`, `ROADMAP.md`
- `Makefile` for common development tasks, `boost.json` — Laravel Boost configuration
- Database: `users`, `items` (core entity with type/status/priority/subtasks/assignee/time tracking), `comments`, `labels`, `item_label` (pivot), `activity_log`, Spatie Permissions tables
- `AdminPanelProvider` — Filament panel with navigation groups, theme, branding, global search, sidebar, topbar, user menu, theme switcher
- `ItemResource` — CRUD + View page (`/i/{id}`) with rich editor, comments, subtasks, labels, filters, sorting
- `LabelResource` — list/create/edit with name + color picker
- `UserResource` — list/create/edit with name, email, password, roles
- Kanban board: drag-and-drop via `mokhosh/filament-kanban`, `ItemStatus` enum, Spatie Sortable on `position` column (RFC: `docs/rfc/2026-05-30-kanban-board.md`)
- Settings system: central page via `filament/spatie-laravel-settings-plugin`, `GeneralSettings` class, admin-only access (RFC: `docs/rfc/2026-05-30-settings-system.md`)
- Spatie Permissions: `admin`/`manager`/`user` roles, policies for Item/Label/User, permission-gated navigation
- Spatie Activity Log: `created`/`updated`/`deleted`/`restored` events on Item
- `MyTasksWidget` — assigned items on dashboard
- Factories: `ItemFactory` (all field types), `UserFactory` (admin/manager/user)
- `DatabaseSeeder` — roles, permissions, admin user, sample data
- `composer run setup` / `composer run dev` — one-command setup and dev server
- `./vendor/bin/pint` (PSR-12), `php artisan test` (Unit + Feature), custom Filament stubs
