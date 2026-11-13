---
title: "REST API on Lomkit: Items, Users, Labels, Comments and OpenAPI"
status: done
created: 2026-11-11
completed: 2026-11-13
---

# RFC: REST API on Lomkit — Items, Users, Labels, Comments and auto-documentation

## TL DR

We replace the not-yet-implemented combination of custom CRUD controllers, Laravel API Resources and Scribe with `lomkit/laravel-rest-api` 3.x plus OpenAPI through Scramble. We publish four related REST resources — `ItemResource`, `UserResource`, `LabelResource`, `CommentResource` — with shared `details`, `search`, `mutate` and per-resource allowed `destroy`/actions. We keep the domain model with `is_epic` and `meta.type`; the interactive documentation at `/docs/api` and the OpenAPI snapshot are generated from the same declarations as the API.

## Context

**Situation.** There is no API in the code yet: `routes/api.php`, API controllers and Sanctum are all absent. Checking Composer, the lockfile, the configuration and the public artifacts confirms that Scribe is not installed. `PRODUCT.md` declares a Public API, and the `Item`, `User`, `Label`, `Comment` models are already linked through Eloquent relations.

**Complication.** The manual approach duplicates the model description across controllers, Form Requests, API Resources and documentation. A single Item API is not enough to manage the core entities, while metrics are not needed in the current scope. Laravel `13.12.0` is installed, and Lomkit Rest API 3 requires PHP 8.3+ and Laravel `13.30+`.

**Question.** How do we get a single approach to several models and REST resources, search, transactional changes, actions and up-to-date documentation without parallel descriptions of the contract?

**Answer.** Before implementation we move the RFC onto four declarative Lomkit resources and Scramble, upgrade Laravel and use the package's native request and response formats. Since there is no previous API in the code, no compatibility layer for manual CRUD routes is needed.

`docs/specs/` does not exist; the starting state is the code, the migrations and this RFC. No other active RFC registers an API or conflicts with this decision.

## Purpose and goals

- Describe each API model once: typed fields, relations, rules and actions live in the REST resource instead of being spread across controllers and Form Requests.
- Give Items, Users, Labels and Comments a shared search with a text query, filters, sorting, includes, aggregates and pagination.
- Support creation and updates through transactional `mutate`, soft delete for Item/Comment, deletion for Label and a typed action for Items.
- Separate operations by token abilities and policies, including admin-only user management and comment authorship rules.
- Get interactive documentation and OpenAPI automatically from the same declarations, eliminating documentation drift.
- Keep the Item domain decisions: one model type for tasks and containers, independent `type`, `is_epic`, `parent_id`, and hierarchy integrity.

Stakeholders: AgileTracker and API client developers, integration authors, the product owner and architecture reviewers. These four resources set the template for the following REST resources of the application.

## Components and specifics

### 1. Platform, shared contracts and security

- In `composer.json` we raise the constraint to `laravel/framework:^13.30`, update the lockfile and add the production dependencies `lomkit/laravel-rest-api:^3.0`, `dedoc/scramble:^0.13.48`, `laravel/sanctum`. Scribe is not installed; its package, configs, routes and generated assets must be absent.
- We register `routes/api.php` through `install:api`, add `HasApiTokens` to `User` and keep JSON errors for `api/*`. The `/api/v1` group uses `auth:sanctum` and `throttle:api` — 60 requests per minute by user ID, and by IP for guests.
- The base `App\Rest\Resources\Resource`, `App\Rest\Controllers\Controller` and four thin controllers are created. REST classes are not mixed with the identically named Filament Resources; `App\Http\Resources` is not used.

| Resource | Base URI | Allowed package endpoints | Abilities |
|---|---|---|---|
| Items | `/api/v1/items` | `details`, `search`, `mutate`, `operate`, `destroy` | `items:read`, `items:write` |
| Users | `/api/v1/users` | `details`, `search`, `mutate` | `users:read`, `users:write` |
| Labels | `/api/v1/labels` | `details`, `search`, `mutate`, `destroy` | `labels:read`, `labels:write` |
| Comments | `/api/v1/comments` | `details`, `search`, `mutate`, `destroy` | `comments:read`, `comments:write` |

