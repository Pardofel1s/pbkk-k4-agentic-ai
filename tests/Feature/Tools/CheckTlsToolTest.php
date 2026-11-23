<?php

use App\Models\Target;
use App\Models\User;
use App\Services\Scan\FindingRules;
use App\Tools\CheckTlsTool;
use App\Tools\ToolContext;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config()->set('llm.allowed_hosts', ['shop.example.test', 'other.test']);
    $this->travelTo('2026-11-24 00:00:00');

    $this->context = new ToolContext(
        User::factory()->create(),
        Target::factory()->create(['base_url' => 'https://shop.example.test']),
    );
});

/**
 * The tool with the network handshake replaced by a fixed result.
 */
function tlsTool(string $protocol, array $certificate): CheckTlsTool
{
    return new class ($protocol, $certificate) extends CheckTlsTool {
        public function __construct(private string $protocol, private array $certificate)
        {
        }

        protected function handshake(string $host, int $port): array
        {
            return ['protocol' => $this->protocol, 'certificate' => $this->certificate];
        }
    };
}

function certificate(array $overrides = []): array
{
    return array_merge([
        'subject' => ['CN' => 'shop.example.test'],
        'issuer' => ['O' => "Let's Encrypt", 'CN' => 'R11'],
        'validTo_time_t' => strtotime('2027-01-31'),
        'extensions' => ['subjectAltName' => 'DNS:*.example.test, DNS:example.test'],
    ], $overrides);
}

function tlsFindings(string $output): array
{
    return array_column(app(FindingRules::class)->extract('check_tls', $output, 'https://shop.example.test'), 'type');
}

it('passes a modern protocol with a valid wildcard certificate', function () {
    $result = tlsTool('TLSv1.3', certificate())->handle(['url' => 'https://shop.example.test'], $this->context);

    expect($result)->toContain('Protocol: TLSv1.3')->toContain('(68 days left)')
        ->and(tlsFindings($result))->toBe([]);
});

it('flags an old protocol and an expired, self-signed certificate for another host', function () {
    $result = tlsTool('TLSv1.1', certificate([
        'subject' => ['CN' => 'localhost'],
        'issuer' => ['CN' => 'localhost'],
        'validTo_time_t' => strtotime('2026-01-01'),
        'extensions' => [],
    ]))->handle(['url' => 'https://shop.example.test'], $this->context);

    expect(tlsFindings($result))->toBe(['tls-weak-protocol', 'tls-certificate-expired', 'tls-certificate-self-signed', 'tls-hostname-mismatch']);
});

it('does not let a wildcard cover a deeper subdomain', function () {
    config()->set('llm.allowed_hosts', ['a.shop.example.test']);
    $context = new ToolContext(User::factory()->create(), Target::factory()->create(['base_url' => 'https://a.shop.example.test']));

    $result = tlsTool('TLSv1.3', certificate())->handle(['url' => 'https://a.shop.example.test'], $context);

    expect(tlsFindings($result))->toBe(['tls-hostname-mismatch']);
});

it('skips plain http targets', function () {
    config()->set('llm.allowed_hosts', ['localhost']);
    $context = new ToolContext(User::factory()->create(), Target::factory()->create(['base_url' => 'http://localhost:8000']));

    expect((new CheckTlsTool())->handle(['url' => 'http://localhost:8000'], $context))->toContain('plain HTTP');
});

it('refuses a host outside the target scope', function () {
    (new CheckTlsTool())->handle(['url' => 'https://other.test'], $this->context);
})->throws(ValidationException::class);
