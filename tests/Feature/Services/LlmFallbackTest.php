<?php

use App\Services\Llm\LlmClient;
use App\Tools\ToolRegistry;
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
});

it('uses Ollama with the configured system prompt and preserves usage', function (): void {
    Http::fake(['ollama.test/*' => Http::response([
        'choices' => [['message' => ['content' => 'Periksa kontrol akses.']]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
    ])]);

    $reply = $this->client->chat([['role' => 'user', 'content' => 'Jelaskan IDOR.']], ['tools' => false]);

    expect($reply->content)->toBe('Periksa kontrol akses.')
        ->and($reply->raw['model'])->toBe('local-model')
        ->and($reply->raw['usage']['total_tokens'])->toBe(15);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://ollama.test/v1/chat/completions'
        && ! $request->hasHeader('Authorization')
        && $request['model'] === 'local-model'
        && $request['messages'][0] === ['role' => 'system', 'content' => 'Anda adalah asisten DAST.']
        && $request['messages'][1]['content'] === 'Jelaskan IDOR.'
        && $request['stream'] === false);
});

it('falls back after a connection failure and uses Senopati credentials and model', function (): void {
    Http::fake([
        'ollama.test/*' => Http::failedConnection(),
        'senopati.test/*' => Http::response([
            'model' => 'remote-model-version',
            'choices' => [['message' => ['content' => 'Gunakan validasi server.']]],
            'usage' => ['total_tokens' => 27],
        ]),
    ]);

    $reply = $this->client->chat([['role' => 'user', 'content' => 'Bagaimana mitigasinya?']]);

    expect($reply->content)->toBe('Gunakan validasi server.')
        ->and($reply->raw['model'])->toBe('remote-model-version')
        ->and($reply->raw['usage']['total_tokens'])->toBe(27);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://senopati.test/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-senopati-key')
        && $request['model'] === 'remote-model');
});

it('falls back after an Ollama server error', function (): void {
    Http::fake([
        'ollama.test/*' => Http::response([], 503),
        'senopati.test/*' => Http::response([
            'choices' => [['message' => ['content' => 'Jawaban cadangan.']]],
        ]),
    ]);

    $reply = $this->client->chat([['role' => 'user', 'content' => 'Halo']]);

    expect($reply->content)->toBe('Jawaban cadangan.')
        ->and($reply->raw['model'])->toBe('remote-model');
    Http::assertSentCount(2);
});

it('reports an Ollama client error to the caller', function (): void {
    Http::fake(['ollama.test/*' => Http::response(['error' => 'model missing'], 404)]);

    expect(fn () => $this->client->chat([['role' => 'user', 'content' => 'Halo']]))
        ->toThrow(RequestException::class);
    Http::assertSentCount(1);
});

it('reports a failure when both providers are unreachable', function (): void {
    Http::fake([
        'ollama.test/*' => Http::failedConnection(),
        'senopati.test/*' => Http::failedConnection(),
    ]);

    expect(fn () => $this->client->chat([['role' => 'user', 'content' => 'Halo']]))
        ->toThrow(ConnectionException::class);
});

it('rejects an invalid or empty completion', function (array $payload): void {
    Http::fake(['ollama.test/*' => Http::response($payload)]);

    expect(fn () => $this->client->chat([['role' => 'user', 'content' => 'Halo']]))
        ->toThrow(RequestException::class);
})->with([
    'missing message' => [[]],
    'empty answer' => [['choices' => [['message' => ['content' => '   ']]]]],
]);

it('preserves an existing system message', function (): void {
    Http::fake(['ollama.test/*' => Http::response([
        'choices' => [['message' => ['content' => 'Siap.']]],
    ])]);
    $messages = [
        ['role' => 'system', 'content' => 'Analisis target lokal yang telah diverifikasi.'],
        ['role' => 'user', 'content' => 'Halo'],
    ];

    $this->client->chat($messages);

    Http::assertSent(fn (Request $request): bool => $request['messages'] === $messages);
});

it('calls Senopati directly when Ollama is disabled', function (): void {
    config()->set('llm.ollama.enabled', false);
    Http::fake(['senopati.test/*' => Http::response([
        'choices' => [['message' => ['content' => 'Halo dari Senopati.']]],
    ])]);

    $reply = $this->client->chat([['role' => 'user', 'content' => 'Halo']]);

    expect($reply->content)->toBe('Halo dari Senopati.');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://senopati.test/v1/chat/completions');
});
