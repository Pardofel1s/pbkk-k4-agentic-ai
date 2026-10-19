<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\User;

class FindingPolicy
{
    public function view(User $user, Finding $finding): bool
    {
        return $user->can('targets.view-any') || $user->ownsScan($finding->scan_id);
    }

    public function update(User $user, Finding $finding): bool
    {
        return $user->can('findings.manage');
    }
}
