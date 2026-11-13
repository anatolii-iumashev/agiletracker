<?php

declare(strict_types=1);

use App\Models\Item;

it('stores tracked changes in attribute_changes', function () {
    $item = Item::factory()->create(['title' => 'Old title']);

    $item->update(['title' => 'New title']);

    $activity = $item->activitiesAsSubject()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('updated')
        ->and($activity->attribute_changes->get('attributes')['title'])->toBe('New title')
        ->and($activity->attribute_changes->get('old')['title'])->toBe('Old title')
        ->and($activity->properties)->toBeEmpty();
});

it('does not log updates to untracked attributes', function () {
    $item = Item::factory()->create();

    $logged = $item->activitiesAsSubject()->count();

    $item->update(['description' => 'Only the description changes']);

    expect($item->activitiesAsSubject()->count())->toBe($logged);
});
