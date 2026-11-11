# ROADMAP — AgileTracker


## MVP

- [ ] Alfa 3
    - sidebar - collapsed
    - save files - on local and S3

- [ ] beta 1
    - add master setup - like wordpress - if no data - setup first admin user for first auto login
    - merge migrations (one per model + one for settings)
    - [ ] Kanban board (drag & drop, status columns)
    - [ ] Roles & permissions (admin, manager, user) via Spatie
    - [ ] Sub issues (parent_id in Item)

## Upcoming

### Core features (MVP)

- [x] Item CRUD (Filament Resource) — tasks/bugs/features with fields and statuses
- [x] Labels (tags) CRUD
- [x] Comments on Items
- [x] From + To relationship on Item
- [x] Dashboard with widgets (my tasks, recent activity)

### Phase 2
- plugin-system - docs/rfc/2026-05-30-plugin-system.md 

### Future

- [ ] Notifications (due date reminders, assignment alerts)
- [ ] Search & filters (by status, priority, labels)
- [ ] API (Laravel Sanctum + API Resources)
- [ ] Tests (Unit + Feature, critical path coverage)

## Plugins
- [ ] Time tracking (estimated/spent in Item)

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
