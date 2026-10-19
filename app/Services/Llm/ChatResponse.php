<?php

namespace App\Services\Llm;

/**
 * Normalised result of one `/chat/completions` call: either assistant text,
 * one or more tool calls, or both.
 */
readonly class ChatResponse
{
    /**
     * @param  list<ToolCall>  $toolCalls
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $content,
        public array $toolCalls,
        public array $raw,
    ) {
    }

    /**
     * Build from a decoded OpenAI chat completion payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $message = $data['choices'][0]['message'] ?? [];

        $toolCalls = array_map(
            fn (array $call): ToolCall => ToolCall::fromArray($call),
            $message['tool_calls'] ?? [],
        );

        return new self(
            content: $message['content'] ?? null,
            toolCalls: array_values($toolCalls),
            raw: $data,
        );
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
