# RFC: REST API поиска Items с автодокументацией

## TL;DR

Добавляем публичный REST API v1 с одним ресурсом `ItemResource` и одним эндпоинтом `GET /api/v1/items/search` для всех items. Новый boolean `is_epic` отмечает проекты и эпики, но не меняет их основную сущность: один item может одновременно быть задачей и большим контейнером.

## Context

### What?

- Новая миграция: `items.is_epic` — non-null boolean с `default(false)` и индексом `(is_epic, id)`
- `routes/api.php` с префиксом `/api/v1`, регистрируемый штатной командой `php artisan install:api`
- `GET /api/v1/items/search` — единый поиск по всем items с опциональным фильтром `is_epic=0|1`; отдельных маршрутов для projects/epics нет
- Один `App\Http\Resources\ItemResource` для обычных items и проектов/эпиков
- Аутентификация: Laravel Sanctum (personal access tokens) с read-only ability `items:read`
- Документация: `knuckleswtf/scribe` — статические HTML + Postman-коллекция + OpenAPI 3.0.3, генерируемые командой `php artisan scribe:generate --env docs`

### Why?

- PRODUCT.md декларирует «Public API» как часть продукта — сейчас API нет вообще.
- Поиск нужен для интеграций, будущих мобильных приложений и глобальной строки поиска в UI.
- Item — гибридная модель: задача может быть проектом или эпиком. Отдельные ресурсы и маршруты создают ложное разделение одной сущности.
- Автодокументация из кода исключает дрифт документации от реализации.

### How?

- Код-first: контроллер + Form Request + один API Resource; Scribe получает схемы из валидации и явных resource-аннотаций для пагинированного ответа.
- Поиск — SQL `LIKE` по `title`/`description` через `Item::search()` с параметризованными значениями и экранированием `%`, `_` и escape-символа. Laravel Scout оставляем как upgrade path без смены API.
- `is_epic` и `parent_id` независимы: первый обозначает большой контейнер, второй — положение в иерархии.
- Версионирование через префикс URL (`/api/v1`), авторизация через Policy, именованный rate limiter и cursor-пагинация с детерминированной сортировкой.

## Components & Specifics

### Затрагиваемые компоненты

| Компонент | Что делаем |
|---|---|
| `database/migrations/*_add_is_epic_to_items_table.php` | `boolean('is_epic')->default(false)` и составной индекс `(is_epic, id)`; в `down()` удаляем индекс и колонку |
| `app/Models/Item.php` | `is_epic` в `$fillable`, cast `boolean`, scope `search(string $query)` и scope фильтрации по `is_epic` |
| `database/factories/ItemFactory.php` | `is_epic => false` по умолчанию и state `epic()` |
| `bootstrap/app.php` | регистрация `routes/api.php` через `install:api`, alias `abilities` для Sanctum; существующий JSON-rendering для `api/*` сохраняем |
| `routes/api.php` | группа `prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])`; один именованный маршрут `items/search` с `abilities:items:read` |
| `app/Providers/AppServiceProvider.php` | `RateLimiter::for('api')`: 60 запросов/минуту по user ID, для гостя — по IP |
| `app/Http/Controllers/Api/V1/ItemController.php` | `search()`; `Gate::authorize('viewAny', Item::class)`, только `$request->validated()` |
| `app/Http/Requests/Api/ItemSearchRequest.php` | `q`, `is_epic`, `type`, `label`, `assignee_id`, `per_page`, `cursor`; параметра `scope` нет |
| `app/Http/Resources/ItemResource.php` | единая сериализация item, включая `is_epic` и `children_count`; namespace НЕ конфликтует с `App\Filament\Resources\ItemResource` |
| `app/Policies/ItemPolicy.php` | явный `viewAny`; в текущей доменной модели разрешает поиск всем аутентифицированным пользователям |
| Sanctum | установка через `install:api`, миграция `personal_access_tokens`, `HasApiTokens` на `User` |
| `config/scribe.php` | `type=static`, `openapi.enabled=true`, bearer auth, `examples.faker_seed`, группа «Items» |
| `tests/Feature/Api/ItemSearchTest.php` | feature-тесты поиска, гибридной модели, авторизации, пагинации и лимитов |

### Ключевые решения

