# RFC: Plugin System — WordPress/FreeScout-style Events & Plugins

## TL;DR

Add a lightweight plugin system: plugins live in `plugins/` (sibling to `app/`), are activated via `config/plugins.php` + admin UI override, and extend the app through two complementary hook systems — `tormjens/eventy` (data/logic) and Filament Render Hooks (Blade/UI) — no marketplace, no strict module structure.

## Context

### What?

A plugin system that allows third-party code to hook into AgileTracker without modifying core files. Each plugin is a folder in `plugins/` with a `plugin.json` descriptor and free-form `src/` code. The system consists of three parts:

1. **Hook engines** — two complementary systems:
   - `tormjens/eventy` — WordPress-style actions/filters for data and logic hooks.
   - Filament Render Hooks (`FilamentView::registerRenderHook()`) — native Blade injection at 70+ layout points (sidebar, topbar, page header, table toolbar, etc.).
2. **Plugin loader** — discovers, validates, and bootstraps active plugins.
3. **Admin UI** (Filament page) — list plugins, toggle activation, view metadata.

### Why?

AgileTracker's core must remain small. Custom features (e.g., time tracking, custom dashboards, integrations with external services) should live in plugins rather than bloating the main codebase. A hook-based architecture means plugins can:
- Modify queries, add UI sections, inject Blade content, alter data — without touching core.
- Register their own routes, migrations, views, and commands when needed.
- Be distributed as standalone ZIP archives for local installation.

### How?

1. Install `tormjens/eventy` via Composer.
2. Create `app/Plugins/PluginManager.php` — singleton that scans `plugins/*/plugin.json`, resolves active state from config + DB, and registers ServiceProviders.
3. Create `app/Plugins/PluginPage.php` — Filament page listing plugins with activation toggles.
4. Add `config/plugins.php` — default active plugins list.
5. Create a migration for the `plugins` table (stores activation overrides from admin UI).
6. Create `plugins/_example/` as a reference/starter plugin.

## Components & Specifics

### Affected components

| Component | Change |
|---|---|
| `composer.json` | Add `tormjens/eventy` |
| `config/plugins.php` | **New** — default config: `'active' => []` |
| `app/Plugins/PluginManager.php` | **New** — discovery, validation, bootstrapping |
| `app/Plugins/Plugin.php` | **New** — value object: name, alias, version, providers, etc. |
| `app/Filament/Pages/PluginPage.php` | **New** — admin UI for plugin management |
| `database/migrations/` | **New migration** — `create_plugins_table` |
| `bootstrap/providers.php` | Register `PluginManager` in container |
| `plugins/` | **New** — root directory for all plugins |
| `plugins/_example/` | **New** — reference/starter plugin |
| `plugins/hello-world/` | **New** — working example with both hook systems |

### Plugin metadata (`plugin.json`)

```json
{
    "name": "My Plugin",
    "alias": "my-plugin",
    "version": "1.0.0",
    "description": "Adds time tracking to items",
    "providers": [
        "Plugins\\MyPlugin\\MyPluginServiceProvider"
    ],
    "requires": {
        "php": ">=8.3"
    }
}
```

- `name` — display name, can contain spaces, changeable.
- `alias` — unique slug, lowercase, kebab-case. Never changes. Used as ID everywhere.
- `providers` — array of FQCNs to register via Laravel's service container.
- `requires` — minimum PHP version (reserved for future dependency checks).

### Directory structure

```
plugins/
├── _example/              # reference plugin (always available, not activatable)
│   ├── plugin.json
│   └── src/
│       └── ExampleServiceProvider.php
├── hello-world/            # working example using both hook systems
│   ├── plugin.json
│   └── src/
│       ├── HelloWorldServiceProvider.php
│       └── resources/
│           └── views/
│               └── hello-message.blade.php
```

### `hello-world` — working example plugin

Bundled example that demonstrates both hook systems with a real, functional plugin:

**`plugin.json`:**
```json
{
    "name": "Hello World",
    "alias": "hello-world",
    "version": "1.0.0",
    "description": "Demonstrates hook systems: adds a greeting to the user menu and a banner to the dashboard",
    "providers": [
        "Plugins\\HelloWorld\\HelloWorldServiceProvider"
    ]
}
```

