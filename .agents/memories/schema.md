---
id: schema_001
title: Database Schema
content: Current schema with items, labels, comments, and pivot tables
importance: high
tags: database, schema, migrations
---

## Current Tables

### users (Laravel default + Spatie)
- Standard Laravel user table
- Spatie permission tables (roles, permissions, model_has_roles, etc.)

### items
- Core entity: task, bug, feature, or epic
- Status: backlog → todo → in_progress → review → done
- Priority: low, medium, high, critical
- Self-referencing parent_id for sub-tasks/epics
- assigned_to (FK users), created_by (FK users)
- due_date, completed_at, sort_order for kanban ordering

### labels
- Name + color (hex)
- Many-to-many with items via item_label pivot

### comments
- body (text), item_id (FK), user_id (FK)
- Simple flat comments (no threading)

### activity_log (Spatie)
- Tracks all model changes with causer, subject, properties (diff)
