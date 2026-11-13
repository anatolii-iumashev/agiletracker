<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Label;

// ─── Create / read ───────────────────────────────────────────────────────

test('a label can be created with a default color', function () {
    actingAsApi(['labels:write']);

    $response = $this->postJson('/api/v1/labels/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['name' => 'billing']]],
        'response' => ['selects' => [['field' => 'name'], ['field' => 'color']]],
    ])->assertOk();

    expect($response->json('records.0.name'))->toBe('billing')
        ->and($response->json('records.0.color'))->toBe('#6366f1');
});

test('the color must be a hex triplet and the name is required', function () {
    actingAsApi(['labels:write']);

    $this->postJson('/api/v1/labels/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => ['name' => 'bad', 'color' => 'red']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.color');

    $this->postJson('/api/v1/labels/mutate', [
        'mutate' => [['operation' => 'create', 'attributes' => []]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.attributes.name');
});

test('labels are searchable by name', function () {
    actingAsApi(['labels:read']);
    Label::create(['name' => 'billing']);
    Label::create(['name' => 'design']);

    $response = $this->postJson('/api/v1/labels/search', [
        'search' => ['text' => ['value' => 'bill']],
    ])->assertOk();

    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.name'))->toBe('billing');
});

test('the items relation is read-only and includable', function () {
    $user = actingAsApi(['labels:read', 'labels:write']);
    $item = Item::factory()->create(['meta' => ['type' => 'task'], 'reporter_id' => $user->id]);
    $label = Label::create(['name' => 'billing']);
    $label->items()->attach($item);

    $response = $this->postJson('/api/v1/labels/search', [
        'search' => [
            'filters' => [['field' => 'id', 'operator' => '=', 'value' => $label->id]],
            'includes' => [['relation' => 'items', 'selects' => [['field' => 'title']]]],
        ],
    ])->assertOk();

    expect($response->json('data.0.items.0.title'))->toBe($item->title);

    $this->postJson('/api/v1/labels/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $label->id, 'relations' => [
            'items' => [['operation' => 'attach', 'key' => $item->id]],
        ]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('mutate.0.relations.items');
});

// ─── Delete ──────────────────────────────────────────────────────────────

test('deleting a label removes only the pivot links', function () {
    actingAsApi(['labels:write', 'labels:read']);
    $item = Item::factory()->create(['meta' => ['type' => 'task']]);
    $label = Label::create(['name' => 'billing']);
    $label->items()->attach($item);

    $this->deleteJson('/api/v1/labels', ['resources' => [$label->id]])->assertOk();

    expect(Label::find($label->id))->toBeNull()
        ->and(Item::find($item->id))->not->toBeNull()
        ->and($item->labels()->count())->toBe(0);
});

test('label updates accept one field at a time', function () {
    actingAsApi(['labels:write']);
    $label = Label::create(['name' => 'billing', 'color' => '#111111']);

    $this->postJson('/api/v1/labels/mutate', [
        'mutate' => [['operation' => 'update', 'key' => $label->id, 'attributes' => ['name' => 'billing-v2']]],
    ])->assertOk();

    expect($label->fresh()->name)->toBe('billing-v2')
        ->and($label->fresh()->color)->toBe('#111111');
});
