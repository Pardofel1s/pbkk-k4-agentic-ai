<?php

use App\Models\Target;
use App\Models\User;
use App\Services\Scan\FindingRules;
use App\Tools\CheckCorsTool;
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

it('grades the CORS answer to a foreign origin', function (array $headers, ?string $slug) {
    Http::fake(['example.test/*' => Http::response('{}', 200, $headers)]);

    $result = (new CheckCorsTool())->handle(['url' => 'https://example.test/api'], $this->context);
    $findings = app(FindingRules::class)->extract('check_cors', $result, 'https://example.test/api');

    expect(array_column($findings, 'type'))->toBe($slug === null ? [] : [$slug]);
    Http::assertSent(fn ($request) => $request->header('Origin') === [CheckCorsTool::PROBE_ORIGIN]);
})->with([
    'reflected with credentials' => [['Access-Control-Allow-Origin' => CheckCorsTool::PROBE_ORIGIN, 'Access-Control-Allow-Credentials' => 'true'], 'cors-any-origin-credentials'],
    'reflected' => [['Access-Control-Allow-Origin' => CheckCorsTool::PROBE_ORIGIN], 'cors-any-origin'],
    'null origin' => [['Access-Control-Allow-Origin' => 'null'], 'cors-any-origin'],
    'public wildcard' => [['Access-Control-Allow-Origin' => '*'], null],
    'fixed allowlist' => [['Access-Control-Allow-Origin' => 'https://example.test'], null],
    'no CORS' => [[], null],
]);

it('refuses a host outside the target scope', function () {
    Http::fake();

    expect(fn () => (new CheckCorsTool())->handle(['url' => 'https://other.test'], $this->context))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});
