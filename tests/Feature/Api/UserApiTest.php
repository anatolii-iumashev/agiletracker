<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

function adminUser(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

// ─── Read model ──────────────────────────────────────────────────────────

test('users are searchable and filterable by the safe fields', function () {
    actingAsApi(['users:read']);
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $response = $this->postJson('/api/v1/users/search', [
        'search' => [
            'text' => ['value' => 'ada'],
            'filters' => [['field' => 'email', 'operator' => '=', 'value' => 'ada@example.com']],
        ],
    ])->assertOk();

    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.name'))->toBe('Ada Lovelace')
        ->and($response->json('data.0'))->not->toHaveKey('password')
        ->and($response->json('data.0'))->not->toHaveKey('remember_token')
        ->and($response->json('data.0'))->not->toHaveKey('email_verified_at');
});

test('the password can be neither selected, filtered nor sorted', function () {
    actingAsApi(['users:read']);

    $this->postJson('/api/v1/users/search', [
        'search' => ['selects' => [['field' => 'password']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('search.selects.0.field');

    $this->postJson('/api/v1/users/search', [
        'search' => ['filters' => [['field' => 'password', 'operator' => '=', 'value' => 'secret']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('search.filters.0.field');

    $this->postJson('/api/v1/users/search', [
        'search' => ['sorts' => [['field' => 'password', 'direction' => 'asc']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('search.sorts.0.field');
});

test('read-only user relations are includable', function () {
    $author = actingAsApi(['users:read']);
    $assignee = User::factory()->create();

    $item = Item::factory()->assignedTo($assignee)->create(['meta' => ['type' => 'task'], 'reporter_id' => $author->id]);
    Comment::create(['item_id' => $item->id, 'user_id' => $author->id, 'body' => 'Hello']);

    $response = $this->postJson('/api/v1/users/search', [
        'search' => [
            'filters' => [['field' => 'id', 'operator' => '=', 'value' => $assignee->id]],
            'includes' => [['relation' => 'assignedItems', 'selects' => [['field' => 'title']]]],
        ],
    ])->assertOk();

    expect($response->json('data.0.assigned_items.0.title'))->toBe($item->title);

    $search = $this->postJson('/api/v1/users/search', [
        'search' => [
            'filters' => [['field' => 'id', 'operator' => '=', 'value' => $author->id]],
            'includes' => [['relation' => 'comments']],
        ],
    ])->assertOk();

    expect($search->json('data.0.comments.0.body'))->toBe('Hello');
});

// ─── Admin writes ────────────────────────────────────────────────────────

test('an admin can create a user and the password is hashed', function () {
    actingAsApi(['users:write'], adminUser());

    $response = $this->postJson('/api/v1/users/mutate', [
        'mutate' => [[
            'operation' => 'create',
            'attributes' => [
                'name' => 'New Person',
                'email' => 'new@example.com',
                'password' => 'a-very-long-secret',
            ],
        ]],
        'response' => ['selects' => [['field' => 'id'], ['field' => 'email']]],
    ])->assertOk();

    $created = User::where('email', 'new@example.com')->firstOrFail();

    expect(Hash::check('a-very-long-secret', $created->password))->toBeTrue()
        ->and($response->json('records.0.password'))->toBeNull()
        ->and($response->json('records.0.email'))->toBe('new@example.com');
});

test('a non-admin with the write ability is forbidden', function () {
    actingAsApi(['users:write']);

    $this->postJson('/api/v1/users/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'password' => 'a-very-long-secret',
        ]]],
    ])->assertForbidden();

    expect(User::where('email', 'nope@example.com')->exists())->toBeFalse();
});

test('the email must be unique and the password at least 12 characters', function () {
    actingAsApi(['users:write'], adminUser());
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/users/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => [
            'name' => 'Dup',
            'email' => 'taken@example.com',
            'password' => 'a-very-long-secret',
        ]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.email');

    $this->postJson('/api/v1/users/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => [
            'name' => 'Short',
            'email' => 'short@example.com',
            'password' => 'short',
        ]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.password');
});

test('an update keeps its own email and refuses another user email', function () {
    actingAsApi(['users:write'], adminUser());
    $user = User::factory()->create(['email' => 'mine@example.com']);
    User::factory()->create(['email' => 'other@example.com']);

    $this->postJson('/api/v1/users/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $user->id, 'attributes' => [
            'name' => 'Renamed',
            'email' => 'mine@example.com',
        ]]],
    ])->assertOk();

    expect($user->fresh()->name)->toBe('Renamed');

    $this->postJson('/api/v1/users/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $user->id, 'attributes' => ['email' => 'other@example.com']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.email');
});

test('roles and permissions are never exposed nor writable', function () {
    actingAsApi(['users:read', 'users:write'], adminUser());

    $details = $this->getJson('/api/v1/users?with=relations,actions,gates,text')->assertOk();

    expect(collect($details->json('data.relations'))->pluck('relation')->all())
        ->toBe(['assignedItems', 'reportedItems', 'comments'])
        ->and($details->json('data.filters'))->toBeNull();

    $this->postJson('/api/v1/users/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => [
            'name' => 'Role Writer',
            'email' => 'role@example.com',
            'password' => 'a-very-long-secret',
            'roles' => [['operation' => 'sync', 'key' => [1]]],
        ]]],
    ])->assertUnprocessable();
});
