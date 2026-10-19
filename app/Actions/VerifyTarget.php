<?php

namespace App\Actions;

use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VerifyTarget
{
    /**
     * Admin sets verified / rejected, or reopens a target as pending so its owner can edit it again.
     */
    public function handle(User $admin, Target $target, string $status): Target
    {
        Gate::forUser($admin)->authorize('verify', $target);

        Validator::make(['status' => $status], [
            'status' => ['required', Rule::in([Target::PENDING, Target::VERIFIED, Target::REJECTED])],
        ])->validate();

        $target->forceFill(['status_verifikasi' => $status])->save();

        return $target;
    }
}
