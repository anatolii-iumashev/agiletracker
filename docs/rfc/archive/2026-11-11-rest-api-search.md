---
title: "REST API на Lomkit: Items, Users, Labels, Comments и OpenAPI"
status: done
created: 2026-11-11
completed: 2026-11-13
---

# RFC: REST API на Lomkit — Items, Users, Labels, Comments и автодокументация

## TL DR

Заменяем ещё не реализованную связку собственных CRUD-контроллеров, Laravel API Resources и Scribe на `lomkit/laravel-rest-api` 3.x с OpenAPI через Scramble. Публикуем четыре связанных REST-ресурса — `ItemResource`, `UserResource`, `LabelResource`, `CommentResource` — с едиными `details`, `search`, `mutate` и разрешёнными для каждого `destroy`/actions. Сохраняем доменную модель с `is_epic` и `meta.type`; интерактивная документация `/docs/api` и OpenAPI-снапшот генерируются из тех же деклараций, что и API.

## Контекст

**Situation.** В коде API пока нет: отсутствуют `routes/api.php`, API-контроллеры и Sanctum. Проверка Composer, lockfile, конфигурации и public-артефактов подтверждает, что Scribe не установлен. `PRODUCT.md` декларирует Public API, а модели `Item`, `User`, `Label`, `Comment` уже связаны через Eloquent relations.

**Complication.** Ручной подход дублирует описание моделей в контроллерах, Form Requests, API Resources и документации. Одного Item API недостаточно для управления основными сущностями; при этом метрики в текущей постановке не нужны. Установлен Laravel `13.12.0`, а Lomkit Rest API 3 требует PHP 8.3+ и Laravel `13.30+`.

**Question.** Как получить единый подход к нескольким моделям и REST-ресурсам, поиск, транзакционные изменения, действия и актуальную документацию без параллельных описаний контракта?

**Answer.** До начала реализации переносим RFC на четыре декларативных ресурса Lomkit и Scramble, обновляем Laravel и используем штатные пакетные форматы запросов и ответов. Поскольку прежнего API в коде нет, слой совместимости с ручными CRUD-маршрутами не нужен.

`docs/specs/` отсутствует; исходным состоянием служат код, миграции и текущий RFC. Другие активные RFC не регистрируют API и не конфликтуют с этим решением.

## Назначение и цели

- Описывать каждую API-модель один раз: typed fields, связи, правила и действия находятся в REST-ресурсе, а не распределены по контроллерам и Form Requests.
- Дать Items, Users, Labels и Comments единый поиск с текстовым запросом, фильтрами, сортировкой, includes, агрегатами и пагинацией.
- Поддержать создание и обновление через транзакционный `mutate`, мягкое удаление Item/Comment, удаление Label и типизированный action для Items.
- Разграничить операции по token abilities и policies, включая admin-only управление пользователями и авторские правила комментариев.
- Получать интерактивную документацию и OpenAPI автоматически из тех же деклараций, исключая дрифт документации.
- Сохранить доменные решения Item: один тип модели для задач и контейнеров, независимые `type`, `is_epic`, `parent_id`, целостность иерархии.

Заинтересованные стороны: разработчики AgileTracker и клиентов API, авторы интеграций, владелец продукта и ревьюеры архитектуры. Эти четыре ресурса задают шаблон для следующих REST-ресурсов приложения.

## Составляющие и особенности

### 1. Платформа, общие контракты и безопасность

- В `composer.json` повышаем ограничение до `laravel/framework:^13.30`, обновляем lockfile и добавляем production-зависимости `lomkit/laravel-rest-api:^3.0`, `dedoc/scramble:^0.13.48`, `laravel/sanctum`. Scribe не устанавливаем; его пакет, конфиги, routes и generated assets должны отсутствовать.
- Регистрируем `routes/api.php` через `install:api`, добавляем `HasApiTokens` в `User`, сохраняем JSON-ошибки для `api/*`. Группа `/api/v1` использует `auth:sanctum` и `throttle:api` — 60 запросов в минуту по user ID, для гостя по IP.
- Создаются базовые `App\Rest\Resources\Resource`, `App\Rest\Controllers\Controller` и четыре тонких контроллера. REST-классы не смешиваются с одноимёнными Filament Resources; `App\Http\Resources` не используется.

