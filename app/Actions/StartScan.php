<?php

namespace App\Actions;

use App\Jobs\RunScan;
use App\Models\Scan;
use App\Models\ScanProfile;
use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StartScan
{
    /**
     * Queue a scan of a verified target with a scan profile; RunScan runs its tools.
     * A re-test ($retestOf) reuses the profile and test account of the scan it checks.
     */
    public function handle(User $analyst, Target $target, int $profileId, ?int $accountId = null, ?Scan $retestOf = null): Scan
    {
        Gate::forUser($analyst)->authorize('create', [Scan::class, $target]);

        if ($retestOf !== null && $retestOf->target_id !== $target->id) {
            throw ValidationException::withMessages(['scan' => 'Scan yang di-retest bukan milik target ini.']);
        }

        validator(
            ['profile' => $profileId, 'account' => $accountId],
            [
                'profile' => ['required', Rule::exists(ScanProfile::class, 'id')],
                'account' => ['nullable', Rule::exists('target_test_accounts', 'id')->where('target_id', $target->id)],
            ],
            ['account.exists' => 'Akun uji bukan milik target ini.'],
        )->validate();

        $scan = $target->scans()->create([
            'scan_profile_id' => $profileId,
            'target_test_account_id' => $accountId,
            'retest_of_id' => $retestOf?->id,
            'status' => Scan::QUEUED,
            'progress' => 0,
        ]);

        RunScan::dispatch($scan->id, $analyst->id);

        return $scan;
    }
}
