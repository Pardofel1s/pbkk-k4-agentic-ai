<?php

namespace App\Services\Llm;

/**
 * A single tool call the LLM asked us to run, with its arguments already
 * decoded from the JSON string the provider sends.
 */
readonly class ToolCall
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {
    }

    /**
     * Build from one element of the OpenAI `tool_calls` array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $function = $data['function'] ?? [];
        $arguments = $function['arguments'] ?? '{}';

        // The provider sends arguments as a JSON-encoded string.
        if (is_string($arguments)) {
            $arguments = json_decode($arguments, true) ?? [];
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($function['name'] ?? ''),
            arguments: is_array($arguments) ? $arguments : [],
        );
    }
}
