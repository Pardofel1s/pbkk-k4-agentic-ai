<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function view(User $user, Certificate $certificate): bool
    {
        return $user->can('reports.view')
            && ($user->can('targets.view-any') || $user->ownsScan($certificate->scan_id));
    }

    public function create(User $user): bool
    {
        return $user->can('certificates.issue');
    }
}
