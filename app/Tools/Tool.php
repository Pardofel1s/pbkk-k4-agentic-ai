<?php

namespace App\Tools;

interface Tool
{
    /**
     * Function name the LLM calls, e.g. "check_security_headers".
     */
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema of the arguments (the "parameters" object of an OpenAI tool).
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Arguments come straight from the LLM: validate them before use.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): string;
}
