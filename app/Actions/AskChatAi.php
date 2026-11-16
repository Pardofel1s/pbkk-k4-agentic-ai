<?php

namespace App\Actions;

use App\Models\ChatLog;
use App\Models\User;
use App\Services\Llm\LlmClient;

class AskChatAi
{
    public function __construct(private readonly LlmClient $llm)
    {
    }

    /**
     * Send the prompt to the LLM and record the exchange for the user.
     *
     * Nothing is saved when the LLM call fails: the client exceptions
     * (ConnectionException / RequestException) bubble up to the controller.
     * The model name and token usage come from the provider's own response
     * (OpenAI-compatible `model` and `usage` fields), so after a provider
     * switch the log still shows what actually answered.
     */
    public function handle(User $user, string $prompt): ChatLog
    {
        $response = $this->llm->chat(
            [['role' => 'user', 'content' => $prompt]],
            ['tools' => false],
        );

        $usage = $response->raw['usage'] ?? [];
        $promptTokens = $usage['prompt_tokens'] ?? null;
        $completionTokens = $usage['completion_tokens'] ?? null;

        return $user->chatLogs()->create([
            'prompt' => $prompt,
            'ai_response' => $response->content ?? '',
            'model_used' => $response->raw['model'] ?? config('llm.model'),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $usage['total_tokens'] ?? (($promptTokens ?? 0) + ($completionTokens ?? 0)),
        ]);
    }
}
