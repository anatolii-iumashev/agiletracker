---
id: laravel-filament-dev
name: Laravel & Filament Development
description: Build features for AgileTracker using Laravel 11 and FilamentPHP 3
enabled: true
---

# Laravel + Filament Development Workflow

## Creating a new Model/Resource

1. **Migration**: `php artisan make:migration create_xxx_table`
   - Include all columns, foreign keys, indexes
   - Use `$table->id()`, `$table->timestamps()`, `$table->softDeletes()`
2. **Model**: Create in `app/Models/` with:
   - `$fillable` or `$guarded`
   - Relationships (`hasMany`, `belongsTo`, `belongsToMany`)
   - Spatie Activitylog trait: `use LogsActivity;`
3. **Filament Resource**: `php artisan make:filament-resource Xxx`
   - Define `form()`, `table()`, `getPages()`, `getRelations()`
   - Add filters, actions, bulk actions
4. **Policy**: `php artisan make:policy XxxPolicy --model=Xxx`
5. **Seeder**: Add to `DatabaseSeeder` or create standalone
6. **Test**: Feature test for CRUD + permissions

## Filament Patterns

### Form fields
```php
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
```

### Table columns
```php
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
```

### Filters
```php
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
```

### Actions
- `EditAction`, `DeleteAction` in table
- Custom actions: `Action::make('archive')->action(fn() => ...)`

## Spatie Permission

- Roles: `admin`, `project_manager`, `developer`, `viewer`
- Check in Filament: `->visible(fn() => auth()->user()->can('...'))`
- Policies use `$user->hasPermissionTo('...')` or `$user->hasRole('...')`