- Routes are registered through `Rest::resource(...)->only(...)`: endpoints that are not listed do not exist. Metrics, restore, force delete and the traditional `Route::apiResource()` are not published; operate exists only for Items.
- Read endpoints require `<resource>:read`; mutate/operate/destroy require `<resource>:write`. A token ability does not replace a Policy. No token gives a JSON 401, a missing ability or a Policy denial gives 403, exceeding the limiter gives 429.
- `GET /{resource}` returns details, `POST /{resource}/search` returns rows. There is no traditional list/show: a single record is fetched with the `id = ...` filter. An empty result is 200 with `data: []`.
- Every resource declares `Contains::make()` and searchable Text fields. Modes are `words`/`phrase`, maximum 255 characters; `%`, `_`, `\` are literal. Declared filters, selects, sorts, includes, aggregates, gates, page and limit are supported. Scout/FULLTEXT are not added.
- The base Resource sets `$defaultLimit = 15` and limits `[15,25,50,100]`. The response uses Lomkit page pagination; the default order is `id DESC`, and with user sorting the package adds a key for determinism.
- `POST /{resource}/mutate` performs create/update in a single transaction and returns 200 with `operations`; the optional `response` returns the records after commit. `DELETE /{resource}` accepts `resources` and returns 200 with rows rather than 204.
- `searching()` in the base Resource rejects `search.trashed` with 422. Soft-deleted Items/Comments are excluded from search and writes; restore/force delete are absent. Unknown fields, relations and operators give 422 with the exact payload path.
- `rest:check` is mandatory after migrations; the tests enable `REST_AUTHORIZATIONS_STRICT=true` and `WithCachedRoutes`.

### 2. ItemResource

- A new migration adds `items.is_epic`: non-null boolean, `default(false)`, index `(is_epic, id)`; `down()` drops the index and the column. `Item` gains `$fillable`, the boolean cast and the activity log for `is_epic`; `ItemFactory` gets `false` by default and an `epic()` state.
- Fields: `id`, `title`, `description`, `type`, `is_epic`, `parent_id`, `assignee_id`, readonly `reporter_id`, `due_date`, readonly timestamps. Arbitrary `meta` and undeclared columns are not available.
- `type` is a virtual Text field: read from `meta.type`, written through `fillUsing()` while preserving the other meta keys, filtered through `meta->type`. Allowed values are `task|bug|feature`; create requires a value, update requires `filled`. For older records without a type the read schema allows null.
- Searchable: `title`, `description`. Filters: `is_epic`, `type`, `assignee_id`, `labels.name` and the native filters of the other allowed fields. The children count comes from the `children:count` aggregate.
- `parent`, `children`, `assignee`, `reporter`, `comments` are read-only relations. `labels` accepts only `attach`, `detach`, `sync`; labels cannot be created or edited through Item. Nested writes through parent/children are forbidden, and the hierarchy changes only through `parent_id`.
- `ItemPolicy`: `viewAny`, `view`, `create`, `update`, `delete` are allowed to authenticated users in the current domain model. The resource is explicitly `#[WithoutPerimeter]`; future visibility rules move into `perimeterQuery()`.
- On create `title` and `type` are required; title max 255. `parent_id` and `assignee_id` are nullable and reference existing records; the parent must not be deleted. `mutating()` fills the readonly `reporter_id` with the current user.
- When `parent_id` changes, the hook returns 422 for a reference to the item itself or a descendant and rolls back the entire mutation. Changing `is_epic` does not change `type`, `parent_id` or children. Eloquent keeps triggering the activity log.
- `DELETE /items` soft deletes records transactionally. `destroying()` returns 409 when children are active; there is no cascade delete/detach. Comments stay in the database but are excluded through the CommentResource perimeter together with the deleted parent.
- The only initial action is `AssignItems`, `$uriKey = 'assign'`: `POST /items/actions/assign`. The nullable `assignee_id` with `exists:users,id` assigns or clears the assignee.
- The action uses `ActionTarget::Resources`, `$maxResources = 100`, `authorizeEach() = update`, `ActionTransaction::Whole`; `search` is forbidden. A 200 response contains `data.impacted`; an error in any row rolls back the whole list. An unknown action after `viewAny` gives 404.

### 3. UserResource

