<?php

use App\Services\Llm\LlmClient;
use App\Tools\ToolRegistry;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set([
        'llm.base_url' => 'https://senopati.test/v1',
        'llm.model' => 'remote-model',
        'llm.api_key' => 'test-senopati-key',
        'llm.timeout' => 30,
        'llm.system_prompt' => 'Anda adalah asisten DAST.',
        'llm.ollama' => [
            'enabled' => true,
            'base_url' => 'http://ollama.test/v1',
            'model' => 'local-model',
            'connect_timeout' => 5,
            'timeout' => 10,
        ],
    ]);
    $this->client = new LlmClient(new ToolRegistry([]));
    $this->sse = <<<'SSE'
data: {"model":"actual-model","choices":[{"delta":{"role":"assistant","content":""},"finish_reason":null}]}

data: {"model":"actual-model","choices":[{"delta":{"content":"Periksa "},"finish_reason":null}]}

data: {"model":"actual-model","choices":[{"delta":{"content":"otorisasi."},"finish_reason":null}]}

data: {"model":"actual-model","choices":[{"delta":{},"finish_reason":"stop"}]}

data: {"model":"actual-model","choices":[],"usage":{"prompt_tokens":12,"completion_tokens":8,"total_tokens":20}}

data: [DONE]

SSE;
});

it('delivers fragments before the stream ends and returns the completed response with usage', function (): void {
    $body = Utils::streamFor($this->sse);
    Http::fake(['ollama.test/*' => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);
    $chunks = [];
    $positions = [];

    $reply = $this->client->stream(
        [['role' => 'user', 'content' => 'Jelaskan IDOR.']],
        function (string $text) use (&$chunks, &$positions, $body): void {
            $chunks[] = $text;
            $positions[] = $body->tell();
        },
    );

    expect($chunks)->toBe(['Periksa ', 'otorisasi.']);
    expect($positions[0])->toBeLessThan(strlen($this->sse));
    expect($body->isReadable())->toBeFalse();
    expect($reply->content)->toBe('Periksa otorisasi.')
        ->and($reply->raw['model'])->toBe('actual-model')
        ->and($reply->raw['usage'])->toBe([
            'prompt_tokens' => 12,
            'completion_tokens' => 8,
            'total_tokens' => 20,
        ]);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['stream'] === true
        && $request['stream_options']['include_usage'] === true
        && $request['model'] === 'local-model'
        && $request['messages'][0]['role'] === 'system'
        && ! $request->hasHeader('Authorization')
        && ! isset($request['tools']));
});

it('streams from Senopati after Ollama fails before delivering text', function (): void {
    Http::fake([
        'ollama.test/*' => Http::failedConnection(),
        'senopati.test/*' => Http::response($this->sse, 200, ['Content-Type' => 'text/event-stream']),
    ]);
    $chunks = [];

    $reply = $this->client->stream(
        [['role' => 'user', 'content' => 'Halo']],
        function (string $text) use (&$chunks): void {
            $chunks[] = $text;
        },
    );

    expect($chunks)->toBe(['Periksa ', 'otorisasi.']);
    expect($reply->raw['usage']['total_tokens'])->toBe(20);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://senopati.test/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-senopati-key')
        && $request['model'] === 'remote-model'
        && $request['stream'] === true);
});

it('reports an interrupted stream after emitted text and keeps it on one provider', function (): void {
    Http::fake(['ollama.test/*' => Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"Sebagian jawaban\"},\"finish_reason\":null}]}\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);
    $chunks = [];

    expect(function () use (&$chunks): void {
        $this->client->stream(
            [['role' => 'user', 'content' => 'Halo']],
            function (string $text) use (&$chunks): void {
                $chunks[] = $text;
            },
        );
    })->toThrow(ConnectionException::class);

    expect($chunks)->toBe(['Sebagian jawaban']);
    Http::assertSentCount(1);
});

it('reads multiline data fields with CRLF endings and UTF-8 text', function (): void {
    $sse = ": heartbeat\r\n"
        ."event: update\r\n"
        ."data: {\"choices\":\r\n"
        ."data: [{\"delta\":{\"content\":\"Aman 🔒\"},\"finish_reason\":\"stop\"}]}\r\n\r\n"
        ."data: [DONE]\r\n\r\n";
    Http::fake(['ollama.test/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);
    $chunks = [];

    $reply = $this->client->stream(
        [['role' => 'user', 'content' => 'Halo']],
        function (string $text) use (&$chunks): void {
            $chunks[] = $text;
        },
    );

    expect($chunks)->toBe(['Aman 🔒']);
    expect($reply->content)->toBe('Aman 🔒')
        ->and($reply->raw['model'])->toBe('local-model');
    Http::assertSentCount(1);
});

it('reads a final event without a trailing newline', function (): void {
    $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Selesai.\"}}]}\n\n"
        .'data: [DONE]';
    Http::fake(['ollama.test/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);
    $chunks = [];

    $reply = $this->client->stream(
        [['role' => 'user', 'content' => 'Halo']],
        function (string $text) use (&$chunks): void {
            $chunks[] = $text;
        },
    );

    expect($chunks)->toBe(['Selesai.']);
    expect($reply->content)->toBe('Selesai.');
    Http::assertSentCount(1);
});

it('reports malformed streaming data', function (): void {
    Http::fake(['ollama.test/*' => Http::response("data: {broken}\n\n", 200, [
        'Content-Type' => 'text/event-stream',
    ])]);
    $chunks = [];

    expect(function () use (&$chunks): void {
        $this->client->stream(
            [['role' => 'user', 'content' => 'Halo']],
            function (string $text) use (&$chunks): void {
                $chunks[] = $text;
            },
        );
    })->toThrow(RequestException::class);

    expect($chunks)->toBe([]);
    Http::assertSentCount(1);
});