| Ресурс | Базовый URI | Разрешённые пакетные endpoints | Abilities |
|---|---|---|---|
| Items | `/api/v1/items` | `details`, `search`, `mutate`, `operate`, `destroy` | `items:read`, `items:write` |
| Users | `/api/v1/users` | `details`, `search`, `mutate` | `users:read`, `users:write` |
| Labels | `/api/v1/labels` | `details`, `search`, `mutate`, `destroy` | `labels:read`, `labels:write` |
| Comments | `/api/v1/comments` | `details`, `search`, `mutate`, `destroy` | `comments:read`, `comments:write` |

- Routes регистрируются через `Rest::resource(...)->only(...)`: неперечисленные endpoints не существуют. Metrics, restore, force delete и традиционные `Route::apiResource()` не публикуются; operate есть только у Items.
- Read endpoints требуют `<resource>:read`, mutate/operate/destroy — `<resource>:write`. Token ability не заменяет Policy. Без токена — JSON 401, без ability или при отказе Policy — 403, превышение limiter — 429.
- `GET /{resource}` возвращает details, `POST /{resource}/search` — rows. Традиционных list/show нет: одна запись получается фильтром `id = ...`. Пустой результат — 200 с `data: []`.
- Каждый ресурс объявляет `Contains::make()` и searchable Text fields. Режимы `words`/`phrase`, максимум 255 символов; `%`, `_`, `\` буквальны. Поддерживаются декларированные filters, selects, sorts, includes, aggregates, gates, page, limit. Scout/FULLTEXT не добавляются.
- Базовый Resource задаёт `$defaultLimit = 15` и limits `[15,25,50,100]`. Ответ использует page-пагинацию Lomkit; порядок по умолчанию `id DESC`, при пользовательской сортировке пакет добавляет ключ для детерминированности.
- `POST /{resource}/mutate` выполняет create/update в одной транзакции и возвращает 200 `operations`; опциональный `response` возвращает записи после commit. `DELETE /{resource}` принимает `resources` и возвращает 200 с rows, а не 204.
- `searching()` в базовом Resource отклоняет `search.trashed` с 422. Soft-deleted Items/Comments исключены из поиска и записи; restore/force delete отсутствуют. Неизвестные поля, relations и операторы дают 422 с точным payload path.
- `rest:check` обязателен после миграций; в тестах включаются `REST_AUTHORIZATIONS_STRICT=true` и `WithCachedRoutes`.

### 2. ItemResource

- Новая миграция добавляет `items.is_epic`: non-null boolean, `default(false)`, индекс `(is_epic, id)`; `down()` удаляет индекс и колонку. `Item` получает `$fillable`, boolean cast и activity log для `is_epic`; `ItemFactory` — `false` по умолчанию и state `epic()`.
- Поля: `id`, `title`, `description`, `type`, `is_epic`, `parent_id`, `assignee_id`, readonly `reporter_id`, `due_date`, readonly timestamps. Произвольный `meta` и недекларированные колонки недоступны.
- `type` — virtual Text field: читается из `meta.type`, пишется через `fillUsing()` с сохранением остальных meta keys, фильтруется через `meta->type`. Допустимы `task|bug|feature`; create требует значение, update — `filled`. Для старых записей без типа read-схема допускает null.
- Searchable: `title`, `description`. Filters: `is_epic`, `type`, `assignee_id`, `labels.name` и штатные фильтры остальных разрешённых полей. Children count получается aggregate `children:count`.
- `parent`, `children`, `assignee`, `reporter`, `comments` — read-only relations. `labels` принимает только `attach`, `detach`, `sync`; создавать или редактировать labels через Item нельзя. Вложенные writes через parent/children запрещены, иерархия меняется только `parent_id`.
- `ItemPolicy`: `viewAny`, `view`, `create`, `update`, `delete` разрешены аутентифицированным пользователям в текущей доменной модели. Ресурс явно `#[WithoutPerimeter]`; будущие visibility-правила переносятся в `perimeterQuery()`.
- На create обязательны `title` и `type`; title max 255. `parent_id` и `assignee_id` nullable и ссылаются на существующие записи; parent должен быть неудалённым. `mutating()` заполняет readonly `reporter_id` текущим пользователем.
- При изменении `parent_id` hook возвращает 422 для ссылки на сам item или потомка и откатывает всю мутацию. Смена `is_epic` не меняет `type`, `parent_id` или children. Eloquent продолжает запускать activity log.
- `DELETE /items` мягко удаляет записи транзакционно. `destroying()` возвращает 409 при активных children; каскадного удаления/detach нет. Комментарии остаются в БД, но исключаются вместе с удалённым parent через perimeter CommentResource.
- Единственный начальный action — `AssignItems`, `$uriKey = 'assign'`: `POST /items/actions/assign`. Nullable `assignee_id` с `exists:users,id` назначает или снимает исполнителя.
- Action использует `ActionTarget::Resources`, `$maxResources = 100`, `authorizeEach() = update`, `ActionTransaction::Whole`; `search` запрещён. Ответ 200 содержит `data.impacted`, ошибка любой строки откатывает весь список. Неизвестный action после `viewAny` — 404.

