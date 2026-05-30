# RFC: Central Settings System via filament-spatie-settings

## TL;DR

Add a central settings management page using `filament/spatie-laravel-settings-plugin`, accessible only to users with the `admin` role, placed at the bottom of the sidebar navigation.

## Context

### What?

Install the `filament/spatie-laravel-settings-plugin` (which wraps `spatie/laravel-settings`) to provide a Settings page in the Filament admin panel. The page allows admins to manage global application settings — such as app name, default item priorities, notification preferences — stored in the database via a dedicated `settings` table.

### Why?

Currently there is no central place for global application configuration. Any setting change requires editing code, `.env`, or running database queries manually. A Settings page in the admin panel:
- Provides a UI for non-technical admins to configure the app.
- Serves as a foundation for future settings (email templates, notification rules, time tracking defaults).
- Uses a well-established Laravel pattern (`spatie/laravel-settings`) with type-safe settings classes.

### How?

1. Install `spatie/laravel-settings` and `filament/spatie-laravel-settings-plugin` via Composer.
2. Create a `GeneralSettings` class (`app/Settings/GeneralSettings.php`) with typed properties.
3. Create a migration for the `settings` table.
4. Generate a Filament settings page via `php artisan make:filament-settings-page` tied to `GeneralSettings`.
5. Restrict access via `canAccess()` — only users with the `admin` role (Spatie Permission).
6. Position the page at the bottom of the sidebar using `$navigationSort = 99` and the existing `Settings` navigation group.
7. Register the settings class in the service container if needed.

## Components & Specifics

### Affected components

| Component | Change |
|---|---|
| `composer.json` | Add `spatie/laravel-settings` and `filament/spatie-laravel-settings-plugin` |
| `app/Settings/GeneralSettings.php` | **New** — typed settings class with properties |
| `app/Filament/Pages/ManageSettings.php` | **New** — Filament settings page (extends `SettingsPage`) |
| `database/migrations/` | **New migration** — `create_settings_table` |
| `bootstrap/providers.php` | Register `GeneralSettings` in the container (if needed) |

### Settings properties (initial set)

```php
class GeneralSettings extends Settings
{
    public string $site_name;        // default: "AgileTracker"
    public string $default_priority; // default: "medium"
    public bool $enable_notifications; // default: true
    public ?string $default_due_days;  // default: 14 (days from creation)

    public static function group(): string
    {
        return 'general';
    }
}
```

### Authorization

The `ManageSettings` page uses Spatie Permission for access control:

```php
public static function canAccess(): bool
{
    return auth()->user()?->hasRole('admin') ?? false;
}
```

- Only `admin` role can view OR edit settings.
- No separate `canEdit()` — since the page is entirely gated by `canAccess()`, there's no read-only fallback needed.

### Navigation placement

The page is placed in the existing `Settings` navigation group (shared with `UserResource` and `LabelResource`) but with a high `$navigationSort` to appear last:

```php
protected static UnitEnum|string|null $navigationGroup = 'Settings';
protected static ?int $navigationSort = 99;
```

This ensures the Settings menu item is the last item in the Settings group, visually at the bottom of the sidebar.

### Constraints

- The `settings` table uses a key-value structure managed automatically by `spatie/laravel-settings` — no direct DB queries needed.
- Settings values are cached. After saving, the cache is invalidated automatically.
- Only one settings class (`GeneralSettings`) for MVP; additional groups can be added later.

### Out of scope

- Per-user settings (this is global app configuration).
- UI for managing the settings schema itself (that's code-level).
- Email template editing.
- Audit log for settings changes (can be added later via the existing `spatie/laravel-activitylog`).
- Environment-specific overrides (all settings live in DB).

### Dependencies

- `spatie/laravel-settings` ^3.0 — database-backed settings with typed classes.
- `filament/spatie-laravel-settings-plugin` ^5.0 — Filament integration (auto-form binding, save).
- Existing `spatie/laravel-permission` — for role-based access control.
- Existing roles in DB: `admin` (id=1), `manager` (id=2), `user` (id=3).

## Acceptance Criteria

- [ ] `composer require spatie/laravel-settings filament/spatie-laravel-settings-plugin` installs without conflicts.
- [ ] Migration `create_settings_table` runs successfully.
- [ ] `GeneralSettings` class exists in `app/Settings/` with `site_name`, `default_priority`, `enable_notifications`, `default_due_days`.
- [ ] `ManageSettings` page is discoverable by Filament in `app/Filament/Pages/`.
- [ ] The Settings page appears in the sidebar under the **Settings** group, as the last item.
- [ ] Users with `admin` role can access and save the Settings page.
- [ ] Users with `manager` or `user` roles cannot see or access the Settings page (404 or hidden from sidebar).
- [ ] Form fields are pre-filled with current DB values on page load.
- [ ] Saving the form persists values to the `settings` table.
- [ ] `./vendor/bin/pint` passes.
- [ ] `php artisan test` passes (including new Settings access tests).

## Addenda

### Installation sequence

```bash
composer require spatie/laravel-settings filament/spatie-laravel-settings-plugin
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="migrations"
php artisan migrate
php artisan make:settings GeneralSettings
php artisan make:filament-settings-page ManageSettings GeneralSettings
```

### Current navigation structure (for reference)

| Page/Resource | Group | Sort |
|---|---|---|
| ItemResource | Work | 1 |
| KanbanBoard | Work | (default) |
| UserResource | Settings | 9 |
| LabelResource | Settings | 10 |
| **ManageSettings** | **Settings** | **99** |

### Roles reference

| Role | ID | Can access Settings? |
|---|---|---|
| admin | 1 | ✅ Yes |
| manager | 2 | ❌ No |
| user | 3 | ❌ No |
