<?php

declare(strict_types=1);

namespace App\Rest\Resources;

use App\Models\Label;
use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Relations\BelongsToMany;

#[WithoutPerimeter]
class LabelResource extends Resource
{
    public static string $model = Label::class;

    /**
     * @return list<string>
     */
    public function abilities(Endpoint $endpoint): array
    {
        return match ($endpoint) {
            Endpoint::Details, Endpoint::Search => ['viewAny', 'labels:read'],
            Endpoint::Mutate, Endpoint::Destroy => ['viewAny', 'labels:write'],
            default => parent::abilities($endpoint),
        };
    }

    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('name')
                ->searchable()
                ->rules('max:50')
                ->creationRules('required')
                ->updateRules('filled')
                ->examples('billing'),
            Text::make('color')
                ->rules('regex:/^#[0-9A-Fa-f]{6}$/')
                ->updateRules('filled')
                ->examples('#6366f1'),
            DateTime::make('created_at')->readonly(),
            DateTime::make('updated_at')->readonly(),
        ];
    }

    public function relations(): array
    {
        return [
            BelongsToMany::make('items', ItemResource::class)->onlyOperations(),
        ];
    }
}
