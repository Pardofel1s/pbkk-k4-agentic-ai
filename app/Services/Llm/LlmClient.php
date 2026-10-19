<?php

namespace App\Services\Llm;

use App\Tools\ToolRegistry;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin client over the OpenAI-compatible `/chat/completions` endpoint
 * configured in config/llm.php (Senopati AI ITS by default, Ollama as a
 * fallback). It attaches the registered tool schemas so the model can ask us
 * to run tools, and returns a normalised {@see ChatResponse}.
 */
class LlmClient
{
    public function __construct(private readonly ToolRegistry $tools)
    {
    }

    /**
     * Send a chat completion request.
     *
     * @param  list<array<string, mixed>>  $messages  OpenAI chat messages.
     * @param  array{tools?: bool, tool_choice?: string, model?: string}  $options
     */
    public function chat(array $messages, array $options = []): ChatResponse
    {
        $payload = [
            'model' => $options['model'] ?? config('llm.model'),
            'messages' => $messages,
        ];

        // Expose tools unless the caller opts out (e.g. a plain summary call).
        if (($options['tools'] ?? true) && $schemas = $this->tools->schemas()) {
            $payload['tools'] = $schemas;
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        $response = $this->request()
            ->post('/chat/completions', $payload)
            ->throw()
            ->json();

        return ChatResponse::fromArray($response);
    }

    /**
     * Base HTTP request: endpoint, timeout, and auth from config/llm.php.
     */
    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('llm.base_url'), '/'))
            ->connectTimeout(5)
            ->timeout((int) config('llm.timeout', 120))
            ->acceptJson()
            ->asJson();

        // Senopati needs a bearer token; the local Ollama fallback has none.
        if ($key = config('llm.api_key')) {
            $request->withToken($key);
        }

        return $request;
    }
}
