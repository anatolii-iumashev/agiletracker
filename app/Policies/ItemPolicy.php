<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Item $item): bool
    {
        return true;
    }

    public function create(User $user, mixed $draft = null): bool
    {
        return true;
    }

    public function update(User $user, Item $item): bool
    {
        return true;
    }

    public function delete(User $user, Item $item): bool
    {
        return true;
    }
}