- Fields: `id`, `name`, `email`, writeOnly `password`, readonly timestamps. `remember_token`, `email_verified_at`, tokens, password and session data are never returned; password cannot be filtered or sorted.
- Searchable: `name`, `email`; filters include `name`, `email`, `id`. Read-only relations: `assignedItems`, `reportedItems`, `comments`. Roles and permissions are not exposed in API v1.
- Create requires `name`, a unique `email` and a `password` of at least 12 characters; the model's hashed cast performs the hashing. Update allows name/email and an optional password; the unique email rule ignores the current record via `Contextual::rule()`.
- `UserPolicy`: `viewAny`/`view` are allowed to authenticated users, `create`/`update` only to the `admin` role. So `users:write` is necessary but not sufficient without admin. The resource is `#[WithoutPerimeter]`.
- `DELETE /users` is not registered: deleting users, moving their items/comments and revoking tokens require a separate lifecycle decision. Role assignment is also outside this RFC.

### 4. LabelResource

- Fields: `id`, `name`, `color`, readonly timestamps; searchable `name`; read-only relation `items`. The resource is `#[WithoutPerimeter]`.
- Create requires `name` max 50. `color` is optional on create because of the DB default, and when supplied its `#RRGGBB` format is validated; update uses `filled` for both fields.
- `LabelPolicy` allows read/write to authenticated users. `DELETE /labels` deletes the label; the existing `item_label` cascade removes only the links, the items themselves are not changed.
- The reverse `items` relation is read-only: label membership is changed only through `ItemResource.labels`, which rules out two competing write APIs.

### 5. CommentResource

- Fields: `id`, `body`, create-only `item_id`, readonly `user_id`, readonly timestamps. Searchable `body`; filters include `item_id`, `user_id`, `id`; read-only relations `item`, `author`.
- Create requires `body` and an existing, non-deleted `item_id`. `mutating()` fills `user_id` with the current user; the client cannot spoof the author. Update allows only `body` with `filled`; the item and author are immutable.
- `CommentPolicy`: `viewAny`/`view` are allowed to authenticated users, `create` when the parent Item is available, `update`/`delete` to the comment author or `admin`. `comments:write` is mandatory for all writes.
- `perimeterQuery()` limits the output with `whereHas('item')`: comments of a soft-deleted Item are not returned, changed or deleted through the API. They stay in the database in case the parent is restored in the future.
- `DELETE /comments` performs a soft delete in a single transaction. `search.trashed`, restore and force delete are forbidden by the shared contract.

### 6. Automatic OpenAPI documentation and contract checking

Scramble was chosen because it is the only native way for Lomkit 3 to build OpenAPI automatically from REST resource declarations. Lomkit 3 removed its own documentation generator, and the Scramble transformers it ships use the same fields, relations, filters, actions, limits and gates as the working API. That rules out a parallel description of the contract and matches the original goal of auto-documentation.

Scribe is not technically incompatible with Lomkit, but it has no ready-made integration with Lomkit Resource. Scribe can extract responses from Laravel API Resources (`JsonResource`), not from `App\Rest\Resources\Resource` declarations; the search/mutate schemas, relations, filters and actions would have to be duplicated with annotations or a custom adapter would have to be maintained. Scribe therefore does not satisfy the single-source-of-contract requirement without additional development.

- Scramble is a production dependency: the interactive documentation is available at `/docs/api`, and no manual registration of Lomkit operations is required.
- A document transformer adds the HTTP Bearer security scheme. The schemas automatically cover all four resources, their details/search/mutate, the allowed destroy, `assign`, typed fields, filters, relations, limits, validation and package errors. The domain 409 for Item delete/hierarchy is added by a small operation transformer.
- `examples()` are set on fields and action inputs. The password is marked writeOnly schematically; forbidden relations and endpoints do not appear in the documentation.
- `php artisan scramble:export --path=tests/api.json` creates the OpenAPI 3.1.0 snapshot. The file is committed; CI re-exports the specification and fails on a diff. Scribe HTML and a separate Postman collection are not needed: OpenAPI can be imported into Postman.
- Feature tests make real HTTP requests for every resource, relation, policy, ability and transaction, checking responses against the schemas. A snapshot test verifies paths, component schemas and bearer security.
- Readiness is confirmed by `php artisan rest:check`, the full `php artisan test`, `./vendor/bin/pint` and a re-export with no changes.

### Limitations and out of scope

- Metrics, queued/batched actions, restore/force delete, reading trashed records, nested creation of the Items tree.
- Separate Project/Epic models and routes; REST Resources for Favorites and other models beyond the four listed.
- Deleting Users, assigning roles/permissions, managing tokens through the API, the email verification flow.
- Traditional list/show/store/update endpoints and compatibility with the formats of the previous RFC variant.
- Laravel Scout, Meilisearch, FULLTEXT; OAuth2, webhooks, public SDKs.

