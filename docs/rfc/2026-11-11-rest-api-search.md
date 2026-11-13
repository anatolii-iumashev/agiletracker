# RFC: REST API поиска задач и проектов с автодокументацией

## TL;DR

Добавляем публичный REST API v1: `GET /api/v1/search` ищет только задачи (issues/items), а `GET /api/v1/projects/search` — только проекты (root-level items). Аутентификация — через Sanctum, документация генерируется на базе Scribe.

## Context

### What?

- `routes/api.php` с префиксом `/api/v1`, регистрируемый штатной командой `php artisan install:api`
- `GET /api/v1/search` — поиск только по задачам, без параметра `scope` и проектов в выдаче
- `GET /api/v1/projects/search` — отдельный поиск только по проектам
- Аутентификация: Laravel Sanctum (personal access tokens) с read-only abilities
- Документация: `knuckleswtf/scribe` — статические HTML + Postman-коллекция + OpenAPI 3.0.3, генерируемые командой `php artisan scribe:generate --env docs`

### Why?

- PRODUCT.md декларирует «Public API» как часть продукта — сейчас API нет вообще.
- Поиск нужен для интеграций, будущих мобильных приложений и глобальной строки поиска в UI.
- Разделение маршрутов повторяет семантику Jira: общий поиск возвращает issues, а проекты запрашиваются через отдельный ресурс.
- Автодокументация из кода исключает дрифт документации от реализации.

### How?

- Код-first: контроллеры + Form Requests + API Resources; Scribe получает схемы из валидации и явных resource-аннотаций для пагинированных ответов.
- Поиск — SQL `LIKE` по `title`/`description` через `Item::search()` с параметризованными значениями и экранированием `%`, `_` и escape-символа. Laravel Scout оставляем как upgrade path без смены API.
- Версионирование через префикс URL (`/api/v1`), авторизация через Policy, именованный rate limiter и cursor-пагинация с детерминированной сортировкой.

## Components & Specifics

### Затрагиваемые компоненты

| Компонент | Что делаем |
|---|---|
| `bootstrap/app.php` | регистрация `routes/api.php` через `install:api`, alias `abilities` для Sanctum; существующий JSON-rendering для `api/*` сохраняем |
| `routes/api.php` | группа `prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])`; именованные маршруты `search` (`abilities:items:read`) и `projects/search` (`abilities:projects:read`) |
| `app/Providers/AppServiceProvider.php` | `RateLimiter::for('api')`: 60 запросов/минуту по user ID, для гостя — по IP |
| `app/Http/Controllers/Api/V1/SearchController.php` | `search()` и `searchProjects()`; `Gate::authorize('viewAny', Item::class)`, только `$request->validated()` |
| `app/Http/Requests/Api/SearchRequest.php` | задачи: `q`, `type`, `label`, `assignee_id`, `per_page`, `cursor`; параметра `scope` нет |
| `app/Http/Requests/Api/ProjectSearchRequest.php` | проекты: только `q`, `per_page`, `cursor`; task-only фильтры → 422 |
| `app/Http/Resources/ItemResource.php` | сериализация задачи (namespace НЕ конфликтует с `App\Filament\Resources\ItemResource`) |
| `app/Http/Resources/ProjectResource.php` | сериализация root-level Item с `children_count` |
| `app/Models/Item.php` | scope `search(string $query)` и query scopes для issues/projects |
| `app/Policies/ItemPolicy.php` | явный `viewAny`; в текущей доменной модели разрешает поиск всем аутентифицированным пользователям |
| Sanctum | установка через `install:api`, миграция `personal_access_tokens`, `HasApiTokens` на `User` |
| `config/scribe.php` | `type=static`, `openapi.enabled=true`, bearer auth, `examples.faker_seed`, группы «Search» и «Projects» |
| `tests/Feature/Api/SearchTest.php` | feature-тесты эндпоинтов, авторизации, пагинации и лимитов |

### Ключевые решения

