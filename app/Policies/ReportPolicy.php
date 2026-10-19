<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return $user->can('reports.view')
            && ($user->can('targets.view-any') || $user->ownsScan($report->scan_id));
    }
}
