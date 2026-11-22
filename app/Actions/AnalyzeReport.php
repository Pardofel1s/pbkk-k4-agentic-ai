<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Finding;
use App\Models\Report;
use App\Models\User;
use App\Services\Llm\LlmClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnalyzeReport
{
    public const PREFIX = 'Analisis AI';

    public function __construct(private readonly LlmClient $llm)
    {
    }

    /**
     * Ask the LLM to prioritise the scan's findings and save its answer as a
     * comment on the report, so the client sees it in the same thread.
     */
    public function handle(User $user, Report $report): Comment
    {
        Gate::forUser($user)->authorize('create', [Comment::class, $report]);

        $scan = $report->scan()->with('target')->first();
        $findings = $scan->findings()->with('vulnerabilityType')->get();

        $list = $findings->isEmpty()
            ? '(tidak ada temuan)'
            : $findings->map(fn (Finding $f): string => "- [{$f->severity}] {$f->vulnerabilityType->nama} ({$f->vulnerabilityType->kode_cwe})"
                ." di {$f->url_terdampak}".($f->parameter ? " · {$f->parameter}" : '')." · status {$f->status}")->implode("\n");

        try {
            $reply = $this->llm->chat([[
                'role' => 'user',
                'content' => "Hasil scan DAST untuk target {$scan->target->nama} ({$scan->target->base_url}):\n{$list}\n\n"
                    .'Buat ringkasan eksekutif singkat untuk klien: urutkan prioritas perbaikan, jelaskan dampaknya, '
                    .'dan jangan menyebut temuan yang tidak ada di daftar.',
            ]], ['tools' => false]);
        } catch (ConnectionException|RequestException) {
            throw ValidationException::withMessages(['report' => 'Layanan AI sedang tidak tersedia. Coba lagi nanti.']);
        }

        $comment = $report->comments()->make([
            'isi' => self::PREFIX.' ('.($reply->raw['model'] ?? config('llm.model'))."):\n\n".trim((string) $reply->content),
        ]);
        $comment->user()->associate($user);
        $comment->save();

        return $comment;
    }
}
