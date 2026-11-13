---
title: "Mobile-first REST API: версионирование, совместимость и поддержка"
status: draft
created: 2026-11-13
---

# RFC: Mobile-first REST API — версионирование, обратная совместимость и жизненный цикл

## TL;DR

Реализуем публичный API на Lomkit Laravel Rest Api с major-only маршрутами `/api/v1`, аддитивным развитием внутри версии и отдельными контрактами для будущих major-версий. Поверх пакета добавляем идемпотентность записей, стабильные ошибки, телеметрию клиентов, deprecation/sunset-политику и механизм мягкого или обязательного обновления мобильных приложений.

## Context

### What?

Предлагается единый стандарт проектирования и сопровождения API, рассчитанного на мобильные приложения, которые могут месяцами не обновляться, работать offline и повторять запросы после обрыва связи. Документ дополняет RFC `2026-11-11-rest-api-search.md`: Lomkit остаётся реализацией ресурсного API, а этот RFC фиксирует правила совместимости и эксплуатации для всех ресурсов.

### Why?

После публикации мобильного клиента сервер не может обновить его принудительно и одномоментно. Изменение поля, enum, default sort, валидации или error response может сломать уже установленное приложение. Нужны предсказуемый контракт, безопасные повторы операций, измеримая миграция на новые версии и управляемый вывод старых версий из эксплуатации.

### How?

- Версионируем API только major-префиксом в URL; версию приложения передаём отдельно и не используем для обычного выбора схемы ответа.
- Внутри major-версии разрешаем только обратно совместимые изменения; breaking changes публикуются в новой версии.
- Разделяем versioned REST Resources и Responses, но переиспользуем доменные сервисы, policies и модели.
- Добавляем contract tests, OpenAPI snapshots, idempotency keys, optimistic concurrency и telemetry по версиям.
- Выводим старую версию по опубликованному графику и фактическому использованию; forced update остаётся крайней мерой.

## Components & Specifics

### 1. Роль и границы Lomkit

Lomkit принимается как engine для search, mutate, actions, metrics, details, policies, limits и OpenAPI через Scramble. Пакет использует собственный JSON-протокол с `POST /search` и `POST /mutate`; это сознательный контракт API, а не JSON:API.

Lomkit не заменяет прикладной compatibility layer. Мы отдельно реализуем:

- политику поддержки API-версий и deprecation headers;
- идемпотентность клиентских операций;
- стабильный каталог машинных error codes;
- bootstrap/upgrade policy и телеметрию мобильных клиентов;
- delta sync, если продукту потребуется offline-режим;
- контроль влияния обновлений самого пакета на старые контракты.

Обновление Lomkit выполняется отдельным PR с прогоном тестов всех поддерживаемых API-версий. Общий безусловный `composer update` не является допустимой процедурой обновления API-зависимости: в upgrade guide пакета уже встречаются изменения типов полей, дат, валидации и ответов между релизами.

### 2. Две версии: API и приложение

**API version** определяет request/response contract и поведение операции. Используется только major version:

```text
/api/v1/items/search
/api/v1/items/mutate
/api/v2/items/search
```

Не создаются `/v1.1`, per-resource versions в середине URL, неявный `latest` или автоматический переход старого клиента на новую major-версию.

**App version** нужна для observability, поддержки и feature management:

```http
X-App-Version: 1.8.0
X-App-Build: 10800
X-App-Platform: android
X-Device-Id: <анонимный стабильный идентификатор установки>
```

Обычная сериализация ответа не зависит от `X-App-Version`. Исключение — аварийный kill switch для известной критической ошибки, оформленный как временный operational mechanism, а не постоянная архитектура.

### 3. Правила обратной совместимости

| Изменение | Решение для текущей major-версии |
|---|---|
| Новый endpoint, action или metric | Разрешено |
| Новый необязательный request-параметр с прежним default behavior | Разрешено |
| Новое поле ответа | Разрешено при tolerant decoding клиентов |
| Ослабление валидации | Разрешено после проверки domain invariants |
| Удаление или переименование поля, параметра, endpoint | Только в новой major-версии |
| Изменение типа, nullability или смысла существующего поля | Только в новой major-версии |
| Новый обязательный параметр или header | Только в новой major-версии |
| Ужесточение валидации ранее принимаемого запроса | Только в новой major-версии, кроме security emergency |
| Изменение default value, default sort, pagination или error semantics | Только в новой major-версии |
| Изменение auth/authorization requirements существующей операции | Только в новой major-версии, кроме security emergency |
| Новое значение enum | Только для документированного open enum с unknown fallback |

