<?php

namespace App\Services\Agent;

use App\Jobs\RunAgentTurn;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Entry point for the chat UI: save the user's prompt and queue the agent.
 *
 * Only an analyst who owns the session (`chat.use`) and may scan the target
 * (`scans.run` + target verified, ScanPolicy::create) gets here; see
 * docs/rbac.md. The queued job checks the same rules again before it runs.
 */
class StartAgentTurn
{
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

        $message = $session->chatMessages()->create(['role' => 'user', 'isi' => $prompt]);

        RunAgentTurn::dispatch($session->id, $user->id, $target->id, $scan?->id);

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
}
