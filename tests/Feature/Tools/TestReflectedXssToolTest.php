<?php

use App\Models\Target;
use App\Models\User;
use App\Tools\TestReflectedXssTool;
use App\Tools\ToolContext;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config()->set('llm.allowed_hosts', ['example.test', 'other.test']);

    $this->context = new ToolContext(
        User::factory()->create(),
        Target::factory()->create(['base_url' => 'https://example.test']),
    );
});

/** Return the value of one query parameter of a faked request's URL. */
function queryValue(Request $request, string $param): string
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

    return (string) ($q[$param] ?? '');
}

it('flags a parameter whose value is reflected unencoded', function () {
    Http::fake(['example.test/*' => fn (Request $request) => Http::response('<div>'.queryValue($request, 'q').'</div>')]);

    $result = (new TestReflectedXssTool())->handle(['url' => 'https://example.test/search?q=hello'], $this->context);

    expect($result)->toContain('parameter "q": VULNERABLE');
});

it('treats an encoded reflection as likely safe', function () {
    Http::fake(['example.test/*' => fn (Request $request) => Http::response('<div>'.htmlspecialchars(queryValue($request, 'q'), ENT_QUOTES).'</div>')]);

    $result = (new TestReflectedXssTool())->handle(['url' => 'https://example.test/search?q=hello'], $this->context);

    expect($result)->toContain('reflected but encoded')
        ->not->toContain('VULNERABLE');
});

it('reports when the marker is not reflected at all', function () {
    Http::fake(['example.test/*' => Http::response('<div>static page</div>')]);

    $result = (new TestReflectedXssTool())->handle(['url' => 'https://example.test/search?q=hello'], $this->context);

    expect($result)->toContain('not reflected')
        ->toContain('No unencoded reflection found');
});

it('injects a q parameter when the url has none', function () {
    Http::fake(['example.test/*' => fn (Request $request) => Http::response('<div>'.queryValue($request, 'q').'</div>')]);

    $result = (new TestReflectedXssTool())->handle(['url' => 'https://example.test/'], $this->context);

    expect($result)->toContain('parameter "q": VULNERABLE');
});

it('refuses a host outside the target scope', function () {
    Http::fake();

    expect(fn () => (new TestReflectedXssTool())->handle(['url' => 'https://other.test/?q=1'], $this->context))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});
