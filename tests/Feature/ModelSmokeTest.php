<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Item;
use App\Models\Label;
use App\Models\User;

// ─── User Model ──────────────────────────────────────────────────────────

test('user can be created via factory', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->exists)->toBeTrue();
});

test('user has required attributes', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($user->name)->toBe('John Doe')
        ->and($user->email)->toBe('john@example.com');
});

test('password is hashed', function () {
    $user = User::factory()->create(['password' => 'secret']);

    expect($user->password)->not->toBe('secret');
});

// ─── Item Model ──────────────────────────────────────────────────────────

test('item can be created via factory', function () {
    $item = Item::factory()->create();

    expect($item)->toBeInstanceOf(Item::class)
        ->and($item->exists)->toBeTrue();
});

test('item has title', function () {
    $item = Item::factory()->create(['title' => 'Test Task']);

    expect($item->title)->toBe('Test Task');
});

test('item meta is cast to array', function () {
    $item = Item::factory()->create([
        'meta' => [
            'source' => 'roadmap',
            'severity' => 'high',
        ],
    ]);

    expect($item->fresh()->meta)->toBe([
        'source' => 'roadmap',
        'severity' => 'high',
    ]);
});

test('item can have parent-child relationship', function () {
    $parent = Item::factory()->create();
    $child = Item::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent)->not->toBeNull()
        ->and($child->parent->id)->toBe($parent->id)
        ->and($parent->children)->toHaveCount(1);
});

test('item can be assigned to user', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['assignee_id' => $user->id]);

    expect($item->assignee)->not->toBeNull()
        ->and($item->assignee->id)->toBe($user->id);
});

test('item can be labeled', function () {
    $item = Item::factory()->create();
    $label = Label::create(['name' => 'Urgent', 'color' => '#ff0000']);
    $item->labels()->attach($label);

    expect($item->labels)->toHaveCount(1)
        ->and($item->labels->first()->name)->toBe('Urgent');
});

test('item soft deletes', function () {
    $item = Item::factory()->create();
    $item->delete();

    expect(Item::count())->toBe(0)
        ->and(Item::withTrashed()->count())->toBe(1);
});

// ─── Comment Model ───────────────────────────────────────────────────────

test('comment can be created', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();
    $comment = Comment::create([
        'item_id' => $item->id,
        'user_id' => $user->id,
        'body' => 'This is a test comment.',
    ]);

    expect($comment)->toBeInstanceOf(Comment::class)
        ->and($comment->exists)->toBeTrue()
        ->and($comment->body)->toBe('This is a test comment.');
});

// ─── Label Model ─────────────────────────────────────────────────────────

test('label can be created', function () {
    $label = Label::create([
        'name' => 'Bug',
        'color' => '#ff0000',
    ]);

    expect($label)->toBeInstanceOf(Label::class)
        ->and($label->exists)->toBeTrue()
        ->and($label->name)->toBe('Bug');
});
