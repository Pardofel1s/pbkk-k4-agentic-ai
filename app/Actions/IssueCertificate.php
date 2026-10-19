<?php

namespace App\Actions;

use App\Models\Certificate;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IssueCertificate
{
    /**
     * "Sertifikat Lulus Uji Penetrasi" (docs/rancangan.md): only for a finished
     * re-test that left no open findings, and at most one per scan.
     */
    public function handle(User $admin, Scan $scan): Certificate
    {
        Gate::forUser($admin)->authorize('create', Certificate::class);

        $error = match (true) {
            $scan->retest_of_id === null => 'Sertifikat hanya untuk scan re-test.',
            $scan->status !== Scan::COMPLETED => 'Scan re-test belum selesai.',
            $scan->findings()->where('status', Finding::OPEN)->exists() => 'Masih ada finding berstatus open.',
            $scan->certificate()->exists() => 'Scan ini sudah punya sertifikat.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['scan' => $error]);
        }

        return $scan->certificate()->create([
            // Deterministic and unique, because scan_id is unique on certificates.
            'nomor' => sprintf('CERT-%d-%05d', now()->year, $scan->id),
            'issued_at' => now(),
            'due_date' => now()->addYear(),
        ]);
    }
}
