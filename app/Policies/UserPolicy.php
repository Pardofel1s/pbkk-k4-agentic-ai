<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    /**
     * Creating analysts (RegisterAnalyst). Admins are only ever seeded.
     */
    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    /**
     * Never yourself, so the seeded admin cannot lock everyone out.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can('users.manage') && ! $user->is($model);
    }
}
