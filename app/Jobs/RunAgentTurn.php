<?php

namespace App\Jobs;

use App\Models\ChatSession;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use App\Services\Agent\AgentLoop;
use App\Services\Agent\StartAgentTurn;
use App\Tools\ToolContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs one agent turn in the background, so a slow LLM never blocks the UI.
 *
 * Not retried ($tries = 1): LLM and tool calls are not idempotent, and a retry
 * would run every tool again. Keep the queue's retry_after above $timeout
 * (DB_QUEUE_RETRY_AFTER in .env), or a second worker picks up a running job.
 */
class RunAgentTurn implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $chatSessionId,
        public int $userId,
        public int $targetId,
        public ?int $scanId = null,
    ) {
    }

    /**
     * Permissions and the target's status are checked again here, because
     * they may have changed since the turn was queued.
     */
    public function handle(AgentLoop $loop, StartAgentTurn $gate): void
    {
        $session = ChatSession::query()->find($this->chatSessionId);

        if ($session === null) {
            return;
        }

        $user = User::query()->find($this->userId);
        $target = Target::query()->find($this->targetId);
        $scan = $this->scanId === null ? null : Scan::query()->find($this->scanId);

        if ($user === null || $target === null || ! $gate->allows($user, $session, $target, $scan)) {
            $session->chatMessages()->create([
                'role' => 'assistant',
                'isi' => 'Permintaan tidak dijalankan: izin akses atau status target sudah berubah.',
            ]);

            return;
        }

        $loop->run($session, new ToolContext($user, $target, $scan));
    }

    /**
     * Leave a message in the chat so the UI does not wait forever.
     */
    public function failed(?Throwable $exception): void
    {
        ChatSession::query()->find($this->chatSessionId)?->chatMessages()->create([
            'role' => 'assistant',
            'isi' => 'Maaf, agent gagal menyelesaikan permintaan ini. Coba lagi beberapa saat lagi.',
        ]);
    }
}