**`HelloWorldServiceProvider.php`:**
```php
<?php

declare(strict_types=1);

namespace Plugins\HelloWorld;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;

class HelloWorldServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 1. Filament Render Hook — injects a banner above dashboard content
        FilamentView::registerRenderHook(
            PanelsRenderHook::CONTENT_BEFORE,
            fn (): string => view('plugins::hello-world.hello-message')->render(),
            scopes: \App\Filament\Pages\Dashboard::class,
        );

        // 2. Eventy Filter — modifies navigation items
        \Eventy::addFilter('navigation.items', function (array $items) {
            // Plugin could add/remove/reorder items
            return $items;
        }, 20, 1);
    }
}
```

**`resources/views/hello-message.blade.php`:**
```blade
<div class="px-4 py-3 mb-4 bg-primary-50 border border-primary-200 rounded-lg">
    <p class="text-sm text-primary-700 font-medium">
        Hello from <strong>Hello World</strong> plugin!
        This banner is injected via Filament Render Hook.
    </p>
</div>
```

**What it demonstrates:**
- Filament Render Hook: injects Blade into dashboard via `CONTENT_BEFORE`, scoped to `Dashboard::class`
- Eventy Filter: subscribes to `navigation.items` (ready for extension)
- Auto-loaded view namespace: `plugins::hello-world` → `resources/views/`
- Minimal structure: one provider, one view, one `plugin.json`

### PluginManager — lifecycle

1. **Discovery** (`discover()`): scans `plugins/*/plugin.json`, validates JSON, builds `Plugin` objects. Runs early in boot, cached.
2. **Activation resolution**: merges `config('plugins.active')` with overrides from `plugins` DB table (admin UI wins).
3. **Registration** (`register()`): for each active plugin, registers its `providers` via `app()->register()`.
4. **Hook registration**: each provider's `boot()` method calls `Eventy::addAction()` / `Eventy::addFilter()`.

### Hook system (`tormjens/eventy`)

Eventy provides WordPress-style actions and filters for data/logic extension points:

```php
// Core declares a hook point:
$items = Eventy::filter('items.table.query', $items, $filters);

// Plugin subscribes:
Eventy::addFilter('items.table.query', function ($query, $filters) {
    return $query->where('priority', 'critical');
}, 20, 2);

// Core declares an action in Blade:
@action('item.show.sidebar', $item)

// Plugin outputs content:
Eventy::addAction('item.show.sidebar', function ($item) {
    echo view('plugins::my-plugin.sidebar', compact('item'));
}, 20, 1);
```

### Hook system (Filament Render Hooks)

Filament natively provides **70+ render hooks** for Blade injection at specific layout positions. Plugins register them via `FilamentView::registerRenderHook()`:

```php
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

// Inject content after the page header
FilamentView::registerRenderHook(
    PanelsRenderHook::PAGE_HEADER_WIDGETS_AFTER,
    fn (): View => view('plugins::my-plugin.header-widget'),
);

// Scoped to a specific resource page
FilamentView::registerRenderHook(
    PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER,
    fn (): View => view('plugins::my-plugin.table-footer'),
    scopes: \App\Filament\Resources\ItemResource\Pages\ListItems::class,
);
```

**Key render hooks relevant to AgileTracker:**

| Enum constant | Injection point | Scopable |
|---|---|---|
| `CONTENT_BEFORE` / `CONTENT_AFTER` | Before/after page content | ✅ Page class |
| `PAGE_HEADER_WIDGETS_BEFORE` / `AFTER` | Around page header widgets | ✅ Page/Resource |
| `PAGE_START` / `PAGE_END` | Start/end of page container | ✅ Page/Resource |
| `RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE` / `AFTER` | Around resource table | ✅ Page/Resource |
| `RESOURCE_TABS_START` / `RESOURCE_TABS_END` | Around resource tabs | ✅ Resource class |
| `SIDEBAR_NAV_START` / `SIDEBAR_NAV_END` | Inside sidebar `<nav>` | — |
| `SIDEBAR_FOOTER` | Pinned to sidebar bottom | — |
| `TOPBAR_START` / `TOPBAR_END` | Inside topbar | — |
| `USER_MENU_AFTER` / `BEFORE` | Around user menu | — |
| `BODY_START` / `BODY_END` | Body open/close | — |
| `HEAD_START` / `HEAD_END` | Head open/close | — |
| `SCRIPTS_BEFORE` / `SCRIPTS_AFTER` | Around scripts block | — |
| `STYLES_BEFORE` / `STYLES_AFTER` | Around styles block | — |
| `AUTH_LOGIN_FORM_AFTER` / `BEFORE` | Around login form | — |

