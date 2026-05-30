---
id: arch_001
title: Project Architecture
content: AgileTracker is a minimal Jira alternative built on Laravel 11 + FilamentPHP 3 with Spatie Permission and Activitylog
importance: high
tags: architecture, laravel, filament, stack
---

## Architecture Decisions

### Why Laravel + Filament?
- Laravel provides robust backend (Eloquent, queues, validation, auth)
- Filament gives admin panel out of the box — faster than building custom UI
- Spatie packages are de-facto standard for permissions and audit logs

### Database
- SQLite for development (zero config)
- MySQL/PostgreSQL for production
- Migrations for all schema changes

### Authorization
- Spatie Laravel Permission with 4 roles: admin, project_manager, developer, viewer
- Filament integrates natively via `$user->can()` and Policies

### Activity Tracking
- Spatie Laravel Activitylog on all key models (Item, Comment)
- Tracks: created, updated, deleted, restored events with attribute diffs
