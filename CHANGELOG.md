# CHANGELOG

Use date format for releases: `YYYY.MM.I` (e.g. `2026.05.1-alpha`).
Use flat lists for changes
If changes many - use categories (Added, Changed, Fixed, etc.)

## [2026.06.2-alpha] — 2026-06-01

TBD

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