### 3. UserResource

- Поля: `id`, `name`, `email`, writeOnly `password`, readonly timestamps. `remember_token`, `email_verified_at`, tokens, password и session data никогда не возвращаются; password нельзя фильтровать или сортировать.
- Searchable: `name`, `email`; filters включают `name`, `email`, `id`. Read-only relations: `assignedItems`, `reportedItems`, `comments`. Роли и permissions в API v1 не экспонируются.
- Create требует `name`, уникальный `email`, `password` минимум 12 символов; hashed cast модели выполняет хеширование. Update разрешает name/email и необязательный password; unique email игнорирует текущую запись через `Contextual::rule()`.
- `UserPolicy`: `viewAny`/`view` разрешены аутентифицированным пользователям, `create`/`update` — только роли `admin`. Поэтому `users:write` необходим, но недостаточен без admin. Ресурс `#[WithoutPerimeter]`.
- `DELETE /users` не регистрируется: удаление пользователей, перенос их items/comments и отзыв tokens требуют отдельного lifecycle-решения. Назначение ролей также не входит в этот RFC.

### 4. LabelResource

- Поля: `id`, `name`, `color`, readonly timestamps; searchable `name`; read-only relation `items`. Ресурс `#[WithoutPerimeter]`.
- Create требует `name` max 50. `color` необязателен на create из-за DB default, а при передаче проверяется формат `#RRGGBB`; update использует `filled` для обоих полей.
- `LabelPolicy` разрешает read/write аутентифицированным пользователям. `DELETE /labels` удаляет label; существующий `item_label` cascade удаляет только связи, сами items не изменяются.
- Обратная relation `items` read-only: изменение состава labels выполняется только через `ItemResource.labels`, что исключает два конкурирующих write API.

### 5. CommentResource

- Поля: `id`, `body`, create-only `item_id`, readonly `user_id`, readonly timestamps. Searchable `body`; filters включают `item_id`, `user_id`, `id`; read-only relations `item`, `author`.
- Create требует `body` и существующий неудалённый `item_id`. `mutating()` заполняет `user_id` текущим пользователем; клиент не может подменить автора. Update разрешает только `body` с `filled`; item и автор неизменны.
- `CommentPolicy`: `viewAny`/`view` разрешены аутентифицированным пользователям, `create` — при доступном parent Item, `update`/`delete` — автору комментария или `admin`. `comments:write` обязателен для всех writes.
- `perimeterQuery()` ограничивает выдачу `whereHas('item')`: комментарии soft-deleted Item не возвращаются, не изменяются и не удаляются через API. Они остаются в БД для возможного будущего restore родителя.
- `DELETE /comments` выполняет soft delete в одной транзакции. `search.trashed`, restore и force delete запрещены общим контрактом.

