<?php

use App\Models\ChatSession;
use App\Models\Scan;
use App\Models\Target;
use App\Models\Tool as ToolRecord;
use App\Models\ToolCall as ToolCallRecord;
use App\Models\User;
use App\Services\Agent\AgentLoop;
use App\Tools\ToolContext;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * An OpenAI chat completion that answers with plain text.
 *
 * @return array<string, mixed>
 */
function llmTextReply(string $text): array
{
    return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]];
}

/**
 * An OpenAI chat completion that asks for one tool call.
 *
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function llmToolCallReply(string $name, array $arguments, string $id = 'call_1'): array
{
    return ['choices' => [['message' => [
        'role' => 'assistant',
        'content' => null,
        'tool_calls' => [[
            'id' => $id,
            'type' => 'function',
            'function' => ['name' => $name, 'arguments' => json_encode($arguments)],
        ]],
    ]]]];
}

/**
 * The `tool` message the agent sent back to the LLM for a call id, if any.
 *
 * @return array<string, mixed>|null
 */
function sentToolMessage(string $callId): ?array
{
    foreach (Http::recorded() as [$request]) {
        foreach ($request->data()['messages'] ?? [] as $message) {
            if (($message['role'] ?? null) === 'tool' && $message['tool_call_id'] === $callId) {
                return $message;
            }
        }
    }

    return null;
}

beforeEach(function () {
    config()->set('llm.base_url', 'https://llm.test/v1');
    config()->set('llm.api_key', null);
    config()->set('llm.allowed_hosts', ['example.test', 'other.test']);

    $this->analyst = User::factory()->create()->assignRole('analyst');
    $this->target = Target::factory()->create(['base_url' => 'https://example.test', 'status_verifikasi' => Target::VERIFIED]);
    $this->session = ChatSession::factory()->for($this->analyst)->create();
    $this->session->chatMessages()->create(['role' => 'user', 'isi' => 'Cek header keamanan example.test']);
    $this->context = new ToolContext($this->analyst, $this->target);
    $this->loop = app(AgentLoop::class);
});

it('saves a plain answer and runs no tools', function () {
    Http::fake(['llm.test/*' => Http::response(llmTextReply('Halo, mau cek apa?'))]);

    $reply = $this->loop->run($this->session, $this->context);

    expect($reply->role)->toBe('assistant')
        ->and($reply->isi)->toBe('Halo, mau cek apa?')
        ->and(ToolCallRecord::query()->count())->toBe(0);

    Http::assertSent(function (Request $request) {
        $messages = $request->data()['messages'];

        return $messages[0]['role'] === 'system'
            && str_contains($messages[0]['content'], 'https://example.test')
            && $messages[1] === ['role' => 'user', 'content' => 'Cek header keamanan example.test'];
    });
});

it('runs the requested tool, logs it and sends the result back to the LLM', function () {
    $scan = Scan::factory()->for($this->target)->create();

    Http::fake([
        'llm.test/*' => Http::sequence()
            ->push(llmToolCallReply('check_security_headers', ['url' => 'https://example.test'], 'call_7'))
            ->push(llmTextReply('CSP belum dipasang.')),
        'example.test*' => Http::response('ok', 200, ['X-Frame-Options' => 'DENY']),
    ]);

    $reply = $this->loop->run($this->session, new ToolContext($this->analyst, $this->target, $scan));

    expect($reply->isi)->toBe('CSP belum dipasang.')
        ->and($this->session->chatMessages()->pluck('role')->all())->toBe(['user', 'assistant', 'assistant']);

    $log = ToolCallRecord::query()->sole();

    expect($log->status)->toBe('success')
        ->and($log->tool->nama)->toBe('check_security_headers')
        ->and($log->parameter)->toBe(['url' => 'https://example.test'])
        ->and($log->hasil['output'])->toContain('X-Frame-Options: DENY')
        ->and($log->scan_id)->toBe($scan->id)
        ->and($log->loggable->is($this->target))->toBeTrue()
        ->and($log->chatMessage->role)->toBe('assistant');

    expect(sentToolMessage('call_7')['content'])->toContain('X-Frame-Options: DENY');
});

it('creates the tools row from the registered tool on first use', function () {
    Http::fake([
        'llm.test/*' => Http::sequence()
            ->push(llmToolCallReply('check_security_headers', ['url' => 'https://example.test']))
            ->push(llmTextReply('Selesai.')),
        'example.test*' => Http::response('ok'),
    ]);

    $this->loop->run($this->session, $this->context);

    expect(ToolRecord::query()->sole())
        ->nama->toBe('check_security_headers')
        ->status->toBeTrue();
});

it('blocks and logs a tool call outside the target scope', function () {
    Http::fake([
        'llm.test/*' => Http::sequence()
            ->push(llmToolCallReply('check_security_headers', ['url' => 'https://other.test']))
            ->push(llmTextReply('Host itu di luar scope.')),
        '*' => Http::response('should not be requested'),
    ]);

    $this->loop->run($this->session, $this->context);

    expect(ToolCallRecord::query()->sole())
        ->status->toBe('failed')
        ->error_msg->toContain('outside the scope');

    expect(sentToolMessage('call_1')['content'])->toStartWith('Error: invalid arguments.');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'other.test'));
});

it('answers an unknown tool with an error and keeps going', function () {
    Http::fake(['llm.test/*' => Http::sequence()
        ->push(llmToolCallReply('rm_rf', ['path' => '/']))
        ->push(llmTextReply('Tool itu tidak ada.')),
    ]);

    $reply = $this->loop->run($this->session, $this->context);

    expect($reply->isi)->toBe('Tool itu tidak ada.')
        ->and(ToolCallRecord::query()->count())->toBe(0)
        ->and(sentToolMessage('call_1')['content'])->toBe('Error: unknown tool [rm_rf].');
});

it('refuses a tool that is disabled in the tools table', function () {
    ToolRecord::query()->create(['nama' => 'check_security_headers', 'deskripsi' => 'x', 'status' => false]);

    Http::fake([
        'llm.test/*' => Http::sequence()
            ->push(llmToolCallReply('check_security_headers', ['url' => 'https://example.test']))
            ->push(llmTextReply('Tool dimatikan.')),
        'example.test*' => Http::response('ok'),
    ]);

    $this->loop->run($this->session, $this->context);

    expect(ToolCallRecord::query()->sole())
        ->status->toBe('failed')
        ->error_msg->toBe('Tool is disabled.');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'example.test'));
});

it('stops after the configured number of steps', function () {
    config()->set('llm.max_steps', 2);

    Http::fake([
        'llm.test/*' => Http::response(llmToolCallReply('check_security_headers', ['url' => 'https://example.test'])),
        'example.test*' => Http::response('ok'),
    ]);

    $reply = $this->loop->run($this->session, $this->context);

    expect($reply->isi)->toContain('Batas 2 langkah')
        ->and(ToolCallRecord::query()->count())->toBe(2);
});
