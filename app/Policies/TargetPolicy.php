<?php

namespace App\Policies;

use App\Models\Target;
use App\Models\User;

class TargetPolicy
{
    /**
     * Staff see every target, clients only their own (list with Target::visibleTo()).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('targets.view-any') || $user->can('targets.create');
    }

    public function view(User $user, Target $target): bool
    {
        return $user->can('targets.view-any') || $target->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('targets.create');
    }

    /**
     * Covers the target's scope rules and test accounts too.
     * A verified target is locked for its owner: changing base_url/scope would bypass verification.
     */
    public function update(User $user, Target $target): bool
    {
        if ($user->can('targets.verify')) {
            return true;
        }

        return $user->can('targets.create')
            && $target->user_id === $user->id
            && ! $target->isVerified();
    }

    public function delete(User $user, Target $target): bool
    {
        return $this->update($user, $target);
    }

    public function verify(User $user, Target $target): bool
    {
        return $user->can('targets.verify');
    }
}