Дополнительные правила контракта:

- Мобильные декодеры игнорируют неизвестные JSON-поля и не сравнивают объекты по полному набору ключей.
- Эволюционирующие enum обрабатываются как open; клиент сохраняет неизвестное значение или безопасно показывает `unknown`, не падая при декодировании.
- Изменение HTTP status, стабильного `code` или retry semantics является breaking change. Локализуемый `message` не используется для программного ветвления.
- IDs являются стабильными opaque identifiers для клиента. Переход integer ID на UUID/ULID внутри существующего поля запрещён; при необходимости добавляется новое постоянное поле.
- Даты передаются в ISO 8601 с явным timezone; серверное хранение `updated_at` для concurrency использует микросекунды.
- Деньги и другие точные decimal values не переводятся между string/number внутри версии. Формат выбирается до публикации поля; предпочтительны minor units или decimal string.
- `null` и отсутствие поля не считаются взаимозаменяемыми, если клиент уже различает эти состояния.

### 4. Изоляция версий и развитие домена

При появлении `v2` контракты размещаются раздельно:

```text
app/Rest/Resources/V1/ItemResource.php
app/Rest/Resources/V2/ItemResource.php
app/Rest/Responses/V1/ItemResponse.php
app/Rest/Responses/V2/ItemResponse.php
```

Доменные сервисы, policies и модели переиспользуются. `V2` не наследуется от `V1` ради сокращения кода: изменение общего предка не должно незаметно менять старый API. Общие value objects допустимы, если они не содержат изменяемых public-contract decisions.

Изменения БД выполняются по схеме **expand → migrate → contract**:

1. Добавляются новые колонки/структуры, совместимые со старым кодом.
2. Выполняются backfill и необходимые dual read/write.
3. Новые и старые API-версии используют свои adapters.
4. Старые структуры удаляются только после retirement всех зависимых клиентов и завершения rollback window.

Для списков и простых ресурсных изменений используются Lomkit search/mutate. Для бизнес-операций с side effects предпочтительны явные Actions или отдельные controllers. Произвольный nested graph mutation не является способом обойти доменные инварианты.

### 5. Идемпотентность и повторы запросов

Все клиентские `POST`-операции с side effects принимают:

```http
Idempotency-Key: <UUID>
```

Требования:

- клиент создаёт один ключ на логическую операцию и сохраняет его вместе с offline-операцией;
- повтор после timeout/restart использует тот же ключ и побайтово эквивалентное тело;
- сервер разделяет ключи минимум по principal и операции и сохраняет request hash вместе с результатом;
- повтор с тем же ключом и другим телом отклоняется стабильным `409`/`422` code;
- успешный ответ и известный окончательный результат могут быть возвращены повторно без повторного side effect;
- срок хранения ключа покрывает максимальный поддерживаемый срок offline-очереди; его истечение документируется;
- очистка ключей не удаляет записи, необходимые ещё активным offline-клиентам.

Retry policy мобильного клиента:

- network errors, `429` и retryable `5xx`: exponential backoff с jitter и учётом `Retry-After`;
- `400`, `401`, `403`, `422`: автоматический retry запрещён;
- `409`: обрабатывается как conflict или in-progress по стабильному error code;
- повтор mutation с неизвестным результатом выполняется только с прежним idempotency key.

### 6. Конкурентные изменения и offline sync

Для редактируемых ресурсов включается Lomkit `versioned()`. Мобильный клиент всегда отправляет прочитанный `version` при update, хотя пакет считает его необязательным. `409 Conflict` приводит к reload и merge/повторному подтверждению пользователя, а не к слепому retry.

Row versioning не заменяет протокол синхронизации и не покрывает все delete/relation scenarios. Если offline-режим входит в продуктовый scope, добавляется отдельный endpoint:

```http
GET /api/v1/sync/items?cursor=<opaque-token>
```

Ответ содержит changed records, tombstones, `next_cursor` и при необходимости `resync_required`. Cursor opaque для клиента. Истёкший или невосстановимый cursor вызывает явную полную ресинхронизацию. Обычная page pagination не используется как гарантированный change feed.

### 7. Ошибки, кеширование и эксплуатационные ограничения

- Для ошибок задаётся единый envelope со стабильными `type`/`code`, человекочитаемым `title`/`detail`, `request_id` и extension fields для validation/conflicts. Можно использовать RFC 9457 Problem Details; переход на него после публикации другого error contract выполняется только как совместимое расширение или в новой major-версии.
- `POST /search` не рассчитан на CDN/HTTP URL caching. Для тяжёлых общедоступных reference data допускаются отдельные `GET` endpoints с ETag/Cache-Control; приватные пользовательские ответы не кешируются публичными caches.
- Rate limits, payload limits и limits вложенности документируются. Их ужесточение оценивается как потенциально breaking change для существующих клиентов.
- Изменение полей по текущему пользователю не должно создавать непредсказуемые разные схемы. Права и доступные действия передаются через gates/capabilities; базовая структура ресурса остаётся стабильной.

### 8. Жизненный цикл и отключение версий

Статусы версии: `stable` → `deprecated` → `maintenance_only` → `retired`.

Базовая политика:

- одновременно поддерживаются текущая и предыдущая major-версии;
- предыдущая версия поддерживается не менее 24 месяцев после публикации successor, если иное не установлено более строгой продуктовой политикой;
- фактическое отключение учитывает активные установки и трафик, а не только дату;
- security emergency допускает ускоренное изменение с отдельной коммуникацией и incident record;
- retired endpoint возвращает `410 Gone` со стабильным кодом и migration/update information, а не неявно обслуживается новой версией.

Deprecated responses получают `Deprecation` по RFC 9745, `Sunset` по RFC 8594 и `Link` на migration guide. Deprecation не меняет поведение работающего ресурса.

До retirement собираются метрики по API version, app version/build, platform, endpoint и error code. Решение документируется с датами, остаточным использованием и планом коммуникации.

### 9. Bootstrap и обновление приложения

Каждая поддерживаемая major-версия предоставляет минимальный bootstrap endpoint, например `GET /api/v1/mobile/bootstrap`:

```json
{
  "minimum_app_version": "1.6.0",
  "recommended_app_version": "2.1.0",
  "update_required": false,
  "maintenance": null,
  "features": {"item_time_tracking": true},
  "server_time": "2026-11-13T14:00:00Z"
}
```

Сначала используется рекомендуемое обновление, затем ограничение отдельных функций и только при несовместимости или критическом риске — обязательное обновление. Конфигурация кешируется приложением. Недоступность bootstrap не должна сама по себе блокировать offline-работу или уничтожать локальную очередь.

### 10. Тестирование и выпуск

- Scramble export и OpenAPI snapshot хранятся отдельно для каждой поддерживаемой версии.
- CI выполняет breaking-change diff и contract tests, но schema diff не считается достаточной проверкой behavior/defaults.
- Golden tests содержат реальные payloads поддерживаемых мобильных релизов, включая errors, retries, unknown enum и offline conflicts.
- Перед удалением поля/endpoint проверяется, что ни один поддерживаемый client contract его не использует.
- Каждый релиз Lomkit проверяет все API-версии; изменения их snapshots и golden responses явно ревьюятся.
- API changelog и migration guide содержат before/after examples для каждого breaking change.

### Затрагиваемые компоненты

- `routes/api.php`, middleware, request telemetry и rate limiting;
- `app/Rest/Resources`, `Responses`, `Controllers`, Actions и доменные сервисы;
- exception rendering и каталог error codes;
- idempotency storage и очистка устаревших записей;
- OpenAPI/Scramble exports, Pest contract tests и CI;
- mobile bootstrap/configuration и observability;
- при offline scope — sync endpoints, change log/cursors и tombstones.

### Out of scope

- GraphQL, JSON:API и переписывание уже принятого Lomkit search/mutate protocol;
- date-based API versions и автоматический fallback на другую major-версию;
- публичные SDK, OAuth2 и webhooks;
- реализация полноценной offline-синхронизации до отдельного продуктового решения;
- немедленное создание `v2` без реального breaking change;
- безусловное forced update как замена backward compatibility.

## Acceptance Criteria

- [ ] Все публичные мобильные API-маршруты имеют major-only префикс `/api/v{N}`, а неявный `latest` отсутствует.
- [ ] В репозитории опубликована таблица breaking/non-breaking changes из этого RFC, и она используется при API review.
- [ ] API version и app version передаются независимо; выбор обычного response contract не зависит от app version.
- [ ] Для каждой поддерживаемой версии существуют отдельный OpenAPI snapshot и набор HTTP contract tests.
- [ ] Тесты доказывают, что добавление неизвестного response field и open enum value не ломает поддерживаемый мобильный decoding contract.
- [ ] Все mutations с side effects принимают `Idempotency-Key`; повтор с тем же телом не создаёт второй side effect, а с другим телом отклоняется.
- [ ] Мобильные retry tests подтверждают сохранение idempotency key после timeout и restart операции.
- [ ] Для versioned resources update без актуального `version` возвращает задокументированный conflict либо запрещён прикладным контрактом; `409` не приводит к слепому retry.
- [ ] Все ошибки содержат стабильный машинный код и `request_id`; клиентская логика не зависит от локализуемого message.
- [ ] Telemetry позволяет определить число активных установок и объём запросов по API version и app version за выбранный период.
- [ ] Deprecated version возвращает корректные `Deprecation`, `Sunset` и migration `Link`, сохраняя прежнее поведение.
- [ ] Retired version возвращает `410 Gone` с машинным кодом и не перенаправляется молча на новую major-версию.
- [ ] Bootstrap поддерживает recommended/forced update, maintenance и feature flags; его недоступность не блокирует кешированный offline mode.
- [ ] Изменение БД, необходимое двум версиям, проходит expand/migrate/contract и не удаляет старые структуры до retirement зависимой версии.
- [ ] Обновление Lomkit запускает contract suite каждой поддерживаемой версии и не принимается при неутверждённом diff публичного контракта.
- [ ] `php artisan rest:check`, `php artisan test`, `./vendor/bin/pint` и OpenAPI export проходят в CI.

## Addenda

### Этапы внедрения

1. Зафиксировать compatibility policy, error codes и обязательные client headers.
2. Добавить telemetry, OpenAPI snapshots и golden tests до публикации первого мобильного клиента.
3. Реализовать idempotency и обязательное client-side использование row versions.
4. Добавить bootstrap, recommended/forced update и deprecation middleware.
5. При появлении offline-требований отдельным RFC спроектировать change feed, cursors, tombstones и retention.
6. Новую major-версию запускать только вместе с migration guide, usage dashboard и утверждённым sunset-планом.

### Источники

- [Lomkit Laravel Rest Api — Introduction](https://laravel-rest-api.lomkit.com/getting-started/introduction)
- [Lomkit — Resources and route groups](https://laravel-rest-api.lomkit.com/resources/resources)
- [Lomkit — Versioned rows](https://laravel-rest-api.lomkit.com/writing/versioned-rows)
- [Lomkit — OpenAPI and snapshot testing](https://laravel-rest-api.lomkit.com/digging-deeper/openapi)
- [Lomkit — Upgrade guide](https://laravel-rest-api.lomkit.com/getting-started/upgrade-guide)
- [GitHub — Breaking changes and 24-month support](https://docs.github.com/en/rest/about-the-rest-api/breaking-changes)
- [Stripe — API versioning](https://docs.stripe.com/api-versions)
- [Stripe — Idempotent requests](https://docs.stripe.com/api/idempotent_requests)
- [Microsoft Graph — Delta query](https://learn.microsoft.com/en-us/graph/delta-query-overview)
- [RFC 9745 — Deprecation HTTP Header](https://www.rfc-editor.org/info/rfc9745/)
- [RFC 8594 — Sunset HTTP Header](https://www.rfc-editor.org/info/rfc8594/)
- [RFC 9457 — Problem Details for HTTP APIs](https://www.rfc-editor.org/rfc/rfc9457.html)
