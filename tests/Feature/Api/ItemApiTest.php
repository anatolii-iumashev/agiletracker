<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Item;
use App\Models\Label;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// ─── Search ──────────────────────────────────────────────────────────────

test('text search reads title and description', function () {
    actingAsApi(['items:read']);
    Item::factory()->create(['title' => 'Billing migration', 'description' => null]);
    Item::factory()->create(['title' => 'Unrelated', 'description' => 'billing notes']);
    Item::factory()->create(['title' => 'Nothing here', 'description' => null]);

    $response = $this->postJson('/api/v1/items/search', [
        'search' => ['text' => ['value' => 'billing', 'mode' => 'words']],
    ])->assertOk();

    expect($response->json('total'))->toBe(2);
});

test('filters read is_epic, the meta type and relations', function () {
    actingAsApi(['items:read']);
    $label = Label::create(['name' => 'billing']);

    $epic = Item::factory()->epic()->create(['meta' => ['type' => 'task']]);
    $epic->labels()->attach($label);

    Item::factory()->create(['meta' => ['type' => 'bug']]);

    $response = $this->postJson('/api/v1/items/search', [
        'search' => ['filters' => [
            ['field' => 'is_epic', 'operator' => '=', 'value' => true],
            ['field' => 'type', 'operator' => '=', 'value' => 'task'],
        ]],
    ])->assertOk();

    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.id'))->toBe($epic->id)
        ->and($response->json('data.0.type'))->toBe('task')
        ->and($response->json('data.0.is_epic'))->toBeTrue();

    $this->postJson('/api/v1/items/search', [
        'search' => ['filters' => [['field' => 'labels.name', 'operator' => '=', 'value' => 'billing']]],
    ])->assertOk()->assertJsonPath('total', 1);
});

test('includes and aggregates do not change the contract', function () {
    actingAsApi(['items:read']);
    $parent = Item::factory()->create(['meta' => ['type' => 'task']]);
    $label = Label::create(['name' => 'backend']);
    $parent->labels()->attach($label);
    Item::factory()->count(2)->childOf($parent)->create();
    Comment::create(['item_id' => $parent->id, 'user_id' => User::factory()->create()->id, 'body' => 'hello']);

    $response = $this->postJson('/api/v1/items/search', [
        'search' => [
            'filters' => [['field' => 'id', 'operator' => '=', 'value' => $parent->id]],
            'includes' => [
                ['relation' => 'labels', 'selects' => [['field' => 'name']]],
                ['relation' => 'comments', 'selects' => [['field' => 'body']]],
            ],
            'aggregates' => [['relation' => 'children', 'type' => 'count']],
        ],
    ])->assertOk();

    expect($response->json('data.0.labels.0.name'))->toBe('backend')
        ->and($response->json('data.0.comments.0.body'))->toBe('hello')
        ->and($response->json('data.0.children_count'))->toBe(2);
});

test('the type of a legacy item without meta reads as null', function () {
    actingAsApi(['items:read']);
    Item::factory()->create(['meta' => null]);

    $response = $this->postJson('/api/v1/items/search', [])->assertOk();

    expect($response->json('data.0.type'))->toBeNull();
});

// ─── Mutate ──────────────────────────────────────────────────────────────

test('create fills the reporter, keeps meta keys and syncs labels', function () {
    $user = actingAsApi(['items:write']);
    $labels = Label::create(['name' => 'one']);
    $second = Label::create(['name' => 'two']);

    $response = $this->postJson('/api/v1/items/mutate', [
        'mutate' => [[
            'operation' => 'create',
            'attributes' => [
                'title' => 'Развитие биллинга',
                'description' => 'Проект по развитию биллинга',
                'type' => 'task',
                'is_epic' => true,
                'due_date' => '2026-11-20',
            ],
            'relations' => [
                'labels' => [['operation' => 'sync', 'key' => [$labels->id, $second->id]]],
            ],
        ]],
        'response' => ['selects' => [['field' => 'id'], ['field' => 'title'], ['field' => 'type'], ['field' => 'is_epic']]],
    ])->assertOk();

    $item = Item::firstOrFail();

    expect($item->title)->toBe('Развитие биллинга')
        ->and($item->is_epic)->toBeTrue()
        ->and($item->meta)->toBe(['type' => 'task'])
        ->and($item->reporter_id)->toBe($user->getKey())
        ->and($item->labels()->count())->toBe(2)
        ->and($response->json('operations.0.operation'))->toBe('create')
        ->and($response->json('records.0.type'))->toBe('task')
        ->and($response->json('records.0.is_epic'))->toBeTrue();
});

