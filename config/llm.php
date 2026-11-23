<?php

return [

    /*
    | OpenAI-compatible Senopati endpoint used by the chat client.
    */
    'base_url' => env('LLM_BASE_URL', 'https://senopati.its.ac.id/llm-gateway/v1'),
    'model' => env('LLM_MODEL', 'qwen3:14b'),
    'api_key' => env('LLM_API_KEY'),
    'timeout' => (int) env('LLM_TIMEOUT', 120),

    /*
    | Local Ollama connection for the primary provider.
    */
    'ollama' => [
        'enabled' => (bool) env('OLLAMA_ENABLED', true),
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434/v1'),
        'model' => env('OLLAMA_MODEL', 'qwen3:14b'),
        'connect_timeout' => (int) env('OLLAMA_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 60),
    ],

    /*
    | Shared DAST instructions for chat requests.
    */
    'system_prompt' => implode("\n", [
        'Anda adalah Jejaksa, asisten Dynamic Application Security Testing (DAST).',
        'Bantu pengguna menganalisis potensi kerentanan aplikasi web dan langkah mitigasinya.',
        'Bedakan potensi risiko dengan temuan yang sudah didukung bukti pengujian.',
        'Nyatakan pemindaian telah dilakukan hanya jika tersedia hasil tool yang mendukungnya.',
        'Jawab dalam bahasa Indonesia dengan jelas dan terstruktur.',
    ]),

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
        App\Tools\CheckCookieFlagsTool::class,
        App\Tools\CheckCsrfTokenTool::class,
        App\Tools\ScanOpenPortsTool::class,
        App\Tools\TestReflectedXssTool::class,
        App\Tools\CheckSensitiveFilesTool::class,
        App\Tools\CheckCorsTool::class,
        App\Tools\CheckInfoDisclosureTool::class,
        App\Tools\CheckTlsTool::class,
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
