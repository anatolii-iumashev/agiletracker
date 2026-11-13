<?php

declare(strict_types=1);

namespace App\Rest\Resources;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Http\Resource as BaseResource;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

abstract class Resource extends BaseResource
{
    public int $defaultLimit = 15;

    public int $includeLimit = 15;

    /**
     * @return list<int>
     */
    public function limits(): array
    {
        return [15, 25, 50, 100];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(): array
    {
        return [static::newModel()->getKeyName() => 'desc'];
    }

    public function text(): ?TextSearch
    {
        return Contains::make();
    }

    /**
     * Trashed rows are never reachable through the API: restore and force
     * delete are out of scope for API v1.
     */
    public function searching(SearchContext $search): void
    {
        if ($search->trashed !== null) {
            throw ValidationException::withMessages([
                'search.trashed' => __('Trashed rows are not available through the API.'),
            ]);
        }
    }
}
