<?php

namespace App\Actions;

use App\Models\Report;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GenerateReport
{
    /**
     * Save a new version of the scan's report as an HTML snapshot (printable to PDF).
     * The file keeps the findings as they were, so later reviews do not change old versions.
     */
    public function handle(User $analyst, Scan $scan): Report
    {
        Gate::forUser($analyst)->authorize('update', $scan);

        if ($scan->status !== Scan::COMPLETED) {
            throw ValidationException::withMessages(['report' => 'Laporan hanya bisa dibuat dari scan yang sudah selesai.']);
        }

        $versi = (int) $scan->reports()->max('versi') + 1;
        $path = "reports/scan-{$scan->id}-v{$versi}.html";

        $report = $scan->reports()->make([
            'versi' => $versi,
            'format' => 'html',
            'file_path' => $path,
            'generated_at' => now(),
        ]);

        Storage::disk('local')->put($path, view('jejaksa.report-export', [
            'report' => $report,
            'scan' => $scan->load(['target', 'scanProfile']),
            'findings' => $scan->findings()->with('vulnerabilityType')->get(),
        ])->render());

        $report->save();

        return $report;
    }
}
