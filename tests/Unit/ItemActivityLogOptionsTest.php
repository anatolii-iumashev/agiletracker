<?php

declare(strict_types=1);

use App\Models\Item;

it('logs only the tracked item attributes', function () {
    $options = (new Item)->getActivitylogOptions();

    expect($options->logAttributes)->toBe(['title', 'assignee_id', 'parent_id', 'meta', 'is_epic'])
        ->and($options->logOnlyDirty)->toBeTrue()
        ->and($options->logEmptyChanges)->toBeFalse();
});