test('a hybrid task container is a task with is_epic true', function () {
    actingAsApi(['items:write']);

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['title' => 'Hybrid', 'type' => 'task', 'is_epic' => true]]],
    ])->assertOk();

    expect(Item::firstOrFail()->is_epic)->toBeTrue()
        ->and(Item::firstOrFail()->meta['type'])->toBe('task');
});

test('the type keeps the other meta keys on update', function () {
    actingAsApi(['items:write']);
    $item = Item::factory()->create(['meta' => ['type' => 'task', 'team' => 'core']]);

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $item->id, 'attributes' => ['type' => 'bug']]],
    ])->assertOk();

    expect($item->fresh()->meta)->toBe(['type' => 'bug', 'team' => 'core']);
});

test('create requires a title and a known type', function () {
    actingAsApi(['items:write']);

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['type' => 'task']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.title');

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['title' => 'No type']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.type');

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['title' => 'Bad', 'type' => 'epic']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.type');
});

test('reporter, meta and nested hierarchy writes are refused', function () {
    actingAsApi(['items:write']);
    $parent = Item::factory()->create(['meta' => ['type' => 'task']]);

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['title' => 'A', 'type' => 'task', 'reporter_id' => 99]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes');

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['title' => 'A', 'type' => 'task', 'meta' => ['x' => 1]]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes');

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [[
            'operation' => 'update',
            'key' => $parent->id,
            'relations' => ['children' => [['operation' => 'create', 'attributes' => ['title' => 'Child', 'type' => 'task']]]],
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.relations.children');
});

test('an update cannot point an item at itself or a descendant', function () {
    actingAsApi(['items:write']);
    $root = Item::factory()->create(['meta' => ['type' => 'task']]);
    $child = Item::factory()->childOf($root)->create(['meta' => ['type' => 'task']]);
    $grandchild = Item::factory()->childOf($child)->create(['meta' => ['type' => 'task']]);

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $root->id, 'attributes' => ['parent_id' => $root->id]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.parent_id');

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $root->id, 'attributes' => ['parent_id' => $grandchild->id]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.parent_id');

    expect($root->fresh()->parent_id)->toBeNull()
        ->and($grandchild->fresh()->parent_id)->toBe($child->id);
});

test('the parent must exist and not be trashed', function () {
    actingAsApi(['items:write']);
    $parent = Item::factory()->create(['meta' => ['type' => 'task']]);
    $parent->delete();

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['title' => 'A', 'type' => 'task', 'parent_id' => $parent->id]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.parent_id');
});

test('updating is_epic leaves the type and the hierarchy untouched', function () {
    actingAsApi(['items:write']);
    $parent = Item::factory()->create(['meta' => ['type' => 'task']]);
    $item = Item::factory()->childOf($parent)->create(['meta' => ['type' => 'bug']]);

    $this->postJson('/api/v1/items/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $item->id, 'attributes' => ['is_epic' => true]]],
    ])->assertOk();

    expect($item->fresh()->is_epic)->toBeTrue()
        ->and($item->fresh()->meta)->toBe(['type' => 'bug'])
        ->and($item->fresh()->parent_id)->toBe($parent->id);
});

// ─── Destroy ─────────────────────────────────────────────────────────────

test('a leaf item is soft deleted', function () {
    actingAsApi(['items:write', 'items:read']);
    $item = Item::factory()->create(['meta' => ['type' => 'task']]);

    $response = $this->deleteJson('/api/v1/items', ['resources' => [$item->id]])->assertOk();

    expect($response->json('data.0.id'))->toBe($item->id)
        ->and(Item::withTrashed()->find($item->id)->trashed())->toBeTrue();

    $this->postJson('/api/v1/items/search', [])->assertOk()->assertJsonPath('total', 0);
});

test('an item with active children answers 409 and keeps the row', function () {
    actingAsApi(['items:write']);
    $parent = Item::factory()->create(['meta' => ['type' => 'task']]);
    Item::factory()->childOf($parent)->create(['meta' => ['type' => 'task']]);

    $this->deleteJson('/api/v1/items', ['resources' => [$parent->id]])
        ->assertStatus(409);

    expect(Item::find($parent->id))->not->toBeNull();
});

test('trashed items are refused as delete targets', function () {
    actingAsApi(['items:write']);
    $item = Item::factory()->create(['meta' => ['type' => 'task']]);
    $item->delete();

    $this->deleteJson('/api/v1/items', ['resources' => [$item->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('resources.0');
});

// ─── Assign action ───────────────────────────────────────────────────────

test('the assign action sets, clears and reports the impacted rows', function () {
    actingAsApi(['items:write', 'items:read']);
    $items = Item::factory()->count(3)->create(['meta' => ['type' => 'task']]);
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/items/actions/assign', [
        'resources' => $items->pluck('id')->all(),
        'fields' => [['name' => 'assignee_id', 'value' => $user->id]],
    ])->assertOk();

    expect($response->json('data.impacted'))->toBe(3);
    expect(Item::where('assignee_id', $user->id)->count())->toBe(3);

    $this->postJson('/api/v1/items/actions/assign', [
        'resources' => $items->pluck('id')->all(),
        'fields' => [['name' => 'assignee_id', 'value' => null]],
    ])->assertOk()->assertJsonPath('data.impacted', 3);

    expect(Item::whereNotNull('assignee_id')->count())->toBe(0);
});

test('the assign action refuses more than 100 resources and any search', function () {
    actingAsApi(['items:write']);
    $items = Item::factory()->count(101)->create(['meta' => ['type' => 'task']]);

    $this->postJson('/api/v1/items/actions/assign', [
        'resources' => $items->pluck('id')->all(),
        'fields' => [['name' => 'assignee_id', 'value' => null]],
    ])->assertUnprocessable();

    $this->postJson('/api/v1/items/actions/assign', [
        'search' => [],
        'fields' => [['name' => 'assignee_id', 'value' => null]],
    ])->assertUnprocessable();
});

test('the assign action rolls back every row when one fails', function () {
    actingAsApi(['items:write']);
    $items = Item::factory()->count(2)->create(['meta' => ['type' => 'task']]);
    $user = User::factory()->create();

    $this->postJson('/api/v1/items/actions/assign', [
        'resources' => [...$items->pluck('id')->all(), 999999],
        'fields' => [['name' => 'assignee_id', 'value' => $user->id]],
    ])->assertUnprocessable();

    expect(Item::whereNotNull('assignee_id')->count())->toBe(0);
});

test('an unknown item action is a 404 for an authorized user', function () {
    actingAsApi(['items:write']);

    $this->postJson('/api/v1/items/actions/unknown', [])->assertNotFound();
});

test('includes and aggregates run in a bounded number of queries', function () {
    actingAsApi(['items:read']);

    foreach (Item::factory()->count(10)->create(['meta' => ['type' => 'task']]) as $index => $item) {
        $item->labels()->attach(Label::create(['name' => 'label-'.$index]));
        Item::factory()->count(2)->childOf($item)->create(['meta' => ['type' => 'task']]);
    }

    DB::enableQueryLog();

    $this->postJson('/api/v1/items/search', [
        'search' => [
            'includes' => [['relation' => 'labels', 'selects' => [['field' => 'name']]]],
            'aggregates' => [['relation' => 'children', 'type' => 'count']],
        ],
    ])->assertOk()->assertJsonPath('total', 30);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThan(12);
});
