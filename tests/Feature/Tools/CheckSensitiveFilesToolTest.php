<?php

use App\Models\Target;
use App\Models\User;
use App\Services\Scan\FindingRules;
use App\Tools\CheckSensitiveFilesTool;
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

it('flags a file only when the body has its signature', function () {
    Http::fake([
        'example.test/.env' => Http::response("APP_NAME=Shop\nAPP_KEY=base64:abc\n"),
        'example.test/.git/HEAD' => Http::response('ref: refs/heads/main'),
        'example.test/phpinfo.php' => Http::response('Not Found', 404),
        // Like an SPA: every other path answers 200 with the index page.
        'example.test/*' => Http::response('<!doctype html><title>Shop</title>'),
    ]);

    $result = (new CheckSensitiveFilesTool())->handle(['url' => 'https://example.test/login'], $this->context);

    expect($result)->toStartWith('Sensitive files for https://example.test:')
        ->toContain('[exposed-sensitive-file] /.env: EXPOSED')
        ->toContain('[exposed-sensitive-file] /.git/HEAD: EXPOSED')
        ->toContain('/.git/config: not exposed (HTTP 200)')
        ->toContain('/phpinfo.php: not exposed (HTTP 404)');

    $findings = app(FindingRules::class)->extract('check_sensitive_files', $result, 'https://example.test/login');

    expect(array_column($findings, 'parameter'))->toBe(['/.env', '/.git/HEAD'])
        ->and(array_unique(array_column($findings, 'url')))->toBe(['https://example.test']);
});

it('only requests the origin of the given url', function () {
    Http::fake(['*' => Http::response('', 404)]);

    (new CheckSensitiveFilesTool())->handle(['url' => 'https://example.test/a/b?c=d'], $this->context);

    Http::assertSentCount(7);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://example.test/'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/a/b'));
});

it('refuses a host outside the target scope', function () {
    Http::fake();

    expect(fn () => (new CheckSensitiveFilesTool())->handle(['url' => 'https://other.test'], $this->context))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});
