<?php

namespace App\Services\Agent;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Llm\LlmClient;
use App\Tools\ToolContext;

/**
 * The autonomous part of the agent: ask the LLM, run the tools it asks for,
 * send the results back, and repeat until it answers in plain text or the
 * step limit (config('llm.max_steps')) is reached.
 *
 * Every assistant step is saved as a ChatMessage; the tool calls of a step
 * hang off that message in `tool_calls`. Earlier turns are replayed to the
 * LLM as text only; tool messages exist just for the turn being run.
 */
class AgentLoop
{
    private const HISTORY_LIMIT = 20;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly ToolExecutor $executor,
    ) {
    }

    /**
     * Run one turn for the latest user message in the session.
     *
     * An assistant message with tool calls is sent back to the LLM exactly as
     * received, so the `tool_call_id` of each tool result matches its call.
     */
    public function run(ChatSession $session, ToolContext $context): ChatMessage
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($context)],
            ...$this->history($session),
        ];

        $maxSteps = max(1, (int) config('llm.max_steps', 5));

        for ($step = 0; $step < $maxSteps; $step++) {
            $reply = $this->llm->chat($messages);

            if (! $reply->hasToolCalls()) {
                return $this->say($session, $reply->content ?: 'Model tidak memberi jawaban.');
            }

            $parent = $this->say($session, $reply->content ?? '');
            $messages[] = $reply->raw['choices'][0]['message'];

            foreach ($reply->toolCalls as $call) {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call->id,
                    'content' => $this->executor->execute($call, $parent, $context),
                ];
            }
        }

        return $this->say($session, "Batas {$maxSteps} langkah tercapai sebelum agent selesai. Coba persempit permintaannya.");
    }

    private function say(ChatSession $session, string $text): ChatMessage
    {
        return $session->chatMessages()->create(['role' => 'assistant', 'isi' => $text]);
    }

    /**
     * The last user/assistant texts of the session, oldest first.
     *
     * @return list<array{role: string, content: string}>
     */
    private function history(ChatSession $session): array
    {
        return $session->chatMessages()
            ->whereIn('role', ['user', 'assistant'])
            ->where('isi', '!=', '')
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message): array => ['role' => $message->role, 'content' => $message->isi])
            ->values()
            ->all();
    }

    private function systemPrompt(ToolContext $context): string
    {
        $target = $context->target;

        return implode("\n", [
            'You are a DAST (Dynamic Application Security Testing) assistant for a security vendor.',
            "You are testing the target \"{$target->nama}\" at {$target->base_url} ({$target->lingkungan}).",
            'Only test this target. Never send requests to other hosts; tools refuse URLs outside its scope.',
            'Use the available tools to gather evidence before drawing conclusions, and do not invent findings.',
            'Answer in Indonesian, briefly, and name the evidence each conclusion is based on.',
        ]);
    }
}
