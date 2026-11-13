<?php

declare(strict_types=1);

namespace App\Rest\Resources;

use App\Models\Comment;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Relations\BelongsTo;

class CommentResource extends Resource
{
    public static string $model = Comment::class;

    /**
     * @return list<string>
     */
    public function abilities(Endpoint $endpoint): array
    {
        return match ($endpoint) {
            Endpoint::Details, Endpoint::Search => ['viewAny', 'comments:read'],
            Endpoint::Mutate, Endpoint::Destroy => ['viewAny', 'comments:write'],
            default => parent::abilities($endpoint),
        };
    }

    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('body')
                ->searchable()
                ->creationRules('required')
                ->examples('Уточнить состав первого релиза'),
            Integer::make('item_id')
                ->updatable(false)
                ->rules(Rule::exists('items', 'id')->whereNull('deleted_at'))
                ->creationRules('required'),
            Integer::make('user_id')->nullable()->readonly(),
            DateTime::make('created_at')->readonly(),
            DateTime::make('updated_at')->readonly(),
        ];
    }

    public function relations(): array
    {
        return [
            BelongsTo::make('item', ItemResource::class)->onlyOperations(),
            BelongsTo::make('author', UserResource::class)->onlyOperations(),
        ];
    }

    /**
     * Comments of a soft-deleted item stay in the database but leave the API.
     */
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->whereHas('item');
    }

    public function mutating(MutationNode $entry, Model $model): void
    {
        if ($entry->operation === MutationOperation::Create) {
            $model->user_id = $this->request()->user()?->getKey();
        }
    }
}
