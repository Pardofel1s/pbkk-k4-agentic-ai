<?php

use App\Models\ChatLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set([
        'llm.base_url' => 'https://llm.test/v1',
        'llm.model' => 'test-model',
        'llm.api_key' => 'test-key',
        'llm.ollama.enabled' => false,
    ]);
    $this->user = User::factory()->create();
    $this->sse = <<<'SSE'
data: {"model":"test-model","choices":[{"delta":{"content":"Periksa "},"finish_reason":null}]}

data: {"model":"test-model","choices":[{"delta":{"content":"otorisasi."},"finish_reason":"stop"}]}

data: {"model":"test-model","choices":[],"usage":{"prompt_tokens":10,"completion_tokens":5,"total_tokens":15}}

data: [DONE]

SSE;
});

it('protects the streaming endpoint with Sanctum', function (): void {
    $this->postJson('/api/chat-ai/stream', ['prompt' => 'Halo'])->assertUnauthorized();

    Http::assertNothingSent();
    $this->assertDatabaseCount('chat_logs', 0);
});

it('validates the prompt before starting the stream', function (): void {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/chat-ai/stream', ['prompt' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('prompt');

    Http::assertNothingSent();
    $this->assertDatabaseCount('chat_logs', 0);
});

it('streams chunks and a final resource after saving the complete answer', function (): void {
    Http::fake(['llm.test/*' => Http::response($this->sse, 200, [
        'Content-Type' => 'text/event-stream',
    ])]);
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/chat-ai/stream', ['prompt' => 'Jelaskan IDOR.'])
        ->assertOk()
        ->assertStreamed()
        ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    $content = $response->streamedContent();

    expect($content)->toStartWith(
        "event: chunk\ndata: {\"text\":\"Periksa \"}\n\n"
        ."event: chunk\ndata: {\"text\":\"otorisasi.\"}\n\n",
    )->toContain("event: done\n")->not->toContain("event: error\n");
    $this->assertDatabaseCount('chat_logs', 1);
    $this->assertDatabaseHas('chat_logs', [
        'user_id' => $this->user->id,
        'prompt' => 'Jelaskan IDOR.',
        'ai_response' => 'Periksa otorisasi.',
        'model_used' => 'test-model',
        'prompt_tokens' => 10,
        'completion_tokens' => 5,
        'total_tokens' => 15,
    ]);

    preg_match('/event: done\ndata: ([^\n]+)\n\n/', $content, $matches);
    $done = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    expect($done['status'])->toBe('success');
    expect($done['data']['session_id'])->toBe(ChatLog::query()->sole()->id);
    expect($done['data']['ai_response'])->toBe('Periksa otorisasi.');
    Http::assertSentCount(1);
});

it('emits an error event when the provider fails', function (): void {
    Http::fake(['llm.test/*' => Http::response(['error' => 'offline'], 503)]);
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/chat-ai/stream', ['prompt' => 'Halo'])
        ->assertOk()
        ->assertStreamed();
    $content = $response->streamedContent();

    expect($content)->toContain("event: error\n")
        ->not->toContain("event: done\n");
    $this->assertDatabaseCount('chat_logs', 0);
    Http::assertSentCount(1);
});

it('emits an error after a partial answer without saving an incomplete log', function (): void {
    Http::fake(['llm.test/*' => Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"Sebagian jawaban\"},\"finish_reason\":null}]}\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/chat-ai/stream', ['prompt' => 'Halo'])->assertOk()->assertStreamed();
    $content = $response->streamedContent();

    expect($content)->toStartWith("event: chunk\ndata: {\"text\":\"Sebagian jawaban\"}\n\n")
        ->toContain("event: error\n")
        ->not->toContain("event: done\n");
    $this->assertDatabaseCount('chat_logs', 0);
    Http::assertSentCount(1);
});
