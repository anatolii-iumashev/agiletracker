# ROADMAP — AgileTracker

- [ ] alfa 1
    - check this https://filamentphp.com/docs/5.x/introduction/ai
    - это что такое? /Users/aa/Projects/agiletracker/lang/vendor/filament-panels
    

## Upcoming

### Setup & DX

- [ ] `composer require filament/filament` — install Filament PHP
- [ ] `php artisan filament:install --panels` — scaffold admin panel
- [ ] `php artisan boost:install` (re-run) — pull in Filament guidelines to AGENTS.md
- [ ] `npm install` + `npm run build` — frontend

### Core features (MVP)

- [ ] Item CRUD (Filament Resource) — tasks/bugs/features with fields and statuses
- [ ] Kanban board (drag & drop, status columns)
- [ ] Labels (tags) CRUD
- [ ] Comments on Items
- [ ] Assignee + Reporter relationship on Item
- [ ] Dashboard with widgets (my tasks, recent activity)
- [ ] Roles & permissions (admin, manager, user) via Spatie

### Future

- [ ] Time tracking (estimated/spent in Item)
- [ ] Subtasks (parent_id in Item)
- [ ] Notifications (due date reminders, assignment alerts)
- [ ] Search & filters (by status, priority, labels)
- [ ] API (Laravel Sanctum + API Resources)
- [ ] Tests (Unit + Feature, critical path coverage)

---

## Tooling evaluation

| Tool | Status | Notes |
|---|---|---|
| `laravel/boost` | ✅ Installed | Laravel + PHP guidelines in AGENTS.md. Filament section will appear after installing filament/filament and re-running `boost:install` |
| `filament/blueprint` | 🔮 Future | Paid package for Filament v4+. Generates detailed implementation plans (models, resources, forms, tables, policies, tests). **Re-evaluate after**: migrating to Filament v4 and introducing complex features (workflows, wizards, multi-tenancy). Current project scope (4 models) doesn't justify the license cost |

### When to revisit filament/blueprint

- After upgrading to Filament v4
- When at least 2 of these signals appear:
  - Complex multi-step forms (wizards)
  - Custom Relation Managers
  - Multi-tenancy
  - Reactive fields
  - Non-trivial bulk actions
  - Data import/export
