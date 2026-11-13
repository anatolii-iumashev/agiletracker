# RFC: REST API поиска задач и проектов с автодокументацией

## TL;DR

Добавляем публичный REST API v1: `GET /api/v1/search` ищет только задачи (issues/items), а `GET /api/v1/projects/search` — только проекты (root-level items). Аутентификация — через Sanctum, документация генерируется на базе Scribe.

## Context

### What?

- `routes/api.php` с префиксом `/api/v1`
- Эндпоинт `GET /api/v1/search` — поиск только по задачам, без параметра `scope` и без проектов в выдаче
- Эндпоинт `GET /api/v1/projects/search` — отдельный поиск только по проектам
- Аутентификация: Laravel Sanctum (personal access tokens)
- Документация: `knuckleswtf/scribe` — HTML-доки + Postman-коллекция + OpenAPI 3.1, генерируется командой `php artisan scribe:generate`

### Why?

- PRODUCT.md декларирует «Public API» как часть продукта — сейчас API нет вообще.
- Поиск — первый кандидат: нужен для интеграций, будущих мобильных приложений и глобальной строки поиска в UI.
- Разделение маршрутов повторяет семантику Jira: общий поиск возвращает issues, а проекты запрашиваются через отдельный ресурс.
- Автодокументация из кода исключает дрифт документации от реализации.

### How?

- Код-first: контроллеры + Form Requests + API Resources, Scribe выводит схемы из валидации и ресурсов.
- Поиск — обычный SQL `LIKE` по `title`/`description` через scope `Item::search()` (SQLite-совместимо, хватает на тысячи записей). Laravel Scout оставляем как upgrade path — сигнатура API при этом не меняется.
- Версионирование через префикс URL (`/api/v1`).

## Components & Specifics

### Затрагиваемые компоненты

| Компонент | Что делаем |
|---|---|
| `routes/api.php` | новый файл, группа `prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])`, маршруты `search` и `projects/search` |
| `app/Http/Controllers/Api/V1/SearchController.php` | методы `search()` для задач и `searchProjects()` для проектов |
| `app/Http/Requests/Api/SearchRequest.php` | валидация: `q` (required, string, min:2, max:200), `type`, `label`, `assignee_id`, `per_page` (max:100); параметра `scope` нет |
| `app/Http/Resources/ItemResource.php` | сериализация задачи (namespace НЕ конфликтует с `App\Filament\Resources\ItemResource`) |
| `app/Http/Resources/ProjectResource.php` | тонкая обёртка над root-level Item |
| `app/Models/Item.php` | новый scope `search(string $query)` |
| Sanctum | установка пакета, миграция `personal_access_tokens`, `HasApiTokens` на `User` |
| `config/scribe.php` | публикация конфига, `openapi.enabled = true`, группы «Search» и «Projects» |
| `tests/Feature/Api/SearchTest.php` | feature-тесты эндпоинтов |

### Ключевые решения

1. **`GET /api/v1/search` возвращает только issues** — items с `parent_id IS NOT NULL`; смешанной выдачи и параметра `scope` нет. Проекты доступны только через `GET /api/v1/projects/search`.
2. **«Проект» = root-level item** (`parent_id IS NULL`). Отдельной модели `Project` в домене нет; API отражает текущую модель. Явное выделение проектов (`meta.type = 'project'` или отдельная таблица) — вне скоупа, кандидат на отдельный RFC.
3. **Пагинация** — cursor-based (`cursorPaginate`), дешевле offset на больших выборках; ответ в формате `data` + `meta` + `links`.
4. **Фильтры** — query-параметры `type`, `label`, `assignee_id` применяются поверх поиска задач; фильтрация по статусу/приоритету идёт через `where('meta->status', ...)` (работает и в SQLite, без индекса — терпимо на текущем объёме).
5. **Rate limiting** — `throttle:60,1` на всю группу API.
6. **Документация в CI** — шаг `php artisan scribe:generate` + `git diff --exit-code` на сгенерированные файлы: PR, рассинхронизировавший код и доки, падает в CI.

### Ограничения и edge cases

- `q` короче 2 символов → 422 на обоих маршрутах.
- Пустой результат → `200` с пустым `data` (не 404).
- Soft-deleted items исключаются из выдачи (глобальный scope `SoftDeletes`).
- Пользователь видит только items, к которым у него есть доступ — политика авторизации через Policy (сейчас доступ не ограничен, зафиксировать текущее поведение в тестах).

### Out of scope

- CRUD-эндпоинты для items/comments/labels.
- Единый поиск с одновременной выдачей разных типов сущностей.
- Laravel Scout / Meilisearch / FULLTEXT-поиск.
- Webhooks, OAuth2-флоу, публичные SDK.
- Изменение доменной модели (выделение Project в отдельную сущность).

### Зависимости

- `laravel/sanctum` (composer require)
- `knuckleswtf/scribe` (composer require --dev)
- Существующие: `spatie/laravel-permission` (будущие scope-ы токенов), `SoftDeletes` на `Item`

## Acceptance Criteria

- [ ] `GET /api/v1/search?q=...` возвращает `200` с пагинированной выдачей только по задачам; проекты не попадают в ответ даже при совпадении текста; без токена — `401`
- [ ] `GET /api/v1/projects/search?q=...` возвращает `200` с пагинированной выдачей только по проектам; без токена — `401`
- [ ] Валидация на обоих маршрутах: `q` < 2 символов → `422` с описанием ошибки; `per_page` > 100 → `422`
- [ ] Фильтры `type`, `label`, `assignee_id` корректно сужают выдачу задач (покрыто тестами)
- [ ] Удалённые (soft-deleted) записи не попадают в выдачу обоих маршрутов
- [ ] `php artisan scribe:generate` генерирует HTML-документацию, OpenAPI 3.1 спеку и Postman-коллекцию, доступные на `/docs`
- [ ] CI падает, если сгенерированная документация расходится с кодом
- [ ] `php artisan test` зелёный, включая новые feature-тесты; `./vendor/bin/pint` без изменений

## Addenda

### Пример ответа `GET /api/v1/search?q=оплата`

```json
{
  "data": [
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
  "meta": { "next_cursor": null }
}
```

### Ссылки

- Scribe: https://scribe.knuckles.wtf/laravel
- Sanctum: https://laravel.com/docs/sanctum
- Обсуждение дизайна: сессия 2026-11-11 (альтернативы: Scramble — отклонён владельцем, design-first openapi.yaml — отложен как fallback)
