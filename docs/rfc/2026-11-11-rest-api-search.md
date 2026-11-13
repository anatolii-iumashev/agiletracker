---
title: "REST API Items на Lomkit: поиск, мутации, действия и OpenAPI"
status: draft
created: 2026-11-11
---

# RFC: REST API Items на Lomkit — поиск, мутации, действия и автодокументация

## TL DR

Заменяем ещё не реализованную связку собственных CRUD-контроллеров, Laravel API Resources и Scribe на `lomkit/laravel-rest-api` 3.x с OpenAPI через Scramble. Единый `App\Rest\Resources\ItemResource` декларирует поля, связи, валидацию и действия модели `Item`, а пакет предоставляет `/api/v1/items/search`, `/mutate`, `/actions/assign`, `/details` и удаление. Сохраняем доменную модель с `is_epic` и `meta.type`; интерактивная документация `/docs/api` и экспортируемый OpenAPI-снапшот генерируются из тех же деклараций, что и API.

## Контекст

**Situation.** В коде API пока нет: отсутствуют `routes/api.php`, API-контроллеры, Sanctum и Scribe. Существующий вариант этого RFC предлагал ручные CRUD-контроллеры, Form Requests, `App\Http\Resources\ItemResource` и Scribe; `PRODUCT.md` по-прежнему декларирует Public API.

**Complication.** Ручной подход дублирует описание одной модели в контроллерах, запросах, ресурсах и документации. Для поиска, вложенных изменений и действий пришлось бы отдельно поддерживать контракты и их схемы. Кроме того, установлен Laravel `13.12.0`, а Lomkit Rest API 3 требует PHP 8.3+ и Laravel `13.30+`.

**Question.** Как получить единый подход к моделям и REST-ресурсам, поиск, транзакционные изменения, действия и актуальную документацию без параллельных описаний контракта?

**Answer.** До начала реализации переносим RFC на декларативные ресурсы Lomkit и Scramble, обновляем Laravel и используем штатные пакетные форматы запросов и ответов. Поскольку прежнего API в коде нет, слой совместимости с ручными CRUD-маршрутами не нужен.

`docs/specs/` в проекте отсутствует; исходным состоянием служат код, миграции и текущий RFC. Другие активные RFC не регистрируют API и не конфликтуют с этим решением.

## Назначение и цели

- Описывать API-модель один раз: typed fields, связи, правила и действия находятся в REST-ресурсе, а не распределены по контроллерам и Form Requests.
- Дать клиентам единый поиск с текстовым запросом, фильтрами, сортировкой, includes, агрегатами и пагинацией.
- Поддержать создание и обновление через транзакционный `mutate`, мягкое удаление и типизированные действия над выбранными items.
- Получать интерактивную документацию и OpenAPI автоматически из тех же деклараций, исключая дрифт документации.
- Сохранить принятые доменные решения: один `Item` для задач и контейнеров, независимые `type`, `is_epic` и `parent_id`, целостность иерархии, Policy и token abilities.

Заинтересованные стороны: разработчики AgileTracker и будущих клиентов API, авторы интеграций, владелец продукта и ревьюеры архитектуры. Изменение задаёт базовый шаблон для следующих REST-ресурсов приложения.

## Составляющие и особенности

### 1. Платформа API, маршруты и безопасность

- В `composer.json` повышаем ограничение до `laravel/framework:^13.30` и обновляем lockfile. Добавляем production-зависимости `lomkit/laravel-rest-api:^3.0`, `dedoc/scramble:^0.13.48` и `laravel/sanctum`; Scribe не устанавливаем.
- Регистрируем `routes/api.php` через `php artisan install:api`, добавляем `HasApiTokens` в `User`. В `bootstrap/app.php` сохраняем JSON-ошибки для `api/*`.
- Группа `Route::prefix('v1')` использует `auth:sanctum` и `throttle:api`. Именованный limiter в `AppServiceProvider` ограничивает API 60 запросами в минуту по user ID, для гостя — по IP.
- Регистрируем `Rest::resource('items', ItemsController::class)->except('metric')`. Включаются `details`, `search`, `mutate`, `operate`, `destroy`; metrics, restore и force delete не публикуются. Отдельные маршруты projects/epics и традиционные `Route::apiResource()` отсутствуют.
- `details` и `search` требуют ability `items:read`; `mutate`, `operate` и `destroy` — `items:write`. Middleware назначаются на соответствующие endpoints ресурса. Policy проверяются независимо от token ability: токен не заменяет авторизацию модели.
- Пакетный `EnforceExpectsJson` и существующий exception handler возвращают JSON для 401/403/404/409/422/429. Отсутствие токена даёт 401, недостаточная ability или отказ Policy — 403, превышение limiter — 429.

