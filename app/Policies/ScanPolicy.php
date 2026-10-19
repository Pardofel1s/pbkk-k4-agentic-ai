<?php

namespace App\Policies;

use App\Models\Scan;
use App\Models\Target;
use App\Models\User;

class ScanPolicy
{
    public function view(User $user, Scan $scan): bool
    {
        return $user->can('targets.view-any') || $user->ownsTarget($scan->target_id);
    }

    /**
     * Scans only run against targets an admin has verified (ethical scope).
     */
    public function create(User $user, Target $target): bool
    {
        return $user->can('scans.run') && $target->isVerified();
    }

    public function update(User $user, Scan $scan): bool
    {
        return $user->can('scans.run');
    }
}