## Acceptance criteria (checklist)

### 1. Platform, shared contracts and security

- [x] The dependencies match Laravel `^13.30+`, Lomkit 3.x, Scramble `^0.13.48`, Sanctum; Scribe is absent from the lockfile, configs, routes and assets.
- [x] `route:list` contains exactly the endpoints from the table for items/users/labels/comments; metrics, restore, force, users destroy and actions outside Items are absent.
- [x] The shared search/mutate/delete formats, Contains, limits, pagination, the trashed ban and JSON 401/403/422/429 are covered by tests; `rest:check` is green.

### 2. ItemResource

- [x] The `is_epic` migration is backward compatible and rolls back; the hybrid `type=task + is_epic=true` is available through the same resource, and type is stored in `meta.type` without losing other keys.
- [x] Search works over title/description and filters; relations and the children count do not cause N+1; undeclared fields/meta and nested parent/children writes give 422.
- [x] Create/update fill the reporter with the current user, validate fields and labels, forbid `parent_id` cycles with a full mutation rollback; `is_epic` does not change the hierarchy.
- [x] Deleting a leaf Item is soft and returns 200; active children give 409 with no changes. Assign sets/clears the assignee for at most 100 explicit IDs, checks update and atomically returns `impacted`.

### 3. UserResource

- [x] Users search/details work with `users:read`; create/update require both `users:write` and admin. A regular user with the write ability gets 403.
- [x] Email is unique with a correct ignore on update, password is required on create and hashed; password/tokens/remember_token cannot be read, filtered or returned through selects.
- [x] `DELETE /users` and role assignment routes/relations are absent; assignedItems/reportedItems/comments are available only as read-only includes.

### 4. LabelResource

- [x] Labels support details/search/mutate/delete with the matching abilities; name/color are validated and the read-only items include works.
- [x] Deleting a Label clears only the `item_label` links and does not delete Items; label membership changes only through ItemResource.

### 5. CommentResource

- [x] Create requires body/item_id and fills the author with the current user; update changes only body and is allowed to the author or admin; another user without admin gets 403.
- [x] Search filters by item/user and includes item/author; comments of a deleted Item are excluded from search/mutate/delete.
- [x] Delete by the author/admin performs a soft delete with 200; `comments:write` and the Policy are checked independently, trashed/restore/force are absent.

### 6. Automatic OpenAPI documentation and contract checking

- [x] `/docs/api` contains every allowed operation of the four resources plus `assign`, bearer auth, field/relation/action schemas and errors; forbidden endpoints are absent.
- [x] `php artisan scramble:export --path=tests/api.json` creates OpenAPI 3.1.0; a repeated export produces no diff, and changing a resource without a snapshot breaks CI.
- [x] `php artisan test` is green, including all resource/authorization/docs tests and strict authorization; `./vendor/bin/pint` produces no changes.

## Roadmap

1. **Platform, shared contracts and security.** Upgrade Laravel, install the packages, create the base classes, routes, abilities, limiter and shared search/mutate rules.
2. **ItemResource.** Add `is_epic`, fields/relations, hooks, the delete guard, `AssignItems` and the domain invariant tests.
3. **UserResource.** Implement the safe fields, admin policies and user mutations without deletion and roles.
4. **LabelResource.** Implement CRUD, the read-only items relation and the pivot cascade check.
5. **CommentResource.** Implement authorship, author/admin policies, the parent perimeter and soft delete; wire it to the Item/User includes.
6. **Automatic OpenAPI documentation and contract checking.** Configure bearer/examples, the snapshot, contract tests and CI checks for all resources.

## Summary and recommendations

We implement API v1 directly on Lomkit Rest API 3 and Scramble for Items, Users, Labels and Comments, without building manual CRUD, Scribe or metrics. Each REST resource becomes the single source of the contract for its model, and the shared base classes pin the same search, mutations, authorization and documentation. Differences remain only in fields, relations and domain policies.

## Test instructions

Every step below is verified in a clean environment after `composer install`, `php artisan migrate` and `npm run build`.

### 1. Automated tests

