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
    | Tool classes (implementing App\Tools\Tool) exposed to the agent.
    */
    'tools' => [
        //
    ],

];
