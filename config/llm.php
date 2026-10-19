<?php

return [

    /*
    | OpenAI-compatible chat completions endpoint (Senopati AI ITS / Ollama).
    | Switching provider only needs a .env change.
    */
    'base_url' => env('LLM_BASE_URL', 'https://senopati.its.ac.id/llm-gateway/v1'),
    'model' => env('LLM_MODEL', 'qwen3:14b'),
    'api_key' => env('LLM_API_KEY'),
    'timeout' => (int) env('LLM_TIMEOUT', 120),

    /*
    | Maximum LLM calls in one agent turn (each may run several tools), so a
    | model that keeps calling tools cannot loop forever.
    */
    'max_steps' => (int) env('LLM_MAX_STEPS', 5),

    /*
    | Seconds one agent turn may take in total. Keep it well below the queued
    | job's timeout (RunAgentTurn::$timeout, 600) so the turn ends on its own
    | instead of being killed mid-way.
    */
    'turn_timeout' => (int) env('LLM_TURN_TIMEOUT', 420),

    /*
    | Tool classes (implementing App\Tools\Tool) exposed to the agent.
    */
    'tools' => [
        App\Tools\CheckSecurityHeadersTool::class,
    ],

    /*
    | Hosts that tools may send requests to (comma-separated in .env). DAST may
    | only target systems we own or are allowed to test, so others are refused.
    */
    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LLM_TOOL_ALLOWED_HOSTS', 'localhost,127.0.0.1')),
    ))),

];