- [tests/Feature/Api/ApiFoundationTest.php](../../../tests/Feature/Api/ApiFoundationTest.php) — route inventory, 401/403/429, the shared search/mutate/delete formats, pagination, the trashed ban and `rest:check`.
- [tests/Feature/Api/ItemApiTest.php](../../../tests/Feature/Api/ItemApiTest.php) — search, filters, includes/aggregates, `is_epic`, `meta.type`, `parent_id` cycles, leaf deletion and 409, the `assign` action.
- [tests/Feature/Api/UserApiTest.php](../../../tests/Feature/Api/UserApiTest.php) — safe fields, admin-only create/update, email uniqueness, password hashing.
- [tests/Feature/Api/LabelApiTest.php](../../../tests/Feature/Api/LabelApiTest.php) — label CRUD, color format, deleting only `item_label` links.
- [tests/Feature/Api/CommentApiTest.php](../../../tests/Feature/Api/CommentApiTest.php) — authorship, author/admin, deleted Item perimeter, soft delete.
- [tests/Feature/Api/ApiDocumentationTest.php](../../../tests/Feature/Api/ApiDocumentationTest.php) — OpenAPI 3.1, bearer, absence of forbidden paths, snapshot freshness.
- Run: `php artisan test --compact tests/Feature/Api`.

### 2. Contract and documentation

