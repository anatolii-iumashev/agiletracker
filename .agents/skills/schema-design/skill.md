---
id: schema-design
name: Database Schema Design
description: Design and review database schemas for AgileTracker
enabled: true
---

# Schema Design Principles

## Conventions
- Table names: `snake_case`, plural (`items`, `labels`, `comments`)
- Primary key: always `$table->id()` (bigInteger auto-increment)
- Foreign keys: `{singular}_id` (e.g., `item_id`, `assigned_to_user_id`)
- Timestamps: always include `$table->timestamps()`
- Soft deletes: use `$table->softDeletes()` for recoverable data

## Indexing Rules
- Always index foreign keys: `$table->foreignId('item_id')->constrained()->cascadeOnDelete()`
- Add composite indexes for frequently queried column pairs
- Index columns used in `WHERE`, `ORDER BY`, `GROUP BY`

## Current Schema

### items
- `id`, `title`, `description` (text), `status` (enum: backlog/todo/in_progress/review/done)
- `priority` (enum: low/medium/high/critical), `type` (enum: task/bug/feature/epic)
- `assigned_to` (FK → users), `created_by` (FK → users)
- `parent_id` (self-referencing FK for sub-tasks)
- `due_date`, `completed_at`, `sort_order`
- `timestamps`, `softDeletes`

### labels
- `id`, `name`, `color` (hex), `timestamps`

### comments
- `id`, `body` (text), `item_id` (FK), `user_id` (FK)
- `timestamps`

### pivot: item_label
- `item_id` + `label_id` (composite PK)

## Migration Rules
1. Never edit existing migration files — create new migrations
2. Always test `php artisan migrate:fresh --seed` after changes
3. Use `->after()` to position columns when adding to existing tables
