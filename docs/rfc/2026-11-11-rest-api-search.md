# RFC: REST API поиска задач и проектов с автодокументацией

## TL;DR

Добавляем публичный REST API v1 с единым эндпоинтом поиска по задачам (items) и проектам (root-level items), аутентификацией через Sanctum и автогенерируемой документацией на базе Scribe.

## Context

### What?

- `routes/api.php` с префиксом `/api/v1`
- Эндпоинт `GET /api/v1/search` — единый поиск с параметром `scope=items|projects|all`
- Эндпоинты-сахар: `GET /api/v1/items/search`, `GET /api/v1/projects/search`
- Аутентификация: Laravel Sanctum (personal access tokens)
- Документация: `knuckleswtf/scribe` — HTML-доки + Postman-коллекция + OpenAPI 3.1, генерируется командой `php artisan scribe:generate`

### Why?

- PRODUCT.md декларирует «Public API» как часть продукта — сейчас API нет вообще.
- Поиск — первый кандидат: нужен для интеграций, будущих мобильных приложений и глобальной строки поиска в UI.
- Автодокументация из кода исключает дрифт документации от реализации.

### How?

- Код-first: контроллеры + Form Requests + API Resources, Scribe выводит схемы из валидации и ресурсов.
- Поиск — обычный SQL `LIKE` по `title`/`description` через scope `Item::search()` (SQLite-совместимо, хватает на тысячи записей). Laravel Scout оставляем как upgrade path — сигнатура API при этом не меняется.
- Версионирование через префикс URL (`/api/v1`).

## Components & Specifics

### Затрагиваемые компоненты

| Компонент | Что делаем |
|---|---|
| `routes/api.php` | новый файл, группа `prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])` |
| `app/Http/Controllers/Api/V1/SearchController.php` | методы `search()`, `searchItems()`, `searchProjects()` |
| `app/Http/Requests/Api/SearchRequest.php` | валидация: `q` (required, string, min:2, max:200), `scope`, `type`, `label`, `assignee_id`, `per_page` (max:100) |
| `app/Http/Resources/ItemResource.php` | сериализация задачи (namespace НЕ конфликтует с `App\Filament\Resources\ItemResource`) |
| `app/Http/Resources/ProjectResource.php` | тонкая обёртка над root-level Item |
| `app/Models/Item.php` | новый scope `search(string $query)` |
| Sanctum | установка пакета, миграция `personal_access_tokens`, `HasApiTokens` на `User` |
| `config/scribe.php` | публикация конфига, `openapi.enabled = true`, группа «Search» |
| `tests/Feature/Api/SearchTest.php` | feature-тесты эндпоинтов |

### Ключевые решения

1. **«Проект» = root-level item** (`parent_id IS NULL`). Отдельной модели `Project` в домене нет; API отражает текущую модель. Явное выделение проектов (`meta.type = 'project'` или отдельная таблица) — вне скоупа, кандидат на отдельный RFC.
2. **Пагинация** — cursor-based (`cursorPaginate`), дешевле offset на больших выборках; ответ в формате `data` + `meta` + `links`.
3. **Фильтры** — query-параметры `type`, `label`, `assignee_id` применяются поверх текстового поиска; фильтрация по статусу/приоритету идёт через `where('meta->status', ...)` (работает и в SQLite, без индекса — терпимо на текущем объёме).
4. **Rate limiting** — `throttle:60,1` на всю группу API.
5. **Документация в CI** — шаг `php artisan scribe:generate` + `git diff --exit-code` на сгенерированные файлы: PR, рассинхронизировавший код и доки, падает в CI.

### Ограничения и edge cases

- `q` короче 2 символов → 422.
- Пустой результат → `200` с пустым `data` (не 404).
- Soft-deleted items исключаются из выдачи (глобальный scope `SoftDeletes`).
- Пользователь видит только items, к которым у него есть доступ — политика авторизации через Policy (сейчас доступ не ограничен, зафиксировать текущее поведение в тестах).

### Out of scope

- CRUD-эндпоинты для items/comments/labels.
- Laravel Scout / Meilisearch / FULLTEXT-поиск.
- Webhooks, OAuth2-флоу, публичные SDK.
- Изменение доменной модели (выделение Project в отдельную сущность).

### Зависимости

- `laravel/sanctum` (composer require)
- `knuckleswtf/scribe` (composer require --dev)
- Существующие: `spatie/laravel-permission` (будущие scope-ы токенов), `SoftDeletes` на `Item`

## Acceptance Criteria

- [ ] `GET /api/v1/search?q=...` возвращает `200` с пагинированной выдачей по задачам и проектам; без токена — `401`
- [ ] `GET /api/v1/items/search` и `GET /api/v1/projects/search` возвращают только соответствующий scope
- [ ] Валидация: `q` < 2 символов → `422` с описанием ошибки; `per_page` > 100 → `422`
- [ ] Фильтры `type`, `label`, `assignee_id` корректно сужают выдачу (покрыто тестами)
- [ ] Удалённые (soft-deleted) записи не попадают в выдачу
- [ ] `php artisan scribe:generate` генерирует HTML-документацию, OpenAPI 3.1 спеку и Postman-коллекцию, доступные на `/docs`
- [ ] CI падает, если сгенерированная документация расходится с кодом
- [ ] `php artisan test` зелёный, включая новые feature-тесты; `./vendor/bin/pint` без изменений

## Addenda

### Пример ответа `GET /api/v1/search?q=оплата&scope=all`

```json
{
  "data": {
    "items": [
      {
        "id": 42,
        "title": "Оплата хостинга",
        "type": "task",
        "parent_id": 7,
        "assignee": { "id": 3, "name": "Anna" },
        "labels": ["finance"],
        "due_date": "2026-11-20"
      }
    ],
    "projects": [
      {
        "id": 7,
        "title": "Операционные расходы Q4",
        "children_count": 12
      }
    ]
  },
  "meta": { "next_cursor": null }
}
```

### Ссылки

- Scribe: https://scribe.knuckles.wtf/laravel
- Sanctum: https://laravel.com/docs/sanctum
- Обсуждение дизайна: сессия 2026-11-11 (альтернативы: Scramble — отклонён владельцем, design-first openapi.yaml — отложен как fallback)
