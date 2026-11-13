<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

// ─── Routing ─────────────────────────────────────────────────────────────

test('api v1 exposes exactly the declared endpoints', function () {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn ($route) => collect($route->methods())
            ->reject(fn (string $method): bool => $method === 'HEAD')
            ->map(fn (string $method): string => $method.' '.$route->uri()))
        ->sort()
        ->values()
        ->all();

    expect($routes)->toBe(collect([
        'GET api/v1/items',
        'POST api/v1/items/search',
        'POST api/v1/items/mutate',
        'POST api/v1/items/actions/assign',
        'POST api/v1/items/actions/{action}',
        'DELETE api/v1/items',
        'GET api/v1/users',
        'POST api/v1/users/search',
        'POST api/v1/users/mutate',
        'GET api/v1/labels',
        'POST api/v1/labels/search',
        'POST api/v1/labels/mutate',
        'DELETE api/v1/labels',
        'GET api/v1/comments',
        'POST api/v1/comments/search',
        'POST api/v1/comments/mutate',
        'DELETE api/v1/comments',
    ])->sort()->values()->all());
});

test('forbidden endpoints are not registered', function (string $method, string $uri) {
    $response = $this->json($method, $uri);

    expect($response->status())->toBeIn([404, 405]);
})->with([
    ['DELETE', '/api/v1/users'],
    ['POST', '/api/v1/items/restore'],
    ['DELETE', '/api/v1/items/force'],
    ['POST', '/api/v1/users/restore'],
    ['POST', '/api/v1/labels/actions/assign'],
    ['POST', '/api/v1/items/metrics/count'],
]);

// ─── Authentication & authorization ──────────────────────────────────────

test('a request without a token is rejected with json', function () {
    $this->getJson('/api/v1/items')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/json');

    // Even without an Accept header the API answers JSON instead of redirecting.
    $this->get('/api/v1/items')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/json');
});

test('a token without the read ability is forbidden', function () {
    actingAsApi(['users:read']);

    $this->getJson('/api/v1/items')->assertForbidden();
});

test('a read ability cannot write', function () {
    actingAsApi(['items:read']);

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['title' => 'A', 'type' => 'task']]],
    ])->assertForbidden();
});

test('a write ability cannot read', function () {
    actingAsApi(['items:write']);

    $this->postJson('/api/v1/items/search', [])->assertForbidden();
    $this->getJson('/api/v1/items')->assertForbidden();
});

test('abilities are checked per resource', function () {
    actingAsApi(['items:read', 'items:write']);

    $this->postJson('/api/v1/users/search', [])->assertForbidden();
    $this->postJson('/api/v1/labels/search', [])->assertForbidden();
    $this->postJson('/api/v1/comments/search', [])->assertForbidden();
});

test('the api limiter answers 429 past its limit', function () {
    actingAsApi(['items:read']);

    RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(2)
        ->by($request->user()->getKey()));

    $this->getJson('/api/v1/items')->assertOk();
    $this->getJson('/api/v1/items')->assertOk();
    $this->getJson('/api/v1/items')->assertTooManyRequests();
});

// ─── Shared contract ─────────────────────────────────────────────────────

test('details describes fields, limits and the default order', function () {
    actingAsApi(['items:read']);

    $response = $this->getJson('/api/v1/items')->assertOk();

    $fields = collect($response->json('data.fields'))->pluck('name');

    expect($fields)->toContain('id', 'title', 'description', 'type', 'is_epic', 'parent_id', 'assignee_id', 'reporter_id', 'due_date', 'created_at', 'updated_at')
        ->and($response->json('data.limits'))->toBe([15, 25, 50, 100]);
});

test('search defaults to the first page of 15 items in descending id order', function () {
    actingAsApi(['items:read']);
    $items = Item::factory()->count(20)->create();

    $response = $this->postJson('/api/v1/items/search', [])->assertOk();

    expect($response->json('per_page'))->toBe(15)
        ->and($response->json('total'))->toBe(20)
        ->and(collect($response->json('data'))->pluck('id')->all())
        ->toBe($items->sortByDesc('id')->take(15)->pluck('id')->values()->all());
});

test('a second page returns the remainder', function () {
    actingAsApi(['items:read']);
    Item::factory()->count(18)->create();

    $response = $this->postJson('/api/v1/items/search', ['search' => ['page' => 2]])->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('current_page'))->toBe(2);
});

test('an empty result is a 200 with an empty data list', function () {
    actingAsApi(['items:read']);

    $response = $this->postJson('/api/v1/items/search', [])->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->json('total'))->toBe(0);
});

test('searching trashed rows is refused with 422', function () {
    actingAsApi(['items:read']);

    $this->postJson('/api/v1/items/search', ['search' => ['trashed' => 'with']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('search.trashed');
});

test('undeclared fields, relations and operators are refused with 422', function () {
    actingAsApi(['items:read']);

    $this->postJson('/api/v1/items/search', [
        'search' => ['filters' => [['field' => 'meta', 'operator' => '=', 'value' => 'x']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('search.filters.0.field');

    $this->postJson('/api/v1/items/search', [
        'search' => ['includes' => [['relation' => 'favorites']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('search.includes.0.relation');

    $this->postJson('/api/v1/items/search', [
        'search' => ['filters' => [['field' => 'id', 'operator' => 'like', 'value' => '1']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('search.filters.0.operator');
});

test('each resource declares its own limit set', function () {
    actingAsApi(['users:read', 'labels:read', 'comments:read']);

    expect($this->getJson('/api/v1/users')->json('data.limits'))->toBe([15, 25, 50, 100])
        ->and($this->getJson('/api/v1/labels')->json('data.limits'))->toBe([15, 25, 50, 100])
        ->and($this->getJson('/api/v1/comments')->json('data.limits'))->toBe([15, 25, 50, 100]);
});

test('tokens are bound to a user and never leak into responses', function () {
    $user = User::factory()->create(['name' => 'Jane']);
    actingAsApi(['users:read'], $user);

    $response = $this->postJson('/api/v1/users/search', [])->assertOk();

    expect($response->json('data.0'))->toHaveKeys(['id', 'name', 'email', 'created_at', 'updated_at'])
        ->and($response->json('data.0'))->not->toHaveKey('password')
        ->and($response->json('data.0'))->not->toHaveKey('remember_token');
});
