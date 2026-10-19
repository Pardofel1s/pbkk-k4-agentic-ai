<?php

use App\Tools\CheckSecurityHeadersTool;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

it('exposes an OpenAI-compatible schema', function () {
    $tool = new CheckSecurityHeadersTool();

    expect($tool->name())->toBe('check_security_headers')
        ->and($tool->parameters()['required'])->toBe(['url'])
        ->and($tool->parameters()['properties'])->toHaveKey('url');
});

it('reports present and missing security headers', function () {
    Http::fake([
        'example.test' => Http::response('ok', 200, [
            'Content-Security-Policy' => "default-src 'self'",
            'X-Content-Type-Options' => 'nosniff',
        ]),
    ]);

    $result = (new CheckSecurityHeadersTool())->handle(['url' => 'https://example.test']);

    expect($result)->toContain('HTTP 200')
        ->toContain('Content-Security-Policy')
        ->toContain('nosniff')
        // not returned by the fake, so it must be listed as missing
        ->toContain('Strict-Transport-Security (forces HTTPS (HSTS))');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://example.test');
});

it('rejects a non-url argument from the model', function () {
    Http::fake();

    (new CheckSecurityHeadersTool())->handle(['url' => 'not a url']);
})->throws(ValidationException::class);

it('rejects a non-http scheme', function () {
    Http::fake();

    (new CheckSecurityHeadersTool())->handle(['url' => 'file:///etc/passwd']);
})->throws(ValidationException::class);

it('requires the url argument', function () {
    Http::fake();

    (new CheckSecurityHeadersTool())->handle([]);
})->throws(ValidationException::class);
