<?php

use App\Models\ChatLog;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('llm.base_url', 'https://llm.test/v1');
    config()->set('llm.model', 'qwen3:14b');
    config()->set('llm.api_key', 'secret-key');

    $this->user = User::factory()->create();

    $this->completion = [
        'model' => 'qwen3:14b',
        'choices' => [['message' => ['role' => 'assistant', 'content' => 'Middleware adalah...']]],
        'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 30, 'total_tokens' => 42],
    ];
});

describe('authentication', function () {
    it('rejects a request without a token', function () {
        Http::fake();

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])->assertUnauthorized();

        Http::assertNothingSent();
        expect(ChatLog::query()->count())->toBe(0);
    });

    it('rejects an invalid token', function () {
        Http::fake();

        $this->withToken('not-a-real-token')
            ->postJson('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertUnauthorized();

        Http::assertNothingSent();
    });
});

describe('validation', function () {
    beforeEach(function () {
        Http::fake();
        Sanctum::actingAs($this->user);
    });

    afterEach(function () {
        Http::assertNothingSent();
        expect(ChatLog::query()->count())->toBe(0);
    });

    it('refuses a prompt that is missing, empty, not a string or too long', function (mixed $prompt) {
        $this->postJson('/api/chat-ai', ['prompt' => $prompt])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prompt');
    })->with([
        'empty' => '',
        'array' => [['Halo']],
        'number' => 123,
        'one character too long' => str_repeat('a', 1001),
    ]);

    it('refuses a request without the prompt field', function () {
        $this->postJson('/api/chat-ai', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prompt');
    });
});

describe('chat', function () {
    it('returns the answer in the standard envelope and logs it', function () {
        Http::fake(['llm.test/*' => Http::response($this->completion)]);
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/chat-ai', ['prompt' => 'Jelaskan middleware!'])
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => ['session_id', 'model_used', 'prompt', 'ai_response', 'timestamp'],
            ])
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.model_used', 'qwen3:14b')
            ->assertJsonPath('data.prompt', 'Jelaskan middleware!')
            ->assertJsonPath('data.ai_response', 'Middleware adalah...');

        $log = ChatLog::query()->sole();

        expect($response->json('data.session_id'))->toBe($log->id)
            ->and($response->json('data.timestamp'))->toBe($log->created_at->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('chat_logs', [
            'id' => $log->id,
            'user_id' => $this->user->id,
            'prompt' => 'Jelaskan middleware!',
            'ai_response' => 'Middleware adalah...',
            'model_used' => 'qwen3:14b',
            'prompt_tokens' => 12,
            'completion_tokens' => 30,
            'total_tokens' => 42,
        ]);
    });

    it('sends only the prompt to the LLM, without tools', function () {
        Http::fake(['llm.test/*' => Http::response($this->completion)]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/chat-ai', ['prompt' => 'Jelaskan middleware!'])->assertOk();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://llm.test/v1/chat/completions'
            && $request['messages'][0]['role'] === 'user'
            && $request['messages'][0]['content'] === 'Jelaskan middleware!'
            && ! isset($request['tools']));
    });

    it('accepts a prompt of exactly 1000 characters', function () {
        Http::fake(['llm.test/*' => Http::response($this->completion)]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/chat-ai', ['prompt' => str_repeat('a', 1000)])->assertOk();

        expect(ChatLog::query()->count())->toBe(1);
    });

    it('records the model the provider reports and falls back to the configured one', function () {
        Http::fake(['llm.test/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Halo!']]],
        ])]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertOk()
            ->assertJsonPath('data.model_used', 'qwen3:14b');

        $this->assertDatabaseHas('chat_logs', [
            'model_used' => 'qwen3:14b',
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'total_tokens' => 0,
        ]);
    });

    it('keeps the exchanges of different users apart', function () {
        Http::fake(['llm.test/*' => Http::response($this->completion)]);
        $other = User::factory()->create();

        Sanctum::actingAs($this->user);
        $this->postJson('/api/chat-ai', ['prompt' => 'Dari user satu'])->assertOk();

        Sanctum::actingAs($other);
        $this->postJson('/api/chat-ai', ['prompt' => 'Dari user dua'])->assertOk();

        expect($this->user->chatLogs()->pluck('prompt')->all())->toBe(['Dari user satu'])
            ->and($other->chatLogs()->pluck('prompt')->all())->toBe(['Dari user dua']);
    });
});

describe('LLM failures', function () {
    afterEach(function () {
        expect(ChatLog::query()->count())->toBe(0);
    });

    it('answers 502 and logs nothing when the LLM returns an error', function () {
        Http::fake(['llm.test/*' => Http::response(['error' => 'boom'], 500)]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertStatus(502)
            ->assertJsonPath('status', 'error');
    });

    it('answers 502 and logs nothing when the LLM cannot be reached', function () {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        Sanctum::actingAs($this->user);

        $this->postJson('/api/chat-ai', ['prompt' => 'Halo'])
            ->assertStatus(502)
            ->assertJsonPath('status', 'error');
    });
});
