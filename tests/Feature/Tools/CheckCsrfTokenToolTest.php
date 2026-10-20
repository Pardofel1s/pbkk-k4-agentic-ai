<?php

use App\Models\Target;
use App\Models\User;
use App\Tools\CheckCsrfTokenTool;
use App\Tools\ToolContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config()->set('llm.allowed_hosts', ['example.test', 'other.test']);

    $this->context = new ToolContext(
        User::factory()->create(),
        Target::factory()->create(['base_url' => 'https://example.test']),
    );
});

it('flags a POST form without a token and passes one with a token', function () {
    $html = <<<'HTML'
    <form method="post" action="/transfer">
        <input type="text" name="amount">
    </form>
    <form method="POST" action="/login">
        <input type="hidden" name="_token" value="abc">
        <input type="password" name="password">
    </form>
    HTML;

    Http::fake(['example.test' => Http::response($html)]);

    $result = (new CheckCsrfTokenTool())->handle(['url' => 'https://example.test'], $this->context);

    expect($result)->toContain('2 POST form(s), 1 with a token')
        ->toContain('POST forms without a token field:')
        ->toContain('action="/transfer"')
        ->not->toContain('action="/login"');
});

it('ignores GET forms', function () {
    Http::fake(['example.test' => Http::response('<form method="get" action="/search"><input name="q"></form>')]);

    $result = (new CheckCsrfTokenTool())->handle(['url' => 'https://example.test'], $this->context);

    expect($result)->toContain('No POST forms found');
});

it('says so when all POST forms carry a token', function () {
    Http::fake(['example.test' => Http::response('<form method="post"><input type="hidden" name="csrf_token" value="x"></form>')]);

    $result = (new CheckCsrfTokenTool())->handle(['url' => 'https://example.test'], $this->context);

    expect($result)->toContain('All POST forms carry a token field');
});

it('refuses a host outside the target scope', function () {
    Http::fake();

    expect(fn () => (new CheckCsrfTokenTool())->handle(['url' => 'https://other.test'], $this->context))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});