Full list: `Filament\View\PanelsRenderHook`, `Filament\Tables\View\TablesRenderHook`, `Filament\Actions\View\ActionsRenderHook`, `Filament\Widgets\View\WidgetsRenderHook`.

**Scoping** allows render hooks to target specific pages or resources. Plugin providers register these in `boot()`, using `FilamentView::registerRenderHook()`.

### Dual hook strategy

| Use case | System |
|---|---|
| Inject Blade HTML at layout points | Filament Render Hooks |
| Modify queries, data, arrays, configs | Eventy filters |
| Execute side effects at code points | Eventy actions |
| Add scripts/styles | Filament Render Hooks (`SCRIPTS_AFTER`, `STYLES_AFTER`) |

**Naming convention**: `{component}.{event}` — e.g., `items.table.query`, `item.show.sidebar`, `dashboard.widgets`. Plugin-specific hooks use `{plugin-alias}.{event}`.

**Initial Eventy core hook points** (MVP set):

| Hook | Type | Location | Args |
|---|---|---|---|
| `items.table.query` | Filter | ItemResource table query | `$query, $filters` |
| `items.table.columns` | Filter | ItemResource table columns | `$columns` |
| `items.form.fields` | Filter | ItemResource form schema | `$fields` |
| `item.show.content` | Action | Item view page, below main content | `$item` |
| `dashboard.widgets` | Filter | Dashboard widget array | `$widgets` |
| `navigation.items` | Filter | Sidebar navigation items | `$items` |

### Activation model

Two-tier priority system:

1. `config/plugins.php` — default activation list (`'active' => ['my-plugin']`). Source of truth in version control.
2. `plugins` DB table — overrides from admin UI. Admin can activate/deactivate any discovered plugin. DB state overrides config.

```
Is plugin active?
  → Check plugins table for alias.
  → If entry exists: use its `active` flag.
  → If no entry: fall back to config('plugins.active').
```

**Uninstalled plugin safety**: if a plugin folder is deleted but the DB still references it — PluginManager skips it and logs a warning. No crash.

### Admin UI (Filament page)

`app/Filament/Pages/PluginPage.php`:
- Appears under **Settings** navigation group, sort 100 (after ManageSettings).
- Accessible only to `admin` role.
- Lists all discovered plugins with: name, alias, version, description, active toggle.
- Rows from plugin folders (not from DB). Rows from `plugins/` directory scan.
- Permanently show `_example` plugin as read-only (greyed out, not toggleable).
- Toggle triggers DB write, which overrides config.

### Plugin installation (manual)

1. Drop plugin folder into `plugins/`.
2. Refresh page — PluginManager picks it up on next request (discovery is cached, cache key: `plugins.manifest`).
3. Activate via admin UI.
4. Or add alias to `config('plugins.active')` for default-on activation.

### Security

- Plugins have full access to the Laravel app (same process, same user). No sandboxing.
- Admin-only activation: only users with `admin` role can toggle plugins.
- Minimal `plugin.json` schema validation — no code execution from JSON.
- Plugins are trusted code. Distribution is manual (ZIP), not a marketplace.

### Constraints

- No automatic updates or marketplace.
- No plugin dependency resolution beyond `requires.php`.
- No per-plugin permissions — plugins inherit the app's permission system.
- No plugin uninstall hooks (MVP).
- Plugin discovery is cached (file-based cache, invalidated on `php artisan cache:clear` or `plugins:discover`).

### Out of scope

- Plugin marketplace / automatic installation / version checking.
- Plugin sandboxing or isolation.
- Plugin-specific migrations auto-run (manual `php artisan migrate` covers it).
- Plugin asset publishing (use `php artisan vendor:publish` if needed).
- Plugin-to-plugin dependencies.
- Composer dependencies per plugin (single composer.json for the whole app).

### Dependencies

- `tormjens/eventy` ^0.9 — WordPress-style actions and filters for data/logic hooks.
- Existing Filament 5 — admin UI + native Render Hooks (`Filament\Support\Facades\FilamentView`).
- Existing `spatie/laravel-permission` — role-based access control for admin page.

### Why two hook systems?

Filament Render Hooks and Eventy solve different problems:

