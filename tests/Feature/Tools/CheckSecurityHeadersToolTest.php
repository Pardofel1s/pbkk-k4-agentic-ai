<?php

use App\Tools\CheckSecurityHeadersTool;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

it('only allows localhost and 127.0.0.1 by default', function () {
    Http::fake();

    expect(config('llm.allowed_hosts'))->toBe(['localhost', '127.0.0.1'])
        ->and(fn () => (new CheckSecurityHeadersTool())->handle(['url' => 'https://example.com']))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});

describe('with example.test allowed', function () {
    beforeEach(function () {
        config()->set('llm.allowed_hosts', ['example.test']);
    });

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
            ->toContain('Strict-Transport-Security (forces HTTPS (HSTS))');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://example.test');
    });

    it('refuses hosts outside the allowlist without sending a request', function () {
        Http::fake();

        expect(fn () => (new CheckSecurityHeadersTool())->handle(['url' => 'http://169.254.169.254/latest/meta-data']))
            ->toThrow(ValidationException::class);

        Http::assertNothingSent();
    });

    it('follows redirects that stay on an allowed host', function () {
        Http::fake([
            'example.test/old' => Http::response('', 301, ['Location' => '/new']),
            'example.test/new' => Http::response('ok', 200, ['X-Frame-Options' => 'DENY']),
        ]);

        $result = (new CheckSecurityHeadersTool())->handle(['url' => 'https://example.test/old']);

        expect($result)->toContain('Security headers for https://example.test/new (HTTP 200)')
            ->toContain('https://example.test/old -> https://example.test/new')
            ->toContain('X-Frame-Options: DENY');
    });

    it('does not follow a redirect to a host outside the allowlist', function () {
        Http::fake([
            'example.test*' => Http::response('', 302, ['Location' => 'https://evil.test/']),
            '*' => Http::response('ok'),
        ]);

        $result = (new CheckSecurityHeadersTool())->handle(['url' => 'https://example.test']);

        expect($result)->toContain('redirects to https://evil.test/, which is not in the allowed target list');

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil.test'));
    });

    it('stops after too many redirects', function () {
        Http::fake([
            'example.test*' => Http::response('', 302, ['Location' => '/loop']),
        ]);

        $result = (new CheckSecurityHeadersTool())->handle(['url' => 'https://example.test']);

        expect($result)->toContain('more than 5 redirects');
    });

    it('returns a readable message when the target cannot be reached', function () {
        Http::fake([
            'example.test*' => Http::failedConnection(),
        ]);

        $result = (new CheckSecurityHeadersTool())->handle(['url' => 'https://example.test']);

        expect($result)->toStartWith('Could not connect to https://example.test');
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
});
