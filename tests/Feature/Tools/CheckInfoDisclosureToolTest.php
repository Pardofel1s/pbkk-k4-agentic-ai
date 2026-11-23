<?php

use App\Models\Target;
use App\Models\User;
use App\Services\Scan\FindingRules;
use App\Tools\CheckInfoDisclosureTool;
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

it('reports version headers and a debug page on a missing path', function () {
    Http::fake([
        'example.test'.CheckInfoDisclosureTool::PROBE_PATH => Http::response('SQLSTATE[42S02]: Base table not found', 500),
        'example.test/*' => Http::response('ok', 200, ['Server' => 'nginx/1.18.0', 'X-Powered-By' => 'PHP/8.1.2']),
    ]);

    $result = (new CheckInfoDisclosureTool())->handle(['url' => 'https://example.test/home'], $this->context);
    $findings = app(FindingRules::class)->extract('check_info_disclosure', $result, 'https://example.test/home');

    expect(array_map(fn (array $f) => $f['type'].' '.$f['parameter'], $findings))->toBe([
        'version-disclosure Server',
        'version-disclosure X-Powered-By',
        'debug-error-page https://example.test'.CheckInfoDisclosureTool::PROBE_PATH,
    ]);
});

it('ignores product names without a version and plain error pages', function () {
    Http::fake(['example.test/*' => Http::response('<h1>404 Not Found</h1>', 404, ['Server' => 'cloudflare'])]);

    $result = (new CheckInfoDisclosureTool())->handle(['url' => 'https://example.test/'], $this->context);

    expect($result)->toContain('No version headers or debug output found.')
        ->and(app(FindingRules::class)->extract('check_info_disclosure', $result, 'https://example.test/'))->toBe([]);
});

it('refuses a host outside the target scope', function () {
    Http::fake();

    expect(fn () => (new CheckInfoDisclosureTool())->handle(['url' => 'https://other.test'], $this->context))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});
