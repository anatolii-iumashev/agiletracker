<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Comment $comment): bool
    {
        return true;
    }

    /**
     * The field rule guarantees that `item_id` names an existing, non-trashed
     * item, so the policy only decides that the user may comment at all.
     */
    public function create(User $user, mixed $draft = null): bool
    {
        return true;
    }

    public function update(User $user, Comment $comment): bool
    {
        return $this->isAuthorOrAdmin($user, $comment);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->isAuthorOrAdmin($user, $comment);
    }

    private function isAuthorOrAdmin(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getKey() || $user->hasRole('admin');
    }
}