1. **Один ресурс и один поиск** — `/api/v1/items/search` возвращает items независимо от `parent_id` и `is_epic`. Маршруты `/api/v1/search` и `/api/v1/projects/search`, а также `ProjectResource`, не создаются.
2. **`is_epic` — ортогональный признак** — `true` означает проект/эпик, то есть большой контейнер; `false` — обычный item. Признак не заменяет `type`: item с `type=task` может иметь `is_epic=true`. `parent_id` определяет только иерархию и не означает принадлежность к проекту.
3. **Миграция обратно совместима** — существующие записи получают `is_epic=false`. Индекс `(is_epic, id)` поддерживает фильтрацию и cursor-пагинацию; без фильтра используется primary key `id`.
4. **Валидация** — `q`: `required|string|min:2|max:200`; `is_epic`: `sometimes|boolean`; `per_page`: `integer|min:1|max:100`, по умолчанию 15; `cursor`: optional string, некорректное значение → 422; `type`: allow-list `task|bug|feature`; `label`: `string|max:255`; `assignee_id`: `integer|exists:users,id`.
5. **Пагинация** — `orderByDesc('id')->cursorPaginate()`; `id` уникален, non-null и входит в primary key. `withQueryString()` сохраняет `q` и фильтры в links; ответ имеет формат `data` + `meta` + `links`.
6. **Фильтры и производительность** — `is_epic`, `type`, `label`, `assignee_id` применяются поверх текстового поиска. Запрос выбирает нужные колонки, использует `withCount('children')` и eager-load `assignee:id,name` и `labels:id,name`, исключая N+1.
7. **Авторизация** — действие вызывает `viewAny` и проверяет `items:read`. Сейчас Policy явно разрешает доступ ко всем неудалённым items; если появятся per-user ограничения, соответствующий visibility scope обязателен в query до пагинации.
8. **Rate limiting и ошибки** — только именованный `throttle:api`; превышение → 429. Ответы 401/403/422/429 возвращаются в JSON через существующую обработку `api/*`.
9. **Документация** — resource collection описывается через `@apiResourceCollection`/`ResponseFromApiResource` и `@apiResourceModel ... paginate=15,cursor`. Статические артефакты в `public/docs` и `public/vendor/scribe` работают без Scribe в production, поэтому пакет остаётся dev-зависимостью.
10. **Документация в CI** — `.env.docs` фиксирует `APP_DEBUG=false` и тестовую SQLite; seed примеров и git-based `last_updated` делают вывод детерминированным. После генерации в чистом checkout `git status --porcelain` должен быть пустым.

### Ограничения и edge cases

- Без `is_epic` возвращаются все items; `is_epic=1` — только проекты/эпики; `is_epic=0` — только обычные items.
- Изменение `is_epic` не меняет `parent_id`, `type` или связи с children.
- Пустой результат → `200` с пустым `data`, не 404.
- Soft-deleted items исключаются глобальным scope `SoftDeletes`.
- Отсутствие токена → 401; недостаточная ability или отказ Policy → 403; ошибки валидации → 422; превышение лимита → 429.
- Пользовательские `%` и `_` в `q` трактуются как обычные символы, а не SQL wildcards.

### Out of scope

- Отдельные Project/Epic-модели, API Resources, контроллеры и маршруты.
- CRUD-эндпоинты для items/comments/labels.
- UI/эндпоинты выпуска и отзыва токенов.
- Laravel Scout / Meilisearch / FULLTEXT-поиск.
- Webhooks, OAuth2-флоу, публичные SDK.
- Per-user visibility rules.

### Зависимости

- `laravel/sanctum` — устанавливается штатной командой `install:api`
- `knuckleswtf/scribe` — `composer require --dev`
- Существующие: `spatie/laravel-permission`, `SoftDeletes` на `Item`

## Acceptance Criteria

- [ ] Миграция добавляет non-null `is_epic` с `false` по умолчанию и индекс `(is_epic, id)`; существующие записи получают `false`; `down()` удаляет индекс и колонку
- [ ] Зарегистрирован только один поисковый маршрут `GET /api/v1/items/search`; `/api/v1/search` и `/api/v1/projects/search` отсутствуют
- [ ] Без фильтра маршрут возвращает `200` с пагинированной выдачей всех items независимо от `parent_id`; без токена — JSON `401`
- [ ] `is_epic=1` и `is_epic=0` корректно фильтруют выдачу; item с `type=task` и `is_epic=true` возвращается как гибрид через тот же `ItemResource`
- [ ] Токен без `items:read` получает JSON `403`; отказ `ItemPolicy::viewAny` также покрыт тестом с JSON `403`
- [ ] Некорректные `q`, `is_epic`, `per_page`, `cursor`, `type`, `label`, `assignee_id` возвращают JSON `422`
- [ ] Фильтры `type`, `label`, `assignee_id` сужают выдачу; `%` и `_` в `q` не расширяют результаты
- [ ] Переход по next/previous cursor не даёт дублей и пропусков при `id DESC`; pagination links сохраняют `q` и фильтры
- [ ] Soft-deleted записи исключены; сериализация связанных полей не вызывает N+1
- [ ] 61-й запрос за минуту от одного пользователя получает JSON `429`
- [ ] `php artisan scribe:generate --env docs` создаёт статические HTML, OpenAPI 3.0.3 и Postman-коллекцию, доступные на `/docs`; bearer auth, `is_epic` и cursor-paginated схема описаны
- [ ] CI падает на любом изменении или untracked файле после повторной генерации документации
- [ ] `php artisan test` зелёный, включая новые feature-тесты; `./vendor/bin/pint` без изменений

## Addenda

### Пример ответа `GET /api/v1/items/search?q=биллинг&is_epic=1`

```json
{
  "data": [
    {
      "id": 7,
      "title": "Развитие биллинга",
      "type": "task",
      "is_epic": true,
      "parent_id": null,
      "children_count": 12,
      "assignee": { "id": 3, "name": "Anna" },
      "labels": ["finance"],
      "due_date": "2026-11-20"
    }
  ],
  "links": {
    "first": "https://example.com/api/v1/items/search?q=биллинг&is_epic=1",
    "last": null,
    "prev": null,
    "next": null
  },
  "meta": {
    "path": "https://example.com/api/v1/items/search",
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