### 6. Автоматическая OpenAPI-документация и проверка контракта

Scramble выбран потому, что это единственный штатный способ Lomkit 3 автоматически строить OpenAPI из деклараций REST-ресурсов. Собственный генератор документации удалён из Lomkit 3, а поставляемые трансформеры Scramble используют те же fields, relations, filters, actions, limits и gates, что и работающий API. Это исключает параллельное описание контракта и соответствует исходной цели автодокументации.

Scribe не является технически несовместимым с Lomkit, но готовой интеграции с Lomkit Resource у него нет. Scribe умеет извлекать ответы из Laravel API Resources (`JsonResource`), а не из деклараций `App\Rest\Resources\Resource`; схемы search/mutate, relations, filters и actions пришлось бы дублировать аннотациями либо поддерживать собственный адаптер. Поэтому Scribe не удовлетворяет требованию единого источника контракта без дополнительной разработки.

- Scramble — production-зависимость: интерактивная документация доступна на `/docs/api`, ручная регистрация операций Lomkit не требуется.
- Document transformer добавляет HTTP Bearer security scheme. Схемы автоматически покрывают все четыре ресурса, их details/search/mutate, разрешённые destroy, `assign`, typed fields, filters, relations, limits, validation и пакетные ошибки. Доменные 409 Item delete/hierarchy дополняются небольшим operation transformer.
- `examples()` задаются полям и action inputs. Password схематично обозначен writeOnly; запрещённые relations и endpoints не попадают в документацию.
- `php artisan scramble:export --path=tests/api.json` создаёт OpenAPI 3.1.0 snapshot. Файл коммитится; CI повторно экспортирует спецификацию и падает на diff. Scribe HTML и отдельная Postman-коллекция не нужны: OpenAPI можно импортировать в Postman.
- Feature-тесты выполняют реальные HTTP-запросы для каждого ресурса, связей, policies, abilities и транзакций, сверяя ответы со схемами. Snapshot-тест проверяет paths, component schemas и bearer security.
- Готовность подтверждается `php artisan rest:check`, полным `php artisan test`, `./vendor/bin/pint` и повторным экспортом без изменений.

### Ограничения и out of scope

- Метрики, queued/batched actions, restore/force delete, чтение trashed, вложенное создание дерева Items.
- Отдельные Project/Epic-модели и маршруты; REST Resources для Favorites и других моделей сверх четырёх перечисленных.
- Удаление Users, назначение ролей/permissions, управление tokens через API, email verification flow.
- Традиционные list/show/store/update endpoints и совместимость с форматами предыдущего варианта RFC.
- Laravel Scout, Meilisearch, FULLTEXT; OAuth2, webhooks, публичные SDK.

## Критерии приемки (checklist)

### 1. Платформа, общие контракты и безопасность

- [x] Зависимости соответствуют Laravel `^13.30+`, Lomkit 3.x, Scramble `^0.13.48`, Sanctum; Scribe отсутствует в lockfile, конфигах, routes и assets.
- [x] `route:list` содержит ровно endpoints из таблицы для items/users/labels/comments; metrics, restore, force, users destroy и actions вне Items отсутствуют.
- [x] Общие search/mutate/delete форматы, Contains, limits, pagination, запрет trashed и JSON 401/403/422/429 подтверждены тестами; `rest:check` зелёный.

### 2. ItemResource

- [x] Миграция `is_epic` обратно совместима и откатывается; hybrid `type=task + is_epic=true` доступен через тот же ресурс, type сохраняется в `meta.type` без потери других keys.
- [x] Search работает по title/description и filters; relations и children count не вызывают N+1; недекларированные поля/meta и вложенные parent/children writes дают 422.
- [x] Create/update заполняют reporter текущим пользователем, валидируют поля и labels, запрещают циклы parent_id с откатом всей мутации; `is_epic` не меняет иерархию.
- [x] Delete leaf Item мягкий и возвращает 200; активные children дают 409 без изменений. Assign меняет/очищает assignee максимум для 100 явных ID, проверяет update и атомарно возвращает `impacted`.

### 3. UserResource

