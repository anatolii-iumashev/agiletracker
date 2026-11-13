<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Item;
use App\Models\User;
use Spatie\Permission\Models\Role;

// ─── Create ──────────────────────────────────────────────────────────────

test('create fills the current user as the author', function () {
    $author = actingAsApi(['comments:write']);
    $item = Item::factory()->create(['meta' => ['type' => 'task']]);

    $response = $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [[
            'operation' => 'create',
            'attributes' => ['item_id' => $item->id, 'body' => 'Уточнить состав первого релиза'],
        ]],
        'response' => ['selects' => [['field' => 'id'], ['field' => 'body'], ['field' => 'user_id']]],
    ])->assertOk();

    $comment = Comment::firstOrFail();

    expect($comment->user_id)->toBe($author->getKey())
        ->and($comment->body)->toBe('Уточнить состав первого релиза')
        ->and($response->json('records.0.user_id'))->toBe($author->getKey());
});

test('the author cannot be chosen by the client', function () {
    actingAsApi(['comments:write']);
    $item = Item::factory()->create(['meta' => ['type' => 'task']]);
    $other = User::factory()->create();

    $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => [
            'item_id' => $item->id,
            'body' => 'Spoofed',
            'user_id' => $other->id,
        ]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes');

    expect(Comment::count())->toBe(0);
});

test('create requires an existing, non-trashed item', function () {
    actingAsApi(['comments:write']);

    $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['body' => 'No item']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.item_id');

    $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['item_id' => 999999, 'body' => 'Ghost']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.item_id');

    $trashed = Item::factory()->create(['meta' => ['type' => 'task']]);
    $trashed->delete();

    $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['item_id' => $trashed->id, 'body' => 'Late']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.item_id');
});

// ─── Update / delete ─────────────────────────────────────────────────────

test('only the author or an admin may update a comment', function () {
    $author = actingAsApi(['comments:write']);
    $item = Item::factory()->create(['meta' => ['type' => 'task']]);
    $comment = Comment::create(['item_id' => $item->id, 'user_id' => $author->id, 'body' => 'Original']);

    actingAsApi(['comments:write'], User::factory()->create());

    $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $comment->id, 'attributes' => ['body' => 'Hijacked']]],
    ])->assertForbidden();

    expect($comment->fresh()->body)->toBe('Original');

    actingAsApi(['comments:write'], $author);

    $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $comment->id, 'attributes' => ['body' => 'Edited']]],
    ])->assertOk();

    expect($comment->fresh()->body)->toBe('Edited');
});

test('an admin may update and delete any comment', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $item = Item::factory()->create(['meta' => ['type' => 'task']]);
    $comment = Comment::create(['item_id' => $item->id, 'user_id' => User::factory()->create()->id, 'body' => 'Original']);

    actingAsApi(['comments:write'], $admin);

    $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $comment->id, 'attributes' => ['body' => 'Moderated']]],
    ])->assertOk();

    $this->deleteJson('/api/v1/comments', ['resources' => [$comment->id]])->assertOk();

    expect(Comment::withTrashed()->find($comment->id)->trashed())->toBeTrue();
});

test('a comment updates only its body', function () {
    $author = actingAsApi(['comments:write']);
    $item = Item::factory()->create(['meta' => ['type' => 'task']]);
    $other = Item::factory()->create(['meta' => ['type' => 'task']]);
    $comment = Comment::create(['item_id' => $item->id, 'user_id' => $author->id, 'body' => 'Original']);

    $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $comment->id, 'attributes' => ['item_id' => $other->id]]],
    ])->assertUnprocessable();

    expect($comment->fresh()->item_id)->toBe($item->id);
});

// ─── Perimeter ───────────────────────────────────────────────────────────

test('comments of a trashed item leave search, mutate and delete', function () {
    $author = actingAsApi(['comments:read', 'comments:write', 'items:read', 'items:write']);
    $item = Item::factory()->create(['meta' => ['type' => 'task']]);
    $comment = Comment::create(['item_id' => $item->id, 'user_id' => $author->id, 'body' => 'Hidden soon']);

    $this->deleteJson('/api/v1/items', ['resources' => [$item->id]])->assertOk();

    $this->postJson('/api/v1/comments/search', [])->assertOk()->assertJsonPath('total', 0);

    $this->postJson('/api/v1/comments/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $comment->id, 'attributes' => ['body' => 'Edited']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.key');

    $this->deleteJson('/api/v1/comments', ['resources' => [$comment->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('resources.0');

    expect(Comment::find($comment->id))->not->toBeNull();
});

test('comments include their item and author', function () {
    $author = actingAsApi(['comments:read']);
    $item = Item::factory()->create(['meta' => ['type' => 'task']]);
    Comment::create(['item_id' => $item->id, 'user_id' => $author->id, 'body' => 'Hello']);

    $response = $this->postJson('/api/v1/comments/search', [
        'search' => [
            'filters' => [
                ['field' => 'item_id', 'operator' => '=', 'value' => $item->id],
                ['field' => 'user_id', 'operator' => '=', 'value' => $author->id],
            ],
            'includes' => [
                ['relation' => 'item', 'selects' => [['field' => 'title']]],
                ['relation' => 'author', 'selects' => [['field' => 'name']]],
            ],
        ],
    ])->assertOk();

    expect($response->json('data.0.item.title'))->toBe($item->title)
        ->and($response->json('data.0.author.name'))->toBe($author->name)
        ->and($response->json('data.0.body'))->toBe('Hello');
});
