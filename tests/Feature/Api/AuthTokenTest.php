<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'analyst@example.com']);
});

it('issues a bearer token for valid credentials', function () {
    $this->postJson('/api/login', ['email' => 'analyst@example.com', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure(['data' => ['token']]);

    expect($this->user->tokens()->count())->toBe(1);
});

it('lets the issued token call the chat endpoint', function () {
    config()->set('llm.base_url', 'https://llm.test/v1');
    Http::fake(['llm.test/*' => Http::response([
        'model' => 'qwen3:14b',
        'choices' => [['message' => ['role' => 'assistant', 'content' => 'Halo!']]],
    ])]);

    $token = $this->postJson('/api/login', ['email' => 'analyst@example.com', 'password' => 'password'])
        ->json('data.token');

    $this->withToken($token)
        ->postJson('/api/chat-ai', ['prompt' => 'Halo'])
        ->assertOk()
        ->assertJsonPath('data.ai_response', 'Halo!');
});

it('refuses wrong credentials', function (string $email, string $password) {
    $this->postJson('/api/login', ['email' => $email, 'password' => $password])
        ->assertUnauthorized()
        ->assertJsonPath('status', 'error');

    expect($this->user->tokens()->count())->toBe(0);
})->with([
    'wrong password' => ['analyst@example.com', 'salah'],
    'unknown email' => ['tidak-ada@example.com', 'password'],
]);

it('validates the login fields', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('revokes the token on logout', function () {
    $token = $this->postJson('/api/login', ['email' => 'analyst@example.com', 'password' => 'password'])
        ->json('data.token');

    $this->withToken($token)->postJson('/api/logout')->assertNoContent();

    expect($this->user->tokens()->count())->toBe(0);
});
