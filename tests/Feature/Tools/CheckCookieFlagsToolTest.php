<?php

use App\Models\Target;
use App\Models\User;
use App\Tools\CheckCookieFlagsTool;
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

it('reports which cookie flags are missing and which are set', function () {
    Http::fake([
        'example.test' => Http::response('ok', 200, [
            'Set-Cookie' => [
                'session=abc; Path=/',
                'remember=x; Secure; HttpOnly; SameSite=Lax',
            ],
        ]),
    ]);

    $result = (new CheckCookieFlagsTool())->handle(['url' => 'https://example.test'], $this->context);

    expect($result)->toContain('session: missing Secure, HttpOnly, SameSite')
        ->toContain('remember: Secure, HttpOnly, SameSite all set');
});

it('says so when no cookies are set', function () {
    Http::fake(['example.test' => Http::response('ok')]);

    $result = (new CheckCookieFlagsTool())->handle(['url' => 'https://example.test'], $this->context);

    expect($result)->toContain('No cookies are set');
});

it('refuses a host outside the target scope', function () {
    Http::fake();

    expect(fn () => (new CheckCookieFlagsTool())->handle(['url' => 'https://other.test'], $this->context))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});

it('requires a valid url', function () {
    Http::fake();

    (new CheckCookieFlagsTool())->handle(['url' => 'not a url'], $this->context);
})->throws(ValidationException::class);
