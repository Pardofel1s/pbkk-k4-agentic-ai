<?php

use App\Models\ChatSession;
use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function analyst(): User
{
    return User::factory()->create()->assignRole('analyst');
}

function demoTarget(): Target
{
    return Target::factory()->create([
        'base_url' => 'http://localhost:8000',
        'status_verifikasi' => Target::VERIFIED,
    ]);
}

/**
 * LLM answers with one tool call, then (after seeing the tool result) a final text answer.
 */
function fakeAgentConversation(): void
{
    Http::fake([
        '*/chat/completions' => Http::sequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => ['name' => 'check_security_headers', 'arguments' => '{"url":"http://localhost:8000"}'],
                ]],
            ]]]])
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => 'Target tidak mengirim header Content-Security-Policy.',
            ]]]]),
        'localhost:8000*' => Http::response('ok', 200, ['X-Frame-Options' => 'DENY']),
    ]);
}

it('runs a full live agent turn: prompt, tool call, answer', function () {
    $user = analyst();
    $target = demoTarget();
    fakeAgentConversation();

    $this->actingAs($user);

    $component = Volt::test('agent.chat')
        ->call('startSession')
        ->set('targetId', $target->id)
        ->set('prompt', 'Periksa header keamanan target.')
        ->call('send');

    $session = ChatSession::query()->firstWhere('user_id', $user->id);

    expect($session->chatMessages()->where('role', 'user')->count())->toBe(1)
        ->and($session->chatMessages()->where('role', 'assistant')->where('isi', 'like', '%Content-Security-Policy%')->exists())->toBeTrue()
        ->and($session->chatMessages()->whereHas('toolCalls')->exists())->toBeTrue();

    $component->assertSet('prompt', '');
});

it('is reachable only by users who may chat', function () {
    demoTarget();

    $this->actingAs(analyst());
    Volt::test('agent.chat')->call('startSession')->assertOk();

    $this->actingAs(User::factory()->create()->assignRole('client'));
    Volt::test('agent.chat')->call('startSession')->assertForbidden();
});