### 2. Доменная модель и единый REST-ресурс

- Новая миграция добавляет `items.is_epic`: non-null boolean, `default(false)`, составной индекс `(is_epic, id)`; `down()` удаляет индекс и колонку. Существующие записи получают `false`.
- `Item` получает `is_epic` в `$fillable`, boolean cast и в список activity log. `ItemFactory` получает значение `false` по умолчанию и state `epic()`. Признак не заменяет `type` и не меняет `parent_id` или children.
- Создаются базовые `App\Rest\Resources\Resource` и `App\Rest\Controllers\Controller`, тонкий `ItemsController` и `App\Rest\Resources\ItemResource`. Этот класс не смешивается с `App\Filament\Resources\ItemResource`; `App\Http\Resources\ItemResource` не создаётся.
- `ItemResource::$model = Item::class`. Whitelist полей: `id`, `title`, `description`, `type`, `is_epic`, `parent_id`, `assignee_id`, readonly `reporter_id`, `due_date`, readonly timestamps. Недекларированные поля, включая произвольный `meta`, клиенты читать и изменять не могут.
- `type` — virtual Text field: читается из `meta.type`, записывается через `fillUsing()` с сохранением остальных ключей `meta`, фильтруется через `meta->type`. Допустимы `task|bug|feature`; на create поле обязательно, на update — `filled`. Старые записи без `meta.type` возвращают `null`, поэтому read-схема допускает nullable.
- `parent`, `children`, `assignee`, `reporter` — read-only relations. `labels` разрешает только `attach`, `detach`, `sync`: создавать или изменять labels через item нельзя. Вложенные create/update/delete items через `parent`/`children` запрещены; положение в иерархии меняется только валидируемым `parent_id`.
- Для связей создаются `UserResource` (`id`, `name`) и `LabelResource` (`id`, `name`, `color`), но собственные контроллеры и маршруты users/labels не регистрируются. Эти ресурсы обслуживают только includes, relation filters и проверки связей.
- `ItemPolicy` реализует `viewAny`, `view`, `create`, `update`, `delete`; связанные policies разрешают необходимые `viewAny`/`view`. В текущей доменной модели аутентифицированный пользователь может работать со всеми записями, поэтому ресурсы явно помечаются `#[WithoutPerimeter]`; будущие visibility-правила должны переехать в `perimeterQuery()`.
- `rest:check` выполняется после миграций и подтверждает наличие auth middleware, policies, безопасных relations и row check у действия. В тестах включается `REST_AUTHORIZATIONS_STRICT=true`.

### 3. Детали ресурса и поиск

