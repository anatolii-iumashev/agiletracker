---
id: conv_001
title: Development Conventions
content: Coding standards and workflow for AgileTracker
importance: medium
tags: conventions, workflow, code-style
---

## Conventions

### PHP
- `declare(strict_types=1)` in all files
- PSR-12 formatting (use `./vendor/bin/pint`)
- Return type hints on all methods
- Parameter type hints on all methods
- No `mixed` types where specific types are known

### Laravel
- Migrations never edited — always create new ones
- Seeders for test data and defaults
- Form Requests for validation (not controller validation)
- Policies for authorization
- Eloquent over raw queries

### Filament
- Resources in `app/Filament/Resources/`
- Pages in `app/Filament/Pages/`
- Widgets in `app/Filament/Widgets/`
- Custom theme in `resources/css/filament/admin/theme.css`