- **Filament Render Hooks** are Blade-level — they inject HTML into the layout (sidebar, topbar, page header, table). Perfect for: adding a widget, extra column, banner, or script. 70+ injection points, scoped to specific pages/resources. Zero extra dependency — built into Filament.
- **Eventy** is code-level — it hooks into PHP logic (queries, collections, data). Perfect for: modifying an Eloquent query, changing config arrays, running side effects. No HTML — pure data/logic.

Plugin providers use both: Render Hooks for UI injection, Eventy for backend hooks. They complement each other, not compete.

## Acceptance Criteria

- [ ] `composer require tormjens/eventy` installs without conflicts.
- [ ] `config/plugins.php` exists with `'active' => []` default.
- [ ] Migration `create_plugins_table` runs successfully (columns: id, alias, active, timestamps).
- [ ] `PluginManager` scans `plugins/*/plugin.json` and returns discovered plugins.
- [ ] Plugin discovery is cached; cache key is `plugins.manifest`.
- [ ] `plugins:discover` artisan command flushes and rebuilds the plugin manifest.
- [ ] Active plugin ServiceProviders are registered in Laravel container on boot.
- [ ] `plugins/_example` reference plugin exists and demonstrates hook usage.
- [ ] `plugins/hello-world` example plugin exists and is activatable — injects a banner into dashboard via Filament Render Hook, subscribes to `navigation.items` via Eventy.
- [ ] `PluginPage` Filament page lists all discovered plugins with toggles under Settings group.
- [ ] Only `admin` role can access PluginPage (404 for manager/user).
- [ ] Toggling a plugin in admin UI persists to `plugins` table and takes effect on next request.
- [ ] DB activation overrides `config('plugins.active')` (admin UI wins).
- [ ] Deleted plugin folders don't crash the app — skipped with a log warning.
- [ ] `Eventy` hooks work: plugins can subscribe to core filter/action points.
- [ ] Filament Render Hooks work: plugins can inject Blade content via `FilamentView::registerRenderHook()`.
- [ ] At least 6 Eventy core hook points exist and are documented.
- [ ] The dual hook strategy is documented: Eventy for data/logic, Filament Render Hooks for UI injection.
- [ ] `./vendor/bin/pint` passes.
- [ ] `php artisan test` passes (including new Plugin system tests).

## Addenda

### `PluginPage` Filament page — sketch

```php
// app/Filament/Pages/PluginPage.php

class PluginPage extends Page
{
    protected static string $view = 'filament.pages.plugins';
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 100;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
```

The page view iterates `app(PluginManager::class)->all()` and renders a toggleable table. Each row shows: name, alias, version, description, active state.

### Database schema

```sql
CREATE TABLE plugins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    alias VARCHAR(255) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### `PluginManager` API

```php
PluginManager::all(): Collection            // all discovered plugins (active + inactive)
PluginManager::active(): Collection         // only active plugins
PluginManager::isActive(string $alias): bool
PluginManager::activate(string $alias): void
PluginManager::deactivate(string $alias): void
PluginManager::discover(): void             // force re-scan
```

### Convention: auto-loaded optional files

If a plugin has these files, PluginManager loads them automatically:

| File | Behavior |
|---|---|
| `src/routes.php` | Loaded via `Route::group()` with no prefix |
| `resources/views/` | Registered as `plugins::{alias}` view namespace |
| `database/migrations/` | Standard Laravel — picked up by `php artisan migrate` |

### Navigation structure (updated)

| Page/Resource | Group | Sort |
|---|---|---|
| ItemResource | Work | 1 |
| KanbanBoard | Work | (default) |
| UserResource | Settings | 9 |
| LabelResource | Settings | 10 |
| ManageSettings | Settings | 99 |
| **PluginPage** | **Settings** | **100** |

### Reference: `plugins/_example/plugin.json`

```json
{
    "name": "Example Plugin",
    "alias": "_example",
    "version": "1.0.0",
    "description": "Reference plugin demonstrating hook usage. Not activatable.",
    "providers": [
        "Plugins\\_Example\\ExampleServiceProvider"
    ]
}
```

### Installation sequence

```bash
composer require tormjens/eventy
php artisan make:migration create_plugins_table
# ... write migration, then:
php artisan migrate
mkdir -p plugins/_example/src
# ... create plugin.json + ExampleServiceProvider
php artisan plugins:discover
```