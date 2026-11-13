---
title: "REST API v1 на Lomkit и Scramble"
status: done
completed: 2026-11-13
rfc: ../rfc/archive/2026-11-11-rest-api-search.md
---

# Spec: REST API v1 на Lomkit и Scramble

## Что сделано

Публичный REST API v1 живёт под `/api/v1` и защищён Sanctum-токенами. Четыре ресурса Lomkit
(`App\Rest\Resources\{Item,User,Label,Comment}Resource`) описаны декларативно и дают один и тот же
набор операций: `details`, `search`, `mutate`, а для items/labels/comments ещё и `destroy`.
У items дополнительно есть action `assign`. Традиционных list/show/store/update маршрутов нет:
список и одна запись получаются через `POST /search` (фильтр `id = ...`).

| Ресурс | URI | Abilities | Особенности |
|---|---|---|---|
| Items | `/api/v1/items` | `items:read`, `items:write` | `is_epic`, virtual `type` из `meta.type`, labels attach/detach/sync, 409 при активных children, action `assign` |
| Users | `/api/v1/users` | `users:read`, `users:write` | write-only password, create/update только `admin`, роли не экспонируются, destroy отсутствует |
| Labels | `/api/v1/labels` | `labels:read`, `labels:write` | удаление чистит только `item_label`, relation `items` read-only |
| Comments | `/api/v1/comments` | `comments:read`, `comments:write` | автор = текущий пользователь, author/admin правила, perimeter по живому Item |

Общие правила зафиксированы в `App\Rest\Resources\Resource`: `Contains`-поиск, лимиты
`[15,25,50,100]`, порядок `id DESC`, отказ от `search.trashed` с 422. Авторизация двухслойная:
token ability (Gate в `AppServiceProvider`) плюс политика модели. Rate limit — 60 запросов в минуту
по user ID или IP. `php artisan rest:check` проходит: 17 маршрутов аутентифицированы, 4 политики,
action проверяет каждую строку.

Документация строится Scramble из тех же деклараций: `/docs/api` (UI) и `/docs/api.json`,
снапшот `tests/api.json` коммитится. Ответ 409 удаления Item и bearer-схема добавлены
трансформерами OpenAPI.

## Ключевые решения

- **Один источник контракта.** Поля, relations, правила, фильтры и действия объявлены только в
  REST-ресурсе; API Resources, Form Requests и Scribe не используются.
- **`type` — виртуальное поле над `meta.type`.** Чтение через `resolveUsing`, запись через
  `fillUsing` (остальные ключи `meta` сохраняются), фильтр через `meta->type`, допустимые значения
  `task|bug|feature`. Старые записи без типа читаются как `null`.
- **`is_epic` — отдельный флаг.** Новый `items.is_epic` (boolean, default false, индекс
  `(is_epic, id)`) не меняет `type`; гибрид `type=task + is_epic=true` — валидная задача-контейнер.
- **Иерархия меняется только через `parent_id`.** Relations `parent`/`children` объявлены
  read-only (`onlyOperations()`), поэтому запись дерева возможна лишь сменой `parent_id`.
  Цикл (сам item или потомок) даёт 422 и откатывает всю мутацию.
- **Состав меток — только через Item.** `ItemResource.labels` принимает `attach`/`detach`/`sync`;
  `LabelResource.items` read-only, чтобы не было двух конкурирующих write API.
- **Destroy с доменным ограничением.** Item с активными children отвечает 409 без изменений;
  остальные удаления — мягкие и транзакционные.
- **Perimeter комментариев.** `CommentResource::perimeterQuery()` делает `whereHas('item')`:
  комментарии удалённого Item остаются в БД, но не видны, не изменяются и не удаляются через API.
- **Безопасность пользователей.** `password` объявлен `writeOnly`, поэтому не возвращается,
  не фильтруется и не сортируется; `remember_token`, `email_verified_at` и роли не объявлены вовсе.
  Unique email на update игнорирует текущую запись через `Contextual::rule()`.
- **Snapshot вместо ручной коллекции.** `tests/api.json` — экспорт OpenAPI 3.1; тест
  `ApiDocumentationTest` повторно экспортирует документ и падает при расхождении.

## Отклонения от RFC

- **`includeLimit` = 15, а не 10.** Пакет требует, чтобы `$includeLimit` входил в `limits()`;
  при лимитах `[15,25,50,100]` значение 10 по умолчанию бросает `InvalidResourceException`.
- **`password` помечен описанием `Write-only.`, а не OpenAPI-флагом `writeOnly`.** Генератор
  Scramble 0.13 не поддерживает `writeOnly` у схем; поле по-прежнему отсутствует в схеме записи
  User и присутствует только в `UserCreate`/`UserUpdate`.
- **`CommentPolicy::create` разрешает аутентифицированному пользователю.** Существование и
  неудалённость `item_id` проверяет правило поля, поэтому клиент получает 422 (как в RFC), а не
  403 из политики. Политика `view/update/delete` ограничивает автора и `admin`.
- **Доступ к `/docs/api` ограничен `local`/`testing`.** Политика публичного доступа к UI остаётся
  открытым вопросом RFC; в production страница по-прежнему закрыта до отдельного решения.
- **CI-конфигурации в репозитории нет.** Роль «CI падает на diff» выполняет тест
  `the committed snapshot is up to date` в `ApiDocumentationTest`, который запускается вместе с
  `php artisan test` в любом пайплайне.
- **`SmokeTest` обращается к `/i`, а не `/items`.** Filament-ресурс Item имеет slug `i`; тест
  приведён в соответствие с фактическим маршрутом (это была существовавшая до RFC рассинхронизация).

## Известные ограничения / дальнейшие шаги

- Нет выдачи токенов через API: токены создаются вручную или командами (`createToken`).
- Нет удаления пользователей, назначения ролей и permissions через API — отдельный lifecycle-RFC.
- Нет restore/force delete и чтения trashed; `search.trashed` намеренно возвращает 422.
- Нет метрик, очередей, batched actions, webhooks, OAuth2 и публичных SDK.
- Поиск — `Contains` по SQL; при росте данных потребуется `FullText`/Scout и индексы.
- Политика доступа к `/docs/api` в production не определена.
- Rate limit 60/мин общий для всех четырёх ресурсов; при необходимости именованные лимитеры
  добавляются на уровне action/ресурса.