- [x] Users search/details работают с `users:read`; create/update требуют и `users:write`, и admin. Обычный пользователь с write ability получает 403.
- [x] Email уникален с корректным ignore при update, password обязателен на create и хешируется; password/tokens/remember_token невозможно прочитать, отфильтровать или вернуть через selects.
- [x] `DELETE /users` и role assignment routes/relations отсутствуют; assignedItems/reportedItems/comments доступны только как read-only includes.

### 4. LabelResource

- [x] Labels поддерживают details/search/mutate/delete с соответствующими abilities; name/color валидируются, read-only items include работает.
- [x] Удаление Label очищает только `item_label` связи и не удаляет Items; состав labels изменяется только через ItemResource.

### 5. CommentResource

- [x] Create требует body/item_id и заполняет автором текущего пользователя; update меняет только body и разрешён автору или admin; чужой пользователь без admin получает 403.
- [x] Search фильтрует по item/user и включает item/author; comments удалённого Item исключены из search/mutate/delete.
- [x] Delete автора/admin выполняет soft delete с 200; `comments:write` и Policy проверяются независимо, trashed/restore/force отсутствуют.

### 6. Автоматическая OpenAPI-документация и проверка контракта

- [x] `/docs/api` содержит все разрешённые операции четырёх ресурсов и `assign`, bearer auth, схемы полей/relations/actions и ошибки; запрещённые endpoints отсутствуют.
- [x] `php artisan scramble:export --path=tests/api.json` создаёт OpenAPI 3.1.0; повторный экспорт не создаёт diff, а изменение ресурса без snapshot ломает CI.
- [x] `php artisan test` зелёный, включая все resource/authorization/docs tests и strict authorization; `./vendor/bin/pint` не производит изменений.

## Дорожная карта

1. **Платформа, общие контракты и безопасность.** Обновить Laravel, установить пакеты, создать базовые классы, routes, abilities, limiter и общие search/mutate правила.
2. **ItemResource.** Добавить `is_epic`, поля/relations, hooks, delete guard, `AssignItems` и тесты доменных инвариантов.
3. **UserResource.** Реализовать безопасные поля, admin policies и user mutations без удаления и ролей.
4. **LabelResource.** Реализовать CRUD, read-only items relation и проверку pivot cascade.
5. **CommentResource.** Реализовать авторство, author/admin policies, parent perimeter и soft delete; связать с Item/User includes.
6. **Автоматическая OpenAPI-документация и проверка контракта.** Настроить bearer/examples, snapshot, contract tests и CI-проверки всех ресурсов.

## Итого и рекомендации

Реализуем API v1 сразу на Lomkit Rest API 3 и Scramble для Items, Users, Labels и Comments, не создавая ручной CRUD, Scribe и метрики. Каждый REST-ресурс становится единственным источником контракта своей модели, а общие базовые классы фиксируют одинаковые поиск, мутации, авторизацию и документацию. Различия остаются только в полях, relations и доменных policies.

## Тест инструкции

Каждый шаг ниже проверяется на чистом окружении после `composer install`, `php artisan migrate` и `npm run build`.

### 1. Автотесты

- [tests/Feature/Api/ApiFoundationTest.php](../../../tests/Feature/Api/ApiFoundationTest.php) — состав маршрутов, 401/403/429, общие форматы search/mutate/delete, пагинация, запрет trashed и `rest:check`.
- [tests/Feature/Api/ItemApiTest.php](../../../tests/Feature/Api/ItemApiTest.php) — поиск, фильтры, includes/aggregates, `is_epic`, `meta.type`, циклы `parent_id`, удаление листа и 409, действие `assign`.
- [tests/Feature/Api/UserApiTest.php](../../../tests/Feature/Api/UserApiTest.php) — безопасные поля, admin-only create/update, уникальность email, хеширование пароля.
- [tests/Feature/Api/LabelApiTest.php](../../../tests/Feature/Api/LabelApiTest.php) — CRUD метки, формат цвета, удаление только связей `item_label`.
- [tests/Feature/Api/CommentApiTest.php](../../../tests/Feature/Api/CommentApiTest.php) — авторство, автор/admin, perimeter удалённого Item, soft delete.
- [tests/Feature/Api/ApiDocumentationTest.php](../../../tests/Feature/Api/ApiDocumentationTest.php) — OpenAPI 3.1, bearer, отсутствие запрещённых путей, актуальность снапшота.
- Запуск: `php artisan test --compact tests/Feature/Api`.

