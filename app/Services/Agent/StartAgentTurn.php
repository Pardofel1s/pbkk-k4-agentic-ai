<?php

namespace App\Services\Agent;

use App\Jobs\RunAgentTurn;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Entry point for the chat UI: save the user's prompt and queue the agent.
 *
 * Only an analyst who owns the session (`chat.use`) and may scan the target
 * (`scans.run` + target verified, ScanPolicy::create) gets here; see
 * docs/rbac.md. The queued job checks the same rules again before it runs.
 *
 * A session runs one turn at a time: a cache lock is taken here and released
 * by RunAgentTurn when the turn ends, so a second prompt cannot make the tools
 * run twice against the target.
 */
class StartAgentTurn
{
    /**
     * Longer than RunAgentTurn::$timeout, so the lock outlives a running turn,
     * but it still expires if no worker ever picks the job up.
     */
    public const LOCK_SECONDS = 660;

    public function handle(User $user, ChatSession $session, Target $target, string $prompt, ?Scan $scan = null): ChatMessage
    {
        Gate::forUser($user)->authorize('update', $session);
        Gate::forUser($user)->authorize('create', [Scan::class, $target]);

        if ($scan !== null && $scan->target_id !== $target->id) {
            throw new AuthorizationException('This scan does not belong to the target.');
        }

        Validator::make(['prompt' => $prompt], [
            'prompt' => ['required', 'string', 'max:4000'],
        ])->validate();

        $lock = Cache::lock(self::lockName($session->id), self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'prompt' => 'Agent masih memproses pesan sebelumnya. Tunggu jawabannya dulu.',
            ]);
        }

        try {
            $message = $session->chatMessages()->create(['role' => 'user', 'isi' => $prompt]);

            RunAgentTurn::dispatch($session->id, $user->id, $target->id, $scan?->id, $lock->owner());
        } catch (Throwable $e) {
            $lock->release();

            throw $e;
        }

        return $message;
    }

    /**
     * The same rules as handle(), as a yes/no for the queued job.
     */
    public function allows(User $user, ChatSession $session, Target $target, ?Scan $scan = null): bool
    {
        return Gate::forUser($user)->allows('update', $session)
            && Gate::forUser($user)->allows('create', [Scan::class, $target])
            && ($scan === null || $scan->target_id === $target->id);
    }

    /**
     * Whether a turn is still running (or queued) for this session, e.g. to
     * disable the send button in the chat UI.
     */
    public static function isBusy(ChatSession $session): bool
    {
        $lock = Cache::lock(self::lockName($session->id), self::LOCK_SECONDS);

        if ($lock->get()) {
            $lock->release();

            return false;
        }

        return true;
    }

    public static function lockName(int $chatSessionId): string
    {
        return "agent-turn:{$chatSessionId}";
    }
}
