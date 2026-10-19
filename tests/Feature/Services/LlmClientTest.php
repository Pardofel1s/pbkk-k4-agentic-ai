<?php

use App\Services\Llm\LlmClient;
use App\Tools\Tool;
use App\Tools\ToolRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('llm.base_url', 'https://llm.test/v1');
    config()->set('llm.model', 'qwen3:14b');
    config()->set('llm.api_key', 'secret-key');
    config()->set('llm.timeout', 30);

    $stubTool = new class () implements Tool {
        public function name(): string
        {
            return 'stub_tool';
        }

        public function description(): string
        {
            return 'Stub tool for request-shape tests.';
        }

        public function parameters(): array
        {
            return ['type' => 'object', 'properties' => ['url' => ['type' => 'string']], 'required' => ['url']];
        }

        public function handle(array $arguments): string
        {
            return 'ok';
        }
    };

    $this->bareClient = new LlmClient(new ToolRegistry([]));
    $this->toolClient = new LlmClient(new ToolRegistry([$stubTool]));
});

it('returns assistant text for a plain response', function () {
    Http::fake([
        'llm.test/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hello there.']]],
        ]),
    ]);

    $response = $this->bareClient->chat([['role' => 'user', 'content' => 'hi']]);

    expect($response->content)->toBe('Hello there.')
        ->and($response->hasToolCalls())->toBeFalse()
        ->and($response->toolCalls)->toBe([]);
});

it('parses tool_calls from the response and decodes their arguments', function () {
    Http::fake([
        'llm.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => [
                            'name' => 'check_security_headers',
                            'arguments' => '{"url":"https://example.test"}',
                        ],
                    ]],
                ],
            ]],
        ]),
    ]);

    $response = $this->toolClient->chat([['role' => 'user', 'content' => 'scan example.test']]);

    expect($response->hasToolCalls())->toBeTrue()
        ->and($response->toolCalls)->toHaveCount(1);

    $call = $response->toolCalls[0];
    expect($call->id)->toBe('call_1')
        ->and($call->name)->toBe('check_security_headers')
        ->and($call->arguments)->toBe(['url' => 'https://example.test']);
});

it('posts to /chat/completions with the model, tool schemas and bearer token', function () {
    Http::fake([
        'llm.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]]),
    ]);

    $this->toolClient->chat([['role' => 'user', 'content' => 'hi']]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://llm.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer secret-key')
            && $body['model'] === 'qwen3:14b'
            && $body['tool_choice'] === 'auto'
            && $body['tools'][0]['function']['name'] === 'stub_tool';
    });
});

it('omits tools when none are registered', function () {
    Http::fake([
        'llm.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]]),
    ]);

    $this->bareClient->chat([['role' => 'user', 'content' => 'hi']]);

    Http::assertSent(fn (Request $request) => ! array_key_exists('tools', $request->data()));
});

it('can skip tools for a plain call even when some are registered', function () {
    Http::fake([
        'llm.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]]),
    ]);

    $this->toolClient->chat([['role' => 'user', 'content' => 'hi']], ['tools' => false]);

    Http::assertSent(fn (Request $request) => ! array_key_exists('tools', $request->data()));
});

it('throws on an error response', function () {
    Http::fake([
        'llm.test/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $this->bareClient->chat([['role' => 'user', 'content' => 'hi']]);
})->throws(RequestException::class);
