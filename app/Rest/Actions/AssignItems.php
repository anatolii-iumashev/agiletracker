<?php

declare(strict_types=1);

namespace App\Rest\Actions;

use App\Models\Item;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Actions\ActionTransaction;
use Lomkit\Rest\Fields\Integer;

/**
 * Assign an item to a user, or clear its assignee when `assignee_id` is null.
 *
 * @extends Action<Item>
 */
class AssignItems extends Action
{
    public ?string $name = 'Assign items';

    public ?string $uriKey = 'assign';

    public ActionTarget $target = ActionTarget::Resources;

    public ?int $maxResources = 100;

    public ActionTransaction $transaction = ActionTransaction::Whole;

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return ['viewAny'];
    }

    public function authorizeEach(): ?string
    {
        return 'update';
    }

    public function fields(): array
    {
        return [
            Integer::make('assignee_id')
                ->nullable()
                ->rules('exists:users,id'),
        ];
    }

    /**
     * @param  Collection<int, Item>  $models
     */
    public function handle(ActionFields $fields, Collection $models): void
    {
        $assigneeId = $fields->assignee_id;

        foreach ($models as $item) {
            $item->assignee_id = $assigneeId;
            $item->save();
        }
    }
}
