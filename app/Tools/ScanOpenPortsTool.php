<?php

namespace App\Tools;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * DAST tool (Vulnerability Assessment pillar): scan a target host for open TCP
 * ports with nmap.
 *
 * nmap is run through Symfony Process (Laravel's Process facade) with an
 * ARGUMENT ARRAY, never a shell string, so a host or port the LLM supplies can
 * never be interpreted as a shell command (mitigasi command injection). The
 * host is also taken from a URL that already passed the target's scope check.
 */
class ScanOpenPortsTool implements Tool
{
    /**
     * Ports checked when the caller gives none: common web, db and admin ports.
     *
     * @var list<int>
     */
    private const DEFAULT_PORTS = [21, 22, 80, 443, 3000, 3306, 5432, 6379, 8000, 8080, 8443];

    private const MAX_PORTS = 50;

    public function name(): string
    {
        return 'scan_open_ports';
    }

    public function description(): string
    {
        return 'Scan a target host for open TCP ports using nmap, to map exposed '
            .'services. Only hosts within the target scope can be scanned.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Absolute http(s) URL of the target; its host is scanned.',
                ],
                'ports' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 65535],
                    'description' => 'Optional list of TCP ports. Defaults to common web/db/admin ports.',
                ],
            ],
            'required' => ['url'],
        ];
    }

    public function handle(array $arguments, ToolContext $context): string
    {
        $data = Validator::make($arguments, [
            'url' => ['required', 'url:http,https', function (string $attribute, mixed $value, \Closure $fail) use ($context): void {
                if (! is_string($value) || ! $context->allowsUrl($value)) {
                    $fail('The :attribute is outside the scope of this target.');
                }
            }],
            'ports' => ['sometimes', 'array', 'max:'.self::MAX_PORTS],
            'ports.*' => ['integer', 'between:1,65535'],
        ])->validate();

        $host = (string) parse_url($data['url'], PHP_URL_HOST);
        $ports = $data['ports'] ?? self::DEFAULT_PORTS;
        $portSpec = implode(',', array_map('intval', $ports));

        try {
            // Argument array: host and ports are argv elements, never a shell string.
            $result = Process::timeout(90)->run(['nmap', '-Pn', '-T4', '-p', $portSpec, $host]);
        } catch (Throwable $e) {
            return "Tidak bisa menjalankan nmap untuk {$host}: {$e->getMessage()}. Pastikan nmap terpasang di mesin ini.";
        }

        if ($result->failed()) {
            $detail = trim($result->errorOutput() ?: $result->output());

            return "nmap gagal untuk {$host} (exit {$result->exitCode()}): {$detail}";
        }

        return $this->report($host, $this->parsePorts($result->output()));
    }

    /**
     * Pull "80/tcp open http" lines out of nmap's normal output.
     *
     * @return list<array{port: int, state: string, service: string}>
     */
    private function parsePorts(string $output): array
    {
        $open = [];

        foreach (preg_split('/\r?\n/', $output) as $line) {
            if (preg_match('#^(\d+)/tcp\s+(\S+)\s+(\S+)#', trim($line), $m) && $m[2] === 'open') {
                $open[] = ['port' => (int) $m[1], 'state' => $m[2], 'service' => $m[3]];
            }
        }

        return $open;
    }

    /**
     * @param  list<array{port: int, state: string, service: string}>  $open
     */
    private function report(string $host, array $open): string
    {
        if ($open === []) {
            return "No open TCP ports found on {$host} among the scanned ports.";
        }

        $lines = ["Open TCP ports on {$host}:"];

        foreach ($open as $p) {
            $lines[] = "  - {$p['port']}/tcp {$p['service']}";
        }

        return implode("\n", $lines);
    }
}
