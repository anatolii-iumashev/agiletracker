# AgileTracker

A minimal project & task manager built with **Laravel & Filament PHP**. Jira/Redmine-alternative.

## Stack

- PHP 8.3+
- Laravel 11
- FilamentPHP 3
- Spatie Laravel Permission (roles)
- Spatie Laravel Activitylog (change history)
- Tailwind CSS (via Filament)
- SQLite / MySQL / PostgreSQL

---

## Quick start

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

Open **http://localhost:8000/admin** and log in with:
- Email: `admin@example.com`
- Password: `password`

---

## File structure (this scaffold)

```
app/
  Filament/
    Pages/
      KanbanBoard.php           ← Drag-and-drop Kanban view
    Resources/
      ItemResource.php          ← Main resource (Tasks / Epics / Projects)
      ItemResource/
        Pages/
          ListItems.php         ← Tabbed list (All / Mine / To Do / In Progress / Overdue)
          CreateItem.php
          EditItem.php
        RelationManagers/
          CommentsRelationManager.php   ← Inline comments with @mention support
          ChildrenRelationManager.php   ← Sub-items panel
      LabelResource.php
      UserResource.php
    Widgets/
      StatsOverviewWidget.php   ← Dashboard stats (open, in-progress, done today, overdue)
      MyTasksWidget.php         ← Current user's open tasks

  Models/
    Item.php      ← Single table (type column: task / epic / project / case)
    Label.php
    Comment.php
    User.php

  Providers/
    Filament/
      AdminPanelProvider.php

database/
  migrations/
    ..._create_items_table.php
    ..._create_labels_table.php
    ..._create_comments_table.php
    ..._create_statuses_table.php
  seeders/
    RolesAndPermissionsSeeder.php   ← admin / manager / member roles + default admin user

resources/
  views/
    filament/
      pages/
        kanban-board.blade.php  ← Blade + Alpine.js drag-and-drop Kanban
```

---

## Key design decisions

| Decision | Reason |
|---|---|
| Single `items` table with `type` column | Simpler than full polymorphism; easy to convert between types |
| `parent_id` self-reference | Builds Project → Epic → Task hierarchy with one join |
| FilamentPHP v3 Resources | Handles CRUD, filters, relations, global search with minimal boilerplate |
| HTML5 drag events + Livewire | No extra JS library; status changes persist via `#[On('item-moved')]` |
| Spatie Permission | Role-gated panel access; expandable to per-resource policies |

---

## Roles

| Role | Permissions |
|---|---|
| **admin** | All permissions + manage users |
| **manager** | View, create, edit items + manage labels |
| **member** | View, create, edit items |