1. **`GET /api/v1/search` возвращает только issues** — items с `parent_id IS NOT NULL`; смешанной выдачи и параметра `scope` нет. Проекты доступны только через `GET /api/v1/projects/search`.
2. **«Проект» = root-level item** (`parent_id IS NULL`). Отдельной модели `Project` нет; выделение её в отдельную сущность — кандидат на отдельный RFC.
3. **Валидация** — `q`: `required|string|min:2|max:200`; `per_page`: `integer|min:1|max:100`, по умолчанию 15; `cursor`: optional string, некорректное значение → 422; `type`: allow-list `task|bug|feature`; `label`: `string|max:255`; `assignee_id`: `integer|exists:users,id`.
4. **Пагинация** — `orderByDesc('id')->cursorPaginate()`; `id` уникален, non-null и входит в primary key. `withQueryString()` сохраняет `q` и фильтры в links; ответ имеет формат `data` + `meta` + `links`.
5. **Фильтры и производительность** — `type`, `label`, `assignee_id` применяются только к задачам. Оба запроса выбирают нужные колонки; задачи eager-load `assignee:id,name` и `labels:id,name`, проекты используют `withCount('children')`, чтобы исключить N+1.
6. **Авторизация** — оба действия вызывают `viewAny` и проверяют ability токена. Сейчас Policy явно разрешает доступ ко всем неудалённым items; если появятся per-user ограничения, соответствующий visibility scope обязателен в query до пагинации.
7. **Rate limiting и ошибки** — только именованный `throttle:api`; превышение → 429. Ответы 401/403/422/429 возвращаются в JSON через существующую обработку `api/*`.
8. **Документация** — resource collections описываются через `@apiResourceCollection`/`ResponseFromApiResource` и `@apiResourceModel ... paginate=15,cursor`. Статические артефакты в `public/docs` и `public/vendor/scribe` работают без Scribe в production, поэтому пакет остаётся dev-зависимостью.
9. **Документация в CI** — `.env.docs` фиксирует `APP_DEBUG=false` и тестовую SQLite; seed примеров и git-based `last_updated` делают вывод детерминированным. После генерации в чистом checkout `git status --porcelain` должен быть пустым: это ловит и изменённые, и untracked артефакты.

### Ограничения и edge cases

- Пустой результат → `200` с пустым `data`, не 404.
- Soft-deleted items исключаются глобальным scope `SoftDeletes`.
- Отсутствие токена → 401; недостаточная ability или отказ Policy → 403; ошибки валидации → 422; превышение лимита → 429.
- Пользовательские `%` и `_` в `q` трактуются как обычные символы, а не SQL wildcards.

### Out of scope

- CRUD-эндпоинты для items/comments/labels и единый поиск разных типов сущностей.
- UI/эндпоинты выпуска и отзыва токенов.
- Laravel Scout / Meilisearch / FULLTEXT-поиск.
- Webhooks, OAuth2-флоу, публичные SDK.
- Per-user visibility rules и выделение Project в отдельную доменную сущность.

### Зависимости

- `laravel/sanctum` — устанавливается штатной командой `install:api`
- `knuckleswtf/scribe` — `composer require --dev`
- Существующие: `spatie/laravel-permission`, `SoftDeletes` на `Item`

## Acceptance Criteria

- [ ] Оба маршрута возвращают `200` с пагинированной выдачей только своего типа; проект не попадает в `/search` даже при совпадении текста; без токена — JSON `401`
- [ ] Токен без `items:read`/`projects:read` получает JSON `403`; отказ `ItemPolicy::viewAny` также покрыт тестом с JSON `403`
- [ ] Некорректные `q`, `per_page`, `cursor`, `type`, `label`, `assignee_id` возвращают JSON `422`; проектный маршрут не принимает task-only фильтры
- [ ] Фильтры `type`, `label`, `assignee_id` сужают выдачу задач; `%` и `_` в `q` не расширяют результаты
- [ ] Переход по next/previous cursor не даёт дублей и пропусков при `id DESC`; pagination links сохраняют `q` и фильтры
- [ ] Soft-deleted записи исключены; сериализация связанных полей не вызывает N+1
- [ ] 61-й запрос за минуту от одного пользователя получает JSON `429`
- [ ] `php artisan scribe:generate --env docs` создаёт статические HTML, OpenAPI 3.0.3 и Postman-коллекцию, доступные на `/docs`; bearer auth и cursor-paginated схемы описаны
- [ ] CI падает на любом изменении или untracked файле после повторной генерации документации
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
  "links": {
    "first": "https://example.com/api/v1/search?q=оплата",
    "last": null,
    "prev": null,
    "next": null
  },
  "meta": {
    "path": "https://example.com/api/v1/search",
    "per_page": 15,
    "next_cursor": null,
    "prev_cursor": null
  }
}
```

### Ссылки

- Laravel API routing и rate limiting: https://laravel.com/docs/13.x/routing
- Sanctum: https://laravel.com/docs/13.x/sanctum
- Cursor pagination: https://laravel.com/docs/13.x/pagination#cursor-pagination
- Authorization: https://laravel.com/docs/13.x/authorization
- Scribe responses: https://scribe.knuckles.wtf/laravel/documenting/responses
- Scribe config: https://scribe.knuckles.wtf/laravel/reference/config
- Обсуждение дизайна: сессия 2026-11-11 (альтернативы: Scramble — отклонён владельцем, design-first openapi.yaml — отложен как fallback)
