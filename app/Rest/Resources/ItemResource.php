<?php

declare(strict_types=1);

namespace App\Rest\Resources;

use App\Models\Item;
use App\Rest\Actions\AssignItems;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;

#[WithoutPerimeter]
class ItemResource extends Resource
{
    public static string $model = Item::class;

    /**
     * @return list<string>
     */
    public function abilities(Endpoint $endpoint): array
    {
        return match ($endpoint) {
            Endpoint::Details, Endpoint::Search => ['viewAny', 'items:read'],
            Endpoint::Mutate, Endpoint::Destroy => ['viewAny', 'items:write'],
            Endpoint::Operate => ['items:write'],
            default => parent::abilities($endpoint),
        };
    }

    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('title')
                ->searchable()
                ->rules('max:255')
                ->creationRules('required')
                ->examples('Billing revamp'),
            Text::make('description')
                ->nullable()
                ->searchable()
                ->examples('Project to revamp the billing system'),
            Text::make('type')
                ->virtual()
                ->nullable()
                ->requires(['meta'])
                ->resolveUsing(fn (Item $item): ?string => $item->meta['type'] ?? null)
                ->fillUsing(function (Item $item, mixed $value): void {
                    $item->meta = [...($item->meta ?? []), 'type' => (string) $value];
                })
                ->filterable(['=', '!='])
                ->filterUsing(fn (Builder $query, string $operator, mixed $value): Builder => $query->where('meta->type', $operator, $value))
                ->rules('in:task,bug,feature')
                ->creationRules('required')
                ->updateRules('filled')
                ->examples('task'),
            Boolean::make('is_epic'),
            Integer::make('parent_id')
                ->nullable()
                ->rules(Rule::exists('items', 'id')->whereNull('deleted_at')),
            Integer::make('assignee_id')
                ->nullable()
                ->rules(Rule::exists('users', 'id')),
            Integer::make('reporter_id')->nullable()->readonly(),
            Date::make('due_date')->nullable(),
            DateTime::make('created_at')->readonly(),
            DateTime::make('updated_at')->readonly(),
        ];
    }

    public function relations(): array
    {
        return [
            BelongsTo::make('parent', self::class)->onlyOperations(),
            HasMany::make('children', self::class)->onlyOperations(),
            BelongsTo::make('assignee', UserResource::class)->onlyOperations(),
            BelongsTo::make('reporter', UserResource::class)->onlyOperations(),
            HasMany::make('comments', CommentResource::class)->onlyOperations(),
            BelongsToMany::make('labels', LabelResource::class)
                ->onlyOperations(
                    MutationOperation::Attach,
                    MutationOperation::Detach,
                    MutationOperation::Sync,
                ),
        ];
    }

    public function actions(): array
    {
        return [
            AssignItems::class,
        ];
    }

    public function mutating(MutationNode $entry, Model $model): void
    {
        if ($entry->operation === MutationOperation::Create) {
            $model->reporter_id = $this->request()->user()?->getKey();
        }

        if ($entry->operation === MutationOperation::Update && is_array($entry->attributes) && array_key_exists('parent_id', $entry->attributes)) {
            $this->assertAcyclicParent($entry, (int) $entry->key, $entry->attributes['parent_id']);
        }
    }

    public function destroying(Model $model): void
    {
        if ($model->children()->exists()) {
            throw new HttpResponseException(response()->json([
                'message' => __('Items with active children cannot be deleted.'),
            ], 409));
        }
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     */
    private function assertAcyclicParent(MutationNode $entry, int $itemId, mixed $parentId): void
    {
        $current = $parentId === null ? null : (int) $parentId;
        $visited = [];

        while ($current !== null) {
            if ($current === $itemId || isset($visited[$current])) {
                throw ValidationException::withMessages([
                    $entry->path.'.attributes.parent_id' => __('The parent must not be the item itself or one of its descendants.'),
                ]);
            }

            $visited[$current] = true;
            $stored = Item::query()->whereKey($current)->value('parent_id');
            $current = $stored === null ? null : (int) $stored;
        }
    }
}
