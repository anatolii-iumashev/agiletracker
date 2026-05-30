<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Favoritable
{
    /**
     * Get all favorites that point to this model (i.e., users who bookmarked this model).
     */
    public function bookmarks(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    /**
     * Check if the given user has bookmarked this model.
     */
    public function isBookmarkedBy(User $user): bool
    {
        return $this->bookmarks()->where('user_id', $user->id)->exists();
    }
}
