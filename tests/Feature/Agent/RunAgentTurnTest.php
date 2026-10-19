<?php

use App\Jobs\RunAgentTurn;
use App\Models\ChatSession;
use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('llm.base_url', 'https://llm.test/v1');

    $this->analyst = User::factory()->create()->assignRole('analyst');
    $this->target = Target::factory()->create(['status_verifikasi' => Target::VERIFIED]);
    $this->session = ChatSession::factory()->for($this->analyst)->create();
    $this->session->chatMessages()->create(['role' => 'user', 'isi' => 'Halo']);
});

it('runs the agent loop for the queued turn', function () {
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['role' => 'assistant', 'content' => 'Halo juga.']]],
    ])]);

    RunAgentTurn::dispatchSync($this->session->id, $this->analyst->id, $this->target->id);

    expect($this->session->chatMessages()->latest('id')->first()->isi)->toBe('Halo juga.');
});

it('does not run when the target was rejected after the turn was queued', function () {
    Http::fake();
    $this->target->forceFill(['status_verifikasi' => Target::REJECTED])->save();

    RunAgentTurn::dispatchSync($this->session->id, $this->analyst->id, $this->target->id);

    expect($this->session->chatMessages()->latest('id')->first()->isi)->toContain('tidak dijalankan');

    Http::assertNothingSent();
});

it('leaves a message in the chat when the job fails', function () {
    (new RunAgentTurn($this->session->id, $this->analyst->id, $this->target->id))
        ->failed(new RuntimeException('LLM down'));

    expect($this->session->chatMessages()->latest('id')->first())
        ->role->toBe('assistant')
        ->isi->toContain('gagal');
});
