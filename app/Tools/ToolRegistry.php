<?php

namespace App\Tools;

use InvalidArgumentException;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * @param  iterable<Tool>  $tools
     */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Value for the "tools" field of a chat completions request.
     *
     * @return list<array<string, mixed>>
     */
    public function schemas(): array
    {
        return array_values(array_map(fn (Tool $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
        ], $this->tools));
    }

    public function get(string $name): Tool
    {
        // The name is chosen by the LLM, so only whitelisted tools may run.
        return $this->tools[$name] ?? throw new InvalidArgumentException("Unknown tool [{$name}].");
    }
}
