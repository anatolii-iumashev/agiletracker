# ROADMAP — AgileTracker

- [ ] alfa 1
    - [x] AdminPanelProvider - переименовать в MainPanelProvider + id = main и юзать для всех панелей
    - какие то базовые смок тесты - но настроить на базе PestPHP 
    - docs/rfc/2026-05-30-favorite-bookmarks.md 
    - edit item http://localhost:8000/i/4/edit - нужны экшены - Save and view, Save and Delete
    - Type - remove - replace to Labels
    - Favorite - модель и ресурс - юзер может разные типы объектов и ссылки добавлять к себе в избранное - вывод через ресурс с полиморфной связью
    - http://localhost:8001/items - тут не нужен экшен Convert type
    - $navigationGroup = 'Work' - надо поменять на Collections
    - http://localhost:8001/items - заменить на http://localhost:8001/search
    - тут ошибка http://localhost:8001/items/4/edit 
    **done:**
        - check this https://filamentphp.com/docs/5.x/introduction/ai
        - это что такое? /Users/aa/Projects/agiletracker/lang/vendor/filament-panels


## Upcoming

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