- `php artisan rest:check` — routes are authenticated, the four models have policies, the action checks every row, the resources are marked `#[WithoutPerimeter]`.
- `php artisan scramble:export --path=tests/api.json` — a repeated export must not change [tests/api.json](../../../tests/api.json); any difference breaks `php artisan test`.
- [/docs/api](http://localhost:8000/docs/api) — interactive documentation with all operations, the bearer scheme and examples; the page is available in `local`/`testing`, and the production access policy remains an open question.
- [routes/api.php](../../../routes/api.php) — the `/api/v1` group with `auth:sanctum` and `throttle:api`.

### 3. Items

- `GET /api/v1/items` — details: fields, limits `[15,25,50,100]`, relations.
- `POST /api/v1/items/search` — text over `title`/`description`, filters `is_epic`, `type`, `assignee_id`, `labels.name`, includes `labels`/`comments`/`children`, aggregate `children:count`.
- `POST /api/v1/items/mutate` — create fills `reporter_id`, writes `meta.type`, syncs `labels`; update with `parent_id` pointing at itself or a descendant gives 422 and rolls back the whole mutation.
- `DELETE /api/v1/items` — a leaf is soft deleted (200), an Item with active children gives 409.
- `POST /api/v1/items/actions/assign` — `{"resources":[...], "fields":[{"name":"assignee_id","value":3}]}`, at most 100 IDs, atomic.
- [app/Rest/Resources/ItemResource.php](../../../app/Rest/Resources/ItemResource.php), [app/Rest/Actions/AssignItems.php](../../../app/Rest/Actions/AssignItems.php), [app/Policies/ItemPolicy.php](../../../app/Policies/ItemPolicy.php).

### 4. Users

- `POST /api/v1/users/search` — `name`/`email` search and filters; `password`, `remember_token`, `email_verified_at` are never returned, filtered or sorted.
- `POST /api/v1/users/mutate` — create/update only with the `admin` role and the `users:write` ability; a regular user gets 403; email is unique with the current record ignored; the password is ≥ 12 characters and hashed.
- [app/Rest/Resources/UserResource.php](../../../app/Rest/Resources/UserResource.php), [app/Policies/UserPolicy.php](../../../app/Policies/UserPolicy.php).

### 5. Labels

- `POST /api/v1/labels/mutate` — the name is required (≤ 50), the `#RRGGBB` color defaults to `#6366f1`.
- `DELETE /api/v1/labels` — deletes the label and only the `item_label` rows; Items remain.
- [app/Rest/Resources/LabelResource.php](../../../app/Rest/Resources/LabelResource.php), [app/Policies/LabelPolicy.php](../../../app/Policies/LabelPolicy.php).

### 6. Comments

- `POST /api/v1/comments/mutate` — `user_id` is filled with the current user, `item_id` is required and points at a non-deleted Item.
- update changes only `body`; another user without the `admin` role gets 403; delete by the author/admin performs a soft delete.
- Comments of a soft-deleted Item are not visible in search, are not changed and are not deleted through the API.
- [app/Rest/Resources/CommentResource.php](../../../app/Rest/Resources/CommentResource.php), [app/Policies/CommentPolicy.php](../../../app/Policies/CommentPolicy.php).

### 7. Manual token and limiter check

- Issue a token: `php artisan tinker --execute '$u = App\Models\User::first(); echo $u->createToken("api", ["items:read","items:write"])->plainTextToken;'`.
- Request: `curl -H "Authorization: Bearer <token>" -H "Accept: application/json" http://localhost:8000/api/v1/items`.
- The 61st request within a minute returns 429; a request without a token gives a JSON 401; a token without the required ability gives 403.


## Addenda

### Search example `POST /api/v1/items/search`

```json
{
  "search": {
    "text": {"value": "billing", "mode": "words"},
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

### Create example `POST /api/v1/items/mutate`

```json
{
  "mutate": [
    {
      "operation": "create",
      "attributes": {
        "title": "Billing revamp",
        "description": "Project to revamp the billing system",
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

### Action example `POST /api/v1/items/actions/assign`

```json
{
  "resources": [7, 8, 9],
  "fields": [{"name": "assignee_id", "value": 3}]
}
```

Response: `200` with `{"data":{"impacted":3,"result":null}}` if all three records passed the checks and were processed.

### Comment create example `POST /api/v1/comments/mutate`

```json
{
  "mutate": [
    {
      "operation": "create",
      "attributes": {"item_id": 7, "body": "Confirm the scope of the first release"}
    }
  ]
}
```

`user_id` is absent from the request: the current user becomes the author.

### Alternatives

**Custom controllers + Laravel API Resources + Scribe.** Gives the familiar CRUD routes, but duplicates the fields/rules/relations of four models and requires manual documentation maintenance. Rejected before implementation.

**Two resources instead of four.** Items + Users cover the basic scenario, but Labels and Comments are left without a unified API, and Item relations have to be hidden or serialized in a special way. That is why four already-related models were chosen, without adding new domain entities.

**Lomkit + Scramble — recommended.** Typed declarations drive search, records, the action, validation, policies and OpenAPI. The trade-off is the package's own format: search/mutate use POST JSON, page pagination and non-standard CRUD responses; this is accepted as the public v1 contract.

### Open questions

- Before the production release, define the access policy for the `/docs/api` UI: public viewing or restriction through Scramble's standard middleware/gate. This does not affect generation or the API contract.
- A separate RFC should define user deletion, role assignment and restore for Items/Comments: these operations change the lifecycle of related records and must not appear as a side effect of the current implementation.
- As data grows, evaluate moving from `Contains` to `FullText`/Scout and the required indexes.

### Sources

- Lomkit introduction: https://laravel-rest-api.lomkit.com/getting-started/introduction
- Installation and requirements: https://laravel-rest-api.lomkit.com/getting-started/installation
- Resources and routes: https://laravel-rest-api.lomkit.com/resources/basics
- Search: https://laravel-rest-api.lomkit.com/endpoints/search
- Text search: https://laravel-rest-api.lomkit.com/search/text-search
- Mutate: https://laravel-rest-api.lomkit.com/writing/mutate
- Actions: https://laravel-rest-api.lomkit.com/endpoints/actions
- Details: https://laravel-rest-api.lomkit.com/digging-deeper/details
- Relations: https://laravel-rest-api.lomkit.com/resources/relations
- Events and hooks: https://laravel-rest-api.lomkit.com/digging-deeper/events-and-hooks
- OpenAPI: https://laravel-rest-api.lomkit.com/digging-deeper/openapi
- Removal of the built-in documentation generator in Lomkit 3: https://github.com/Lomkit/laravel-rest-api/blob/v3.4.0/UPGRADE.md#built-in-documentation-generator-removed
- Scramble export: https://scramble.dedoc.co/usage/export
- Scribe API Resources and response calls: https://scribe.knuckles.wtf/laravel/documenting/responses

## Notes

replace api package to https://laravel-rest-api.lomkit.com/

https://laravel-rest-api.lomkit.com/getting-started/introduction

https://laravel-rest-api.lomkit.com/getting-started/installation

goals
- a single approach to rest api, models and resources
- search, actions and https://laravel-rest-api.lomkit.com/writing/mutate

and we want auto documentation too — so the resulting api has auto docs

let us check that Scribe is absent — if it is there, remove it

and for Metrics we also need to put together our own namespace and the same kind of routes — like adding metrics, reading them, etc.

each metric must have a key and a label

my mistake — there are no metrics here at all, so we don't need them; let's pick something else — for example items & users, or whatever gives 2-3-4 resources to manage through the rest api
