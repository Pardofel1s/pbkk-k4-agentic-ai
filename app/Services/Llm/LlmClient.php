<?php

namespace App\Services\Llm;

use App\Tools\ToolRegistry;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use UnexpectedValueException;

/**
 * OpenAI-compatible chat client with Ollama as the primary provider and
 * Senopati as the fallback for connection failures and server errors.
 */
class LlmClient
{
    public function __construct(private readonly ToolRegistry $tools)
    {
    }

    /**
     * Send a chat completion request.
     *
     * Registered tools are exposed to the model unless the caller opts out
     * with `['tools' => false]` (e.g. a plain summary call).
     *
     * @param  list<array<string, mixed>>  $messages  OpenAI chat messages.
     * @param  array{tools?: bool, tool_choice?: string, model?: string}  $options
     */
    public function chat(array $messages, array $options = []): ChatResponse
    {
        return $this->complete($messages, $options);
    }

    /**
     * Deliver each text fragment immediately, then return the full response.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  callable(string): void  $onChunk
     * @param  array{model?: string}  $options
     */
    public function stream(array $messages, callable $onChunk, array $options = []): ChatResponse
    {
        return $this->complete($messages, $options, $onChunk);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array{tools?: bool, tool_choice?: string, model?: string}  $options
     * @param  (callable(string): void)|null  $onChunk
     */
    private function complete(array $messages, array $options, ?callable $onChunk = null): ChatResponse
    {
        $systemPrompt = trim((string) config('llm.system_prompt', ''));

        if ($systemPrompt !== '' && ! in_array('system', array_column($messages, 'role'), true)) {
            array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
        }

        $hasOutput = false;
        $emit = null;

        if ($onChunk !== null) {
            $emit = function (string $text) use ($onChunk, &$hasOutput): void {
                $hasOutput = true;
                $onChunk($text);
            };
        }

        if ((bool) config('llm.ollama.enabled', false)) {
            try {
                return $this->sendChat($messages, $options, config('llm.ollama'), $emit);
            } catch (ConnectionException | RequestException $exception) {
                if ($hasOutput || ($exception instanceof RequestException && ! $exception->response->serverError())) {
                    throw $exception;
                }

                Log::warning('Ollama unavailable. Falling back to Senopati AI ITS.', [
                    'http_status' => $exception instanceof RequestException
                        ? $exception->response->status()
                        : null,
                ]);
            }
        }

        return $this->sendChat($messages, $options, [
            'base_url' => config('llm.base_url'),
            'model' => config('llm.model'),
            'api_key' => config('llm.api_key'),
            'connect_timeout' => 5,
            'timeout' => (int) config('llm.timeout', 120),
        ], $emit);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array{tools?: bool, tool_choice?: string, model?: string}  $options
     * @param  array{base_url: string, model: string, api_key?: ?string, connect_timeout: int, timeout: int}  $provider
     * @param  (callable(string): void)|null  $onChunk
     */
    private function sendChat(
        array $messages,
        array $options,
        array $provider,
        ?callable $onChunk = null,
    ): ChatResponse {
        $payload = [
            'model' => $options['model'] ?? $provider['model'],
            'messages' => $messages,
            'stream' => $onChunk !== null,
        ];

        if ($onChunk !== null) {
            $payload['stream_options'] = ['include_usage' => true];
        } elseif (($options['tools'] ?? true) && $schemas = $this->tools->schemas()) {
            $payload['tools'] = $schemas;
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        $request = $this->request($provider);

        if ($onChunk !== null) {
            $request->accept('text/event-stream')->withOptions([
                'stream' => true,
                'read_timeout' => (float) $provider['timeout'],
            ]);
        }

        $deadline = microtime(true) + $provider['timeout'];
        $response = $request
            ->post('/chat/completions', $payload)
            ->throw();

        if ($onChunk !== null) {
            return $this->readStream($response, $onChunk, $payload['model'], $deadline);
        }

        $data = $response->json();

        if (! is_array($data) || ! is_array($data['choices'][0]['message'] ?? null)) {
            Log::warning('LLM provider returned an invalid chat completion.');

            throw new RequestException($response);
        }

        $data['model'] ??= $payload['model'];
        $reply = ChatResponse::fromArray($data);

        if (trim($reply->content ?? '') === '' && ! $reply->hasToolCalls()) {
            Log::warning('LLM provider returned an empty chat completion.');

            throw new RequestException($response);
        }

        return $reply;
    }

    /**
     * Read OpenAI-compatible SSE frames, including the final usage frame.
     *
     * @param  callable(string): void  $onChunk
     */
    private function readStream(
        Response $response,
        callable $onChunk,
        string $model,
        float $deadline,
    ): ChatResponse {
        $body = $response->toPsrResponse()->getBody();
        $eventData = [];
        $content = '';
        $usage = [];
        $finished = false;

        try {
            while (! $body->eof() || $eventData !== []) {
                if (microtime(true) >= $deadline) {
                    throw new ConnectionException('LLM response streaming timed out.');
                }

                try {
                    $line = $body->eof() ? '' : Utils::readLine($body);
                } catch (RuntimeException $exception) {
                    throw new ConnectionException('LLM response stream was interrupted.', 0, $exception);
                }

                if ($line === '' && ! $body->eof()) {
                    throw new ConnectionException('LLM response stream stopped making progress.');
                }

                $line = rtrim($line, "\r\n");

                if ($line !== '') {
                    if (str_starts_with($line, 'data:')) {
                        $eventData[] = ltrim(substr($line, 5), ' ');
                    }

                    continue;
                }

                if ($eventData === []) {
                    continue;
                }

                $event = trim(implode("\n", $eventData));
                $eventData = [];

                if ($event === '[DONE]') {
                    $finished = true;

                    break;
                }

                $chunk = json_decode($event, true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($chunk) || ! is_array($chunk['choices'] ?? null) || isset($chunk['error'])) {
                    throw new UnexpectedValueException('Invalid streamed chat completion.');
                }

                $choice = $chunk['choices'][0] ?? [];

                if (! is_array($choice) || ! is_array($choice['delta'] ?? [])) {
                    throw new UnexpectedValueException('Invalid streamed chat choice.');
                }

                $text = $choice['delta']['content'] ?? '';

                if (! is_string($text)) {
                    throw new UnexpectedValueException('Invalid streamed text fragment.');
                }

                if (isset($chunk['model'])) {
                    if (! is_string($chunk['model'])) {
                        throw new UnexpectedValueException('Invalid streamed model name.');
                    }

                    $model = $chunk['model'];
                }

                if (is_array($chunk['usage'] ?? null)) {
                    $usage = array_replace($usage, $chunk['usage']);
                }

                // shortcut: a leaked "...</think>" prefix is only stripped from the final response, not from live chunks; buffer the stream if this shows up in demos.
                if ($text !== '') {
                    $content .= $text;
                    $onChunk($text);
                }

                if (isset($choice['finish_reason'])) {
                    $finished = true;
                }
            }
        } catch (JsonException | UnexpectedValueException $exception) {
            Log::warning('LLM provider returned an invalid response stream.');
            $response->close();

            throw new RequestException($response);
        } finally {
            $response->close();
        }

        if (! $finished) {
            throw new ConnectionException('LLM response stream ended before completion.');
        }

        if (trim($content) === '') {
            throw new RequestException($response);
        }

        return ChatResponse::fromArray([
            'model' => $model,
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]],
            'usage' => $usage,
        ]);
    }

    /**
     * @param  array{base_url: string, model: string, api_key?: ?string, connect_timeout: int, timeout: int}  $provider
     */
    private function request(array $provider): PendingRequest
    {
        $request = Http::baseUrl(rtrim($provider['base_url'], '/'))
            ->connectTimeout($provider['connect_timeout'])
            ->timeout($provider['timeout'])
            ->acceptJson()
            ->asJson();

        if ($key = $provider['api_key'] ?? null) {
            $request->withToken($key);
        }

        return $request;
    }
}
