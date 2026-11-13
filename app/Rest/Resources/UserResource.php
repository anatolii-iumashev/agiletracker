<?php

declare(strict_types=1);

namespace App\Rest\Resources;

use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\Contextual;
use Lomkit\Rest\Mutations\MutationContext;
use Lomkit\Rest\Relations\HasMany;

#[WithoutPerimeter]
class UserResource extends Resource
{
    public static string $model = User::class;

    /**
     * @return list<string>
     */
    public function abilities(Endpoint $endpoint): array
    {
        return match ($endpoint) {
            Endpoint::Details, Endpoint::Search => ['viewAny', 'users:read'],
            Endpoint::Mutate => ['viewAny', 'users:write'],
            default => parent::abilities($endpoint),
        };
    }

    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('name')
                ->searchable()
                ->rules('max:255')
                ->creationRules('required')
                ->examples('Jane Doe'),
            Text::make('email')
                ->searchable()
                ->rules('email', 'max:255')
                ->creationRules('required', Rule::unique('users', 'email'))
                ->updateRules(
                    'filled',
                    Contextual::rule(
                        fn (MutationContext $mutation) => Rule::unique('users', 'email')->ignore($mutation->key)
                    ),
                )
                ->examples('jane@example.com'),
            Text::make('password')
                ->writeOnly()
                ->rules('min:12')
                ->creationRules('required'),
            DateTime::make('created_at')->readonly(),
            DateTime::make('updated_at')->readonly(),
        ];
    }

    public function relations(): array
    {
        return [
            HasMany::make('assignedItems', ItemResource::class)->onlyOperations(),
            HasMany::make('reportedItems', ItemResource::class)->onlyOperations(),
            HasMany::make('comments', CommentResource::class)->onlyOperations(),
        ];
    }
}
