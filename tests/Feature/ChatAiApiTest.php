<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['llm.base_url' => 'https://llm.test/v1', 'llm.model' => 'demo']);
});

it('rejects an absent or invalid bearer token', function (string $token): void {
    Http::fake();
    $this->withToken($token)->postJson('/api/chat-ai', ['prompt' => 'Halo'])->assertUnauthorized();
    Http::assertNothingSent();
})->with(['', 'invalid']);

it('validates the prompt before calling the provider', function (mixed $prompt): void {
    Http::fake();
    $token = User::factory()->create()->createToken('test')->plainTextToken;
    $this->withToken($token)->postJson('/api/chat-ai', ['prompt' => $prompt])
        ->assertUnprocessable()->assertJsonValidationErrors('prompt');
    Http::assertNothingSent();
})->with([null, '   ', 25, str_repeat('a', 1001)]);

it('records one complete turn for the token owner', function (): void {
    Http::fake(['llm.test/*' => Http::response([
        'model' => 'demo-v2',
        'choices' => [['message' => ['content' => 'Batasi akses per pengguna.']]],
        'usage' => ['total_tokens' => 47],
    ])]);
    $user = User::factory()->create();
    $prompt = str_repeat('a', 1000);
    $result = $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/chat-ai', ['prompt' => $prompt])
        ->assertOk()->assertJsonPath('status', 'success')
        ->assertJsonPath('data.prompt', $prompt)->assertJsonPath('data.model_used', 'demo-v2')
        ->assertJsonPath('data.ai_response', 'Batasi akses per pengguna.')
        ->assertJsonStructure(['data' => ['session_id', 'timestamp']]);
    $id = $result->json('data.session_id');
    $this->assertDatabaseHas("chat_logs", ["id" => $id, "user_id" => $user->id, "prompt" => $prompt, "ai_response" => "Batasi akses per pengguna.", "model_used" => "demo-v2", "total_tokens" => 47]);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://llm.test/v1/chat/completions'
        && $r['messages'][0]['role'] === 'system' && $r['messages'][1]['content'] === $prompt && ! isset($r['tools']));
});

it('keeps failed provider responses out of chat history', function (): void {
    Http::fake(['llm.test/*' => Http::response([], 500)]);
    $token = User::factory()->create()->createToken('test')->plainTextToken;
    $this->withToken($token)->postJson('/api/chat-ai', ['prompt' => 'Halo'])->assertStatus(502);
    $this->assertDatabaseCount('chat_logs', 0);
    $this->assertDatabaseCount('chat_messages', 0);
});
