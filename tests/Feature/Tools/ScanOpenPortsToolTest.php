<?php

use App\Models\Target;
use App\Models\User;
use App\Tools\ScanOpenPortsTool;
use App\Tools\ToolContext;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config()->set('llm.allowed_hosts', ['example.test']);

    $this->context = new ToolContext(
        User::factory()->create(),
        Target::factory()->create(['base_url' => 'http://example.test']),
    );
});

$nmapOutput = <<<'TXT'
Starting Nmap 7.94
Nmap scan report for example.test (127.0.0.1)
PORT     STATE  SERVICE
22/tcp   closed ssh
80/tcp   open   http
8080/tcp open   http-proxy
Nmap done: 1 IP address (1 host up) scanned in 0.20 seconds
TXT;

it('parses open ports from nmap output', function () use ($nmapOutput) {
    Process::fake(['*' => Process::result(output: $nmapOutput)]);

    $result = (new ScanOpenPortsTool())->handle(['url' => 'http://example.test'], $this->context);

    expect($result)->toContain('Open TCP ports on example.test')
        ->toContain('80/tcp http')
        ->toContain('8080/tcp http-proxy')
        ->not->toContain('22/tcp');
});

it('passes the host and ports to nmap as an argument array, never a shell string', function () {
    Process::fake();

    (new ScanOpenPortsTool())->handle(['url' => 'http://example.test', 'ports' => [80, 443]], $this->context);

    Process::assertRan(function ($process) {
        return is_array($process->command)
            && $process->command === ['nmap', '-Pn', '-T4', '-p', '80,443', 'example.test'];
    });
});

it('uses the default common ports when none are given', function () {
    Process::fake();

    (new ScanOpenPortsTool())->handle(['url' => 'http://example.test'], $this->context);

    Process::assertRan(fn ($process) => in_array('21,22,80,443,3000,3306,5432,6379,8000,8080,8443', $process->command, true));
});

it('reports a readable message when nmap fails or is missing', function () {
    Process::fake(['*' => Process::result(errorOutput: "'nmap' is not recognized", exitCode: 127)]);

    $result = (new ScanOpenPortsTool())->handle(['url' => 'http://example.test'], $this->context);

    expect($result)->toContain('nmap gagal')->toContain('not recognized');
});

it('says so when no scanned port is open', function () {
    Process::fake(['*' => Process::result(output: "80/tcp closed http\n")]);

    $result = (new ScanOpenPortsTool())->handle(['url' => 'http://example.test'], $this->context);

    expect($result)->toContain('No open TCP ports');
});

it('refuses a host outside the target scope and runs nothing', function () {
    Process::fake();
    config()->set('llm.allowed_hosts', ['example.test', 'other.test']);

    expect(fn () => (new ScanOpenPortsTool())->handle(['url' => 'http://other.test'], $this->context))
        ->toThrow(ValidationException::class);

    Process::assertNothingRan();
});

it('rejects ports outside 1..65535', function () {
    Process::fake();

    expect(fn () => (new ScanOpenPortsTool())->handle(['url' => 'http://example.test', 'ports' => [99999]], $this->context))
        ->toThrow(ValidationException::class);
});
