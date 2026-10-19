<?php

use App\Jobs\RunAgentTurn;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use App\Services\Agent\StartAgentTurn;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Queue::fake();

    $this->analyst = User::factory()->create()->assignRole('analyst');
    $this->target = Target::factory()->create(['status_verifikasi' => Target::VERIFIED]);
    $this->session = ChatSession::factory()->for($this->analyst)->create();
    $this->start = app(StartAgentTurn::class);
});

it('saves the prompt and queues the agent for an analyst on a verified target', function () {
    $message = $this->start->handle($this->analyst, $this->session, $this->target, 'Cek header keamanan');

    expect($message->role)->toBe('user')
        ->and($message->isi)->toBe('Cek header keamanan')
        ->and($message->chat_session_id)->toBe($this->session->id);

    Queue::assertPushed(RunAgentTurn::class, fn (RunAgentTurn $job) => $job->chatSessionId === $this->session->id
        && $job->userId === $this->analyst->id
        && $job->targetId === $this->target->id
        && $job->scanId === null);
});

it('passes the scan along when it belongs to the target', function () {
    $scan = Scan::factory()->for($this->target)->create();

    $this->start->handle($this->analyst, $this->session, $this->target, 'Cek header', $scan);

    Queue::assertPushed(RunAgentTurn::class, fn (RunAgentTurn $job) => $job->scanId === $scan->id);
});

it('refuses a second prompt while the first turn is still running', function () {
    expect(StartAgentTurn::isBusy($this->session))->toBeFalse();

    $this->start->handle($this->analyst, $this->session, $this->target, 'Prompt pertama');

    expect(StartAgentTurn::isBusy($this->session))->toBeTrue()
        ->and(fn () => $this->start->handle($this->analyst, $this->session, $this->target, 'Prompt kedua'))
        ->toThrow(ValidationException::class, 'masih memproses');

    expect($this->session->chatMessages()->pluck('isi')->all())->toBe(['Prompt pertama']);
    Queue::assertPushed(RunAgentTurn::class, 1);
});

it('lets another session start a turn at the same time', function () {
    $otherSession = ChatSession::factory()->for($this->analyst)->create();

    $this->start->handle($this->analyst, $this->session, $this->target, 'Sesi satu');
    $this->start->handle($this->analyst, $otherSession, $this->target, 'Sesi dua');

    Queue::assertPushed(RunAgentTurn::class, 2);
});

describe('refused requests save and queue nothing', function () {
    afterEach(function () {
        expect(ChatMessage::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('refuses users without chat.use and scans.run', function () {
        $client = User::factory()->create()->assignRole('client');
        $session = ChatSession::factory()->for($client)->create();

        expect(fn () => $this->start->handle($client, $session, $this->target, 'Cek header'))
            ->toThrow(AuthorizationException::class);
    });

    it('refuses targets that are not verified', function (string $status) {
        $target = Target::factory()->create(['status_verifikasi' => $status]);

        expect(fn () => $this->start->handle($this->analyst, $this->session, $target, 'Cek header'))
            ->toThrow(AuthorizationException::class);
    })->with([Target::PENDING, Target::REJECTED]);

    it("refuses another user's chat session", function () {
        $otherSession = ChatSession::factory()->for(User::factory()->create()->assignRole('analyst'))->create();

        expect(fn () => $this->start->handle($this->analyst, $otherSession, $this->target, 'Cek header'))
            ->toThrow(AuthorizationException::class);
    });

    it('refuses a scan of another target', function () {
        $scan = Scan::factory()->create();

        expect(fn () => $this->start->handle($this->analyst, $this->session, $this->target, 'Cek header', $scan))
            ->toThrow(AuthorizationException::class);
    });

    it('requires a prompt', function () {
        expect(fn () => $this->start->handle($this->analyst, $this->session, $this->target, ''))
            ->toThrow(ValidationException::class);
    });
});
