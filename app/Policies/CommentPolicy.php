<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CommentPolicy
{
    /**
     * Gate::authorize('create', [Comment::class, $findingOrReport]).
     */
    public function create(User $user, Model $commentable): bool
    {
        return in_array($commentable::class, Comment::COMMENTABLES, true)
            && $user->can('comments.create')
            && $user->can('view', $commentable);
    }

    /**
     * No edit on purpose: the thread is an audit trail. Only the author may delete.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }
}