- `GET /api/v1/items` — пакетный `details`: описание полей, операторов, правил, limits, relations и actions для текущего пользователя. Это не list endpoint.
- `POST /api/v1/items/search` — единственная операция чтения rows. Отдельных `GET /items/{id}` и `GET /items/search` нет; одна запись получается фильтром `id = ...` в том же search.
- `text()` использует `Contains::make()`; `title` и `description` помечаются `searchable()`. Поддерживаются режимы `words` и `phrase`, максимум 255 символов. Пустой `text` не сужает выдачу; `%`, `_` и `\` трактуются буквально. Scout и FULLTEXT не добавляются.
- Декларируются фильтры `is_epic`, `type`, `assignee_id`, `labels.name`, а также штатные фильтры остальных разрешённых полей. Без фильтров возвращаются все items независимо от `parent_id` и `is_epic`.
- Search поддерживает `selects`, `sorts`, `includes`, `aggregates`, `gates`, `page`, `limit`. Количество children запрашивается aggregate `children:count`, а не отдельным постоянным атрибутом. Includes используют только описанные ресурсы и их собственные policies.
- `$defaultLimit = 15`, разрешённые limits — `[15, 25, 50, 100]`. Используется пакетная page-пагинация с `current_page`, `data`, `from`, `last_page`, `per_page`, `to`, `total`, `meta`; cursor-пагинации и прежних `links` нет. Сортировка по умолчанию — `id DESC`, а пакет добавляет ключ для детерминированного порядка при пользовательской сортировке.
- Soft-deleted records по умолчанию исключены. Хотя пакет поддерживает `search.trashed` для SoftDeletes, `searching()` явно отклоняет этот параметр с 422: чтение удалённых записей не входит в контракт v1.
- Пустой результат возвращает 200 с пустым `data`. Неизвестные поля, операторы, relations и недопустимый limit возвращают 422 с точным payload path.

### 4. Мутации, удаление и действие назначения исполнителя

- `POST /api/v1/items/mutate` принимает список `create`/`update`. Одна операция или batch выполняются в одной транзакции; ответ 200 содержит `operations`. Опциональный `response` возвращает записи после commit в формате search; прежние статусы 201 для create и PUT/PATCH-контракт не используются.
- `title` и `type` обязательны на create; `title` ограничен 255 символами. Разрешены только декларированные writable fields. `parent_id` и `assignee_id` nullable и ссылаются на существующие записи; parent дополнительно должен быть неудалённым. Labels синхронизируются пакетной relation-операцией `sync` с проверкой ID.
- `mutating()` при create заполняет readonly `reporter_id` текущим пользователем. При изменении `parent_id` hook отклоняет ссылку на сам item или его потомка с 422. Проверка выполняется до сохранения; при ошибке вся мутация откатывается.
- Изменение `is_epic` не меняет `type`, `parent_id` или children. Eloquent и существующий `spatie/laravel-activitylog` продолжают фиксировать изменения модели; отдельный API activity log не создаётся.
- `DELETE /api/v1/items` принимает `{"resources":[...]}` и выполняет soft delete в одной транзакции, возвращая 200 с изменёнными rows. `destroying()` отклоняет удаление item с активными children через 409; каскадного удаления и автоматического detach нет. Restore, force delete и чтение trashed не регистрируются.
- Первый доменный action — `AssignItems` с URI key `assign`: `POST /api/v1/items/actions/assign`. Он массово назначает или снимает исполнителя через nullable `assignee_id` с `exists:users,id`.
- Action использует `ActionTarget::Resources`, поэтому обязателен явный список ID и запрещён `search`: случайно затронуть всю выдачу нельзя. `$maxResources = 100`, `authorizeEach() = update`, атомарная транзакция на весь список. Успешный ответ — 200 с `data.impacted`; ошибка любой записи откатывает всё действие.
- Поля action, target, ограничения и результат декларируются в `actions()` ресурса и автоматически попадают в `details` и OpenAPI. Неизвестный action для пользователя, прошедшего `viewAny`, возвращает 404.

### 5. Автоматическая OpenAPI-документация и проверка контракта

- Scramble остаётся production-зависимостью, поскольку пакет регистрирует интерактивную документацию `/docs/api`. Дополнительная ручная регистрация операций Lomkit не требуется.
- В document transformer Scramble добавляется HTTP Bearer security scheme. Документация автоматически описывает `details`, `search`, `mutate`, `assign`, `destroy`, typed fields, filters, relations, limits, validation и пакетные ошибки 401/403/404/422. Доменные 409 для delete и hierarchy дополняются небольшим operation transformer без ручного дублирования основных схем.
- Полям и action inputs задаются `examples()`, чтобы генерируемые примеры проходили правила. `type` документирует допустимые значения, `is_epic` — boolean, dates — date, labels и users — связанные схемы.
- `php artisan scramble:export --path=tests/api.json` создаёт OpenAPI 3.1.0 snapshot. Файл коммитится; CI повторно экспортирует спецификацию и падает на diff. Это заменяет Scribe HTML и отдельную Postman-коллекцию: тот же OpenAPI можно импортировать в Postman.
- Feature-тесты выполняют реальные HTTP-запросы ко всем операциям и сверяют ответы с контрактом. Отдельный snapshot-тест проверяет наличие paths, schemas и bearer security. `WithCachedRoutes` добавляется в базовый `TestCase` по рекомендации Lomkit.
- Готовность подтверждается `php artisan rest:check`, полным `php artisan test`, `./vendor/bin/pint` и повторным экспортом документации без изменений.

### Ограничения и out of scope

- Отдельные Project/Epic-модели, ресурсы и маршруты; собственные REST endpoints users/labels/comments.
- Традиционные list/show/store/update endpoints и поддержка старого формата ответов из предыдущего варианта RFC.
- Restore/force delete, чтение trashed, metrics, queued/batched actions, вложенное создание дерева items.
- Laravel Scout, Meilisearch и FULLTEXT; UI выпуска/отзыва токенов; OAuth2, webhooks и публичные SDK.
- Миграция существующих внешних клиентов: на момент RFC публичного API и клиентов его контракта нет.

## Критерии приемки (checklist)

### 1. Платформа API, маршруты и безопасность

- [ ] `composer.json` и lockfile содержат Laravel `^13.30` или новее, Lomkit 3.x, Scramble `^0.13.48` и Sanctum; Scribe отсутствует.
- [ ] `route:list` показывает только пакетные `details`, `search`, `mutate`, `operate`, `destroy` под `/api/v1/items`; metrics, restore, force, projects/epics и `apiResource` отсутствуют.
- [ ] Без токена API возвращает JSON 401; без `items:read`/`items:write` или при отказе Policy — JSON 403; 61-й запрос за минуту получает JSON 429.

### 2. Доменная модель и единый REST-ресурс

- [ ] Миграция добавляет non-null `is_epic=false` и индекс `(is_epic, id)`, корректно откатывается; существующие записи получают `false`, factory имеет state `epic()`.
- [ ] Единый `App\Rest\Resources\ItemResource` возвращает hybrid `type=task` + `is_epic=true`; `type` сохраняется в `meta.type` без потери других meta keys, `reporter_id` нельзя записать через API.
- [ ] Недекларированные поля и произвольный `meta` отклоняются с 422; relations users/labels работают без отдельных публичных маршрутов; `php artisan rest:check` завершается успешно.

### 3. Детали ресурса и поиск

- [ ] `GET /api/v1/items` возвращает details с полями, правилами, relations, limits и `assign`; `POST /api/v1/items/search` без фильтров возвращает все неудалённые items с 200.
- [ ] Text search по title/description, режимы words/phrase и фильтры `is_epic`, `type`, `assignee_id`, `labels.name` работают совместно; `%`, `_`, `\` не становятся wildcards.
- [ ] Selects, includes, children count aggregate, сортировка и limits `[15,25,50,100]` соответствуют декларациям; страницы при `id DESC` не содержат дублей или пропусков.
- [ ] Soft-deleted records исключены, `search.trashed` даёт 422; пустой результат даёт 200 с `data: []`; неизвестные поля/relations и неверный limit дают 422 с точным ключом ошибки.

### 4. Мутации, удаление и действие назначения исполнителя

- [ ] `mutate` создаёт и обновляет один или несколько items за транзакцию, возвращает 200 `operations` и опциональные `records`; create требует title/type, reporter заполняется текущим пользователем.
- [ ] Labels можно attach/detach/sync только существующими ID; parent/children нельзя изменять вложенными операциями; массовое присваивание вне whitelist невозможно.
- [ ] `parent_id` на сам item или потомка возвращает 422 и откатывает всю мутацию; изменение `is_epic` не меняет иерархию и type.
- [ ] `DELETE` мягко удаляет leaf items и возвращает 200; item с активными children даёт 409 без изменений; soft-deleted item недоступен для mutate/delete.
- [ ] `POST /api/v1/items/actions/assign` меняет или очищает assignee только для явно перечисленных ID, возвращает корректный `impacted`, проверяет `update` каждой строки и откатывает весь список при ошибке; `search` в action и более 100 ID отклоняются.

### 5. Автоматическая OpenAPI-документация и проверка контракта

- [ ] `/docs/api` показывает актуальные `details`, `search`, `mutate`, `assign`, `destroy` с bearer auth, схемами полей/relations/actions и ошибками; схема соответствует реальным ответам feature-тестов.
- [ ] `php artisan scramble:export --path=tests/api.json` создаёт OpenAPI 3.1.0; повторный экспорт в CI не создаёт diff, изменение ресурса без обновления snapshot ломает проверку.
- [ ] `php artisan test` зелёный, включая search/mutate/action/delete/docs tests и strict authorization; `./vendor/bin/pint` не производит изменений.

## Дорожная карта

1. **Платформа API, маршруты и безопасность.** Обновить Laravel, установить Lomkit/Scramble/Sanctum, зарегистрировать API routes, abilities и rate limiter.
2. **Доменная модель и единый REST-ресурс.** Добавить `is_epic`, описать Item/User/Label resources, relations, policies и проверки `rest:check`.
3. **Детали ресурса и поиск.** Настроить Contains, filters/includes/aggregates, pagination и запрет trashed; покрыть read contract тестами.
4. **Мутации, удаление и действие назначения исполнителя.** Реализовать mutate hooks, инварианты иерархии, delete guard и `AssignItems`; проверить транзакционность и permissions.
5. **Автоматическая OpenAPI-документация и проверка контракта.** Настроить bearer scheme и examples, экспортировать snapshot, добавить contract/docs tests и CI-проверки.

## Итого и рекомендации

Реализуем API v1 сразу на Lomkit Rest API 3 и Scramble, не создавая ручной CRUD и Scribe. `ItemResource` становится единственным источником API-контракта модели: из него работают поиск, транзакционные мутации, assign action, авторизация и автоматическая документация. Доменные правила прежнего RFC сохраняются, а пакетные request/response formats принимаются как стандарт последующих REST-ресурсов AgileTracker.

## Тест инструкции

<!-- Раздел заполняет dd-delivery по итогам реализации: каждый шаг проверки — заголовок ### со списком ссылок на тесты, страницы, эндпоинты и команды. На этапе discovery раздел не заполняется. -->

## Дополнения

### Пример поиска `POST /api/v1/items/search`

```json
{
  "search": {
    "text": {"value": "биллинг", "mode": "words"},
    "filters": [
      {"field": "is_epic", "operator": "=", "value": true},
      {"field": "type", "operator": "=", "value": "task"}
    ],
    "includes": [
      {"relation": "labels", "selects": [{"field": "name"}]}
    ],
    "aggregates": [{"relation": "children", "type": "count"}],
    "sorts": [{"field": "id", "direction": "desc"}],
    "limit": 15
  }
}
```

### Пример создания `POST /api/v1/items/mutate`

```json
{
  "mutate": [
    {
      "operation": "create",
      "attributes": {
        "title": "Развитие биллинга",
        "description": "Проект по развитию биллинга",
        "type": "task",
        "is_epic": true,
        "parent_id": null,
        "assignee_id": 3,
        "due_date": "2026-11-20"
      },
      "relations": {
        "labels": [{"operation": "sync", "key": [1, 2]}]
      }
    }
  ],
  "response": {
    "selects": [
      {"field": "id"},
      {"field": "title"},
      {"field": "type"},
      {"field": "is_epic"}
    ]
  }
}
```

### Пример действия `POST /api/v1/items/actions/assign`

```json
{
  "resources": [7, 8, 9],
  "fields": [{"name": "assignee_id", "value": 3}]
}
```

Ответ: `200` с `{"data":{"impacted":3,"result":null}}`, если все три записи прошли проверки и были обработаны.

### Альтернативные варианты

**Собственные контроллеры + Laravel API Resources + Scribe.** Даёт привычные CRUD-маршруты и свободу форматов, но дублирует fields/rules/relations в нескольких классах и требует ручной поддержки документации. Отклонён до реализации.

**Design-first `openapi.yaml`.** Обеспечивает независимый контракт, но требует отдельной синхронизации схемы с кодом. Остаётся fallback, если декларативный подход не покроет доменные требования.

**Lomkit + Scramble — рекомендуется.** Единые typed declarations одновременно управляют поиском, записями, действиями, валидацией, policies и OpenAPI. Компромисс — собственный формат пакета: search и mutate используют POST JSON, page-пагинацию и нестандартные CRUD-ответы; это принимается как публичный контракт v1.

### Открытые вопросы

- Перед production-релизом отдельно определить политику доступа к UI `/docs/api`: публичный просмотр или ограничение через стандартные middleware/gate Scramble. На генерацию и API-контракт это не влияет.
- При росте объёма данных оценить переход с `Contains` на `FullText`/Scout и необходимые индексы отдельным RFC; текущий поиск не зависит от внешнего search engine.

### Источники

- Lomkit introduction: https://laravel-rest-api.lomkit.com/getting-started/introduction
- Installation и требования: https://laravel-rest-api.lomkit.com/getting-started/installation
- Resources и routes: https://laravel-rest-api.lomkit.com/resources/basics
- Search: https://laravel-rest-api.lomkit.com/endpoints/search
- Text search: https://laravel-rest-api.lomkit.com/search/text-search
- Mutate: https://laravel-rest-api.lomkit.com/writing/mutate
- Actions: https://laravel-rest-api.lomkit.com/endpoints/actions
- Relations: https://laravel-rest-api.lomkit.com/resources/relations
- Events and hooks: https://laravel-rest-api.lomkit.com/digging-deeper/events-and-hooks
- OpenAPI: https://laravel-rest-api.lomkit.com/digging-deeper/openapi
- Scramble export: https://scramble.dedoc.co/usage/export

## Заметки

replace api package to https://laravel-rest-api.lomkit.com/

https://laravel-rest-api.lomkit.com/getting-started/introduction

https://laravel-rest-api.lomkit.com/getting-started/installation

цели
- единый подход к rest api и моделям и ресурсам
- поиск, экшены и https://laravel-rest-api.lomkit.com/writing/mutate

а и авто документацию хочется - чтобы итоговый апи имел авто доки