### 2. Контракт и документация

- `php artisan rest:check` — маршруты аутентифицированы, у четырёх моделей есть политики, action проверяет каждую строку, ресурсы размечены `#[WithoutPerimeter]`.
- `php artisan scramble:export --path=tests/api.json` — повторный экспорт не должен менять [tests/api.json](../../../tests/api.json); расхождение ломает `php artisan test`.
- [/docs/api](http://localhost:8000/docs/api) — интерактивная документация со всеми операциями, bearer-схемой и примерами; страница доступна в `local`/`testing`, политика доступа для production остаётся открытым вопросом.
- [routes/api.php](../../../routes/api.php) — группа `/api/v1` с `auth:sanctum` и `throttle:api`.

### 3. Items

- `GET /api/v1/items` — details: поля, лимиты `[15,25,50,100]`, relations.
- `POST /api/v1/items/search` — текст по `title`/`description`, фильтры `is_epic`, `type`, `assignee_id`, `labels.name`, includes `labels`/`comments`/`children`, aggregate `children:count`.
- `POST /api/v1/items/mutate` — create заполняет `reporter_id`, пишет `meta.type`, синхронизирует `labels`; update с `parent_id` на себя или потомка даёт 422 и откатывает всю мутацию.
- `DELETE /api/v1/items` — лист удаляется мягко (200), Item с активными children даёт 409.
- `POST /api/v1/items/actions/assign` — `{"resources":[...], "fields":[{"name":"assignee_id","value":3}]}`, не более 100 ID, атомарно.
- [app/Rest/Resources/ItemResource.php](../../../app/Rest/Resources/ItemResource.php), [app/Rest/Actions/AssignItems.php](../../../app/Rest/Actions/AssignItems.php), [app/Policies/ItemPolicy.php](../../../app/Policies/ItemPolicy.php).

### 4. Users

- `POST /api/v1/users/search` — `name`/`email` поиск и фильтры; `password`, `remember_token`, `email_verified_at` не возвращаются, не фильтруются и не сортируются.
- `POST /api/v1/users/mutate` — create/update только с ролью `admin` и ability `users:write`; обычный пользователь получает 403; email уникален с игнором текущей записи; пароль ≥ 12 символов и хешируется.
- [app/Rest/Resources/UserResource.php](../../../app/Rest/Resources/UserResource.php), [app/Policies/UserPolicy.php](../../../app/Policies/UserPolicy.php).

### 5. Labels

- `POST /api/v1/labels/mutate` — имя обязательно (≤ 50), цвет `#RRGGBB` по умолчанию `#6366f1`.
- `DELETE /api/v1/labels` — удаляет метку и только строки `item_label`; Items остаются.
- [app/Rest/Resources/LabelResource.php](../../../app/Rest/Resources/LabelResource.php), [app/Policies/LabelPolicy.php](../../../app/Policies/LabelPolicy.php).

### 6. Comments

- `POST /api/v1/comments/mutate` — `user_id` заполняется текущим пользователем, `item_id` обязателен и указывает на неудалённый Item.
- update меняет только `body`; чужой пользователь без роли `admin` получает 403; delete автора/admin делает soft delete.
- Комментарии soft-deleted Item не видны в search, не изменяются и не удаляются через API.
- [app/Rest/Resources/CommentResource.php](../../../app/Rest/Resources/CommentResource.php), [app/Policies/CommentPolicy.php](../../../app/Policies/CommentPolicy.php).

### 7. Ручная проверка токена и лимитера

- Выпуск токена: `php artisan tinker --execute '$u = App\Models\User::first(); echo $u->createToken("api", ["items:read","items:write"])->plainTextToken;'`.
- Запрос: `curl -H "Authorization: Bearer <token>" -H "Accept: application/json" http://localhost:8000/api/v1/items`.
- 61-й запрос за минуту возвращает 429; запрос без токена — JSON 401; токен без нужной ability — 403.


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
      {"relation": "labels", "selects": [{"field": "name"}]},
      {"relation": "comments", "selects": [{"field": "body"}]}
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

### Пример создания комментария `POST /api/v1/comments/mutate`

```json
{
  "mutate": [
    {
      "operation": "create",
      "attributes": {"item_id": 7, "body": "Уточнить состав первого релиза"}
    }
  ]
}
```

`user_id` в запросе отсутствует: автором становится текущий пользователь.

### Альтернативные варианты

**Собственные контроллеры + Laravel API Resources + Scribe.** Даёт привычные CRUD-маршруты, но дублирует fields/rules/relations четырёх моделей и требует ручной поддержки документации. Отклонён до реализации.

**Два ресурса вместо четырёх.** Items + Users закрывают базовый сценарий, но Labels и Comments остаются без единого API, а Item relations приходится скрывать или сериализовать особым способом. Поэтому выбраны четыре уже связанные модели без добавления новых доменных сущностей.

**Lomkit + Scramble — рекомендуется.** Typed declarations управляют поиском, записями, действием, валидацией, policies и OpenAPI. Компромисс — собственный формат пакета: search/mutate используют POST JSON, page-пагинацию и нестандартные CRUD-ответы; это принимается как публичный контракт v1.

### Открытые вопросы

- Перед production-релизом определить политику доступа к UI `/docs/api`: публичный просмотр или ограничение через стандартные middleware/gate Scramble. На генерацию и API-контракт это не влияет.
- Отдельным RFC определить удаление пользователей, назначение ролей и restore Items/Comments: эти операции меняют lifecycle связанных записей и не должны появиться как побочный эффект текущей реализации.
- При росте данных оценить переход с `Contains` на `FullText`/Scout и необходимые индексы.

### Источники

- Lomkit introduction: https://laravel-rest-api.lomkit.com/getting-started/introduction
- Installation и требования: https://laravel-rest-api.lomkit.com/getting-started/installation
- Resources и routes: https://laravel-rest-api.lomkit.com/resources/basics
- Search: https://laravel-rest-api.lomkit.com/endpoints/search
- Text search: https://laravel-rest-api.lomkit.com/search/text-search
- Mutate: https://laravel-rest-api.lomkit.com/writing/mutate
- Actions: https://laravel-rest-api.lomkit.com/endpoints/actions
- Details: https://laravel-rest-api.lomkit.com/digging-deeper/details
- Relations: https://laravel-rest-api.lomkit.com/resources/relations
- Events and hooks: https://laravel-rest-api.lomkit.com/digging-deeper/events-and-hooks
- OpenAPI: https://laravel-rest-api.lomkit.com/digging-deeper/openapi
- Удаление собственного генератора документации в Lomkit 3: https://github.com/Lomkit/laravel-rest-api/blob/v3.4.0/UPGRADE.md#built-in-documentation-generator-removed
- Scramble export: https://scramble.dedoc.co/usage/export
- Scribe API Resources и response calls: https://scribe.knuckles.wtf/laravel/documenting/responses

## Заметки

replace api package to https://laravel-rest-api.lomkit.com/

https://laravel-rest-api.lomkit.com/getting-started/introduction

https://laravel-rest-api.lomkit.com/getting-started/installation

цели
- единый подход к rest api и моделям и ресурсам
- поиск, экшены и https://laravel-rest-api.lomkit.com/writing/mutate

а и авто документацию хочется - чтобы итоговый апи имел авто доки

проверим что Scribe не должно быть - если он есть - удаляем

и для Метрик - тоже надо собрать свой неймспейс и такие же роуты - типа добавление метрик, чтение и т д

каждая метрика должна иметь ключ и лейбл

бля я перепутал - тут нет же метрик никаких - потому не надо их - давай что то другое - например items & users - ну или что то еще - чтобы было 2-3-4 ресурса для управления через rest api
