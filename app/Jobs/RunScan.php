<?php

namespace App\Jobs;

use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use App\Services\Agent\ToolExecutor;
use App\Services\Llm\ToolCall;
use App\Services\Scan\FindingRules;
use App\Tools\ToolContext;
use App\Tools\ToolRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs the tools of a scan's profile against its target, one after another,
 * and turns their output into findings (FindingRules). Every tool call is
 * logged in `tool_calls` with the scan, so the scan page shows what ran.
 *
 * Not retried: tools send real requests to the target.
 */
class RunScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Up to 5 tools of ToolExecutor::MAX_SECONDS each. */
    public int $timeout = 660;

    public function __construct(public int $scanId, public int $userId)
    {
    }

    public function handle(ToolExecutor $executor, ToolRegistry $tools, FindingRules $rules): void
    {
        $scan = Scan::query()->with(['target', 'scanProfile'])->find($this->scanId);

        if ($scan === null || $scan->status !== Scan::QUEUED) {
            return;
        }

        $user = User::query()->find($this->userId);

        // Checked again: the target may have been rejected or the analyst removed since it was queued.
        if ($user === null || $user->cannot('create', [Scan::class, $scan->target])) {
            $scan->update(['status' => Scan::FAILED, 'finished_at' => now()]);

            return;
        }

        $scan->update(['status' => Scan::RUNNING, 'started_at' => now()]);

        try {
            $names = array_values(array_intersect($scan->scanProfile->pengaturan['tools'] ?? [], array_keys($tools->all())));
            $context = new ToolContext($user, $scan->target, $scan);
            $url = $scan->target->base_url;

            foreach ($names as $i => $name) {
                if ($scan->fresh()->status === Scan::CANCELLED) {
                    $scan->update(['finished_at' => now()]);

                    return;
                }

                $output = $executor->execute(new ToolCall("scan-{$scan->id}-{$i}", $name, ['url' => $url]), null, $context);

                foreach ($rules->extract($name, $output, $url) as $finding) {
                    $scan->findings()->create([
                        'vulnerability_type_id' => $rules->type($finding['type'])->id,
                        'severity' => $rules->severity($finding['type']),
                        'url_terdampak' => mb_substr($finding['url'], 0, 500),
                        'parameter' => $finding['parameter'] === null ? null : mb_substr($finding['parameter'], 0, 150),
                        'proof' => $finding['proof'],
                        'status' => Finding::OPEN,
                    ]);
                }

                $scan->update(['progress' => intdiv(($i + 1) * 100, max(1, count($names)))]);
            }

            $scan->update(['status' => Scan::COMPLETED, 'progress' => 100, 'finished_at' => now()]);
            $this->closeFixedFindings($scan);
        } catch (Throwable $e) {
            report($e);
            $scan->update(['status' => Scan::FAILED, 'finished_at' => now()]);
        }
    }

    /**
     * A re-test closes each finding of the original scan that it no longer finds
     * (same vulnerability type and parameter).
     */
    private function closeFixedFindings(Scan $retest): void
    {
        if ($retest->retest_of_id === null) {
            return;
        }

        $stillThere = $retest->findings()->get()
            ->map(fn (Finding $f): string => $f->vulnerability_type_id.'|'.$f->parameter)
            ->all();

        Finding::query()
            ->where('scan_id', $retest->retest_of_id)
            ->whereIn('status', [Finding::OPEN, Finding::CONFIRMED])
            ->get()
            ->reject(fn (Finding $f): bool => in_array($f->vulnerability_type_id.'|'.$f->parameter, $stillThere, true))
            ->each(fn (Finding $f) => $f->update(['status' => Finding::FIXED]));
    }
}
