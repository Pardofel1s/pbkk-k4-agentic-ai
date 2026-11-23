<?php

namespace App\Tools;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * DAST tool for OWASP WSTG-CONF-04/05 and WSTG-INFO-03: ask the target's origin
 * for well-known files that should never be public (.env, .git, logs, phpinfo).
 *
 * A file only counts as exposed when the response is 200 AND its body has the
 * file's signature, because many sites (SPAs, Vercel) answer every path with
 * 200 and their index page. Read-only: one GET per path, no redirects.
 */
class CheckSensitiveFilesTool implements Tool
{
    /**
     * Path => [what it is, body signature regex].
     */
    private const FILES = [
        '/.env' => ['environment file with secrets', '/^(APP_KEY|DB_[A-Z_]+|[A-Z_]*SECRET[A-Z_]*|[A-Z_]*PASSWORD)=/m'],
        '/.git/HEAD' => ['git repository metadata', '#^(ref: refs/|[0-9a-f]{40}$)#m'],
        '/.git/config' => ['git repository config', '/^\[core\]/m'],
        '/storage/logs/laravel.log' => ['Laravel application log', '/^\[\d{4}-\d{2}-\d{2}[ T][\d:]+[^\]]*\] \w+\.(ERROR|WARNING|INFO|DEBUG)/m'],
        '/phpinfo.php' => ['phpinfo() output', '/<title>PHP \d[^<]*phpinfo\(\)<\/title>|PHP Version \d+\.\d+/'],
        '/.DS_Store' => ['macOS folder index', '/^\x00\x00\x00\x01Bud1/'],
        '/server-status' => ['Apache server-status page', '/Apache Server Status/'],
    ];

    public function name(): string
    {
        return 'check_sensitive_files';
    }

    public function description(): string
    {
        return 'Check whether well-known sensitive files (.env, .git/HEAD, .git/config, Laravel logs, '
            .'phpinfo.php, .DS_Store, server-status) are publicly readable on the target. '
            .'Only target-scope hosts can be checked.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Absolute http(s) URL on the target; its origin (scheme, host, port) is checked.',
                ],
            ],
            'required' => ['url'],
        ];
    }

    public function handle(array $arguments, ToolContext $context): string
    {
        $data = Validator::make($arguments, [
            'url' => ['bail', 'required', 'url:http,https', function (string $attribute, mixed $value, Closure $fail) use ($context): void {
                if (! is_string($value) || ! $context->allowsUrl($value)) {
                    $fail('The :attribute is outside the scope of this target.');
                }
            }],
        ])->validate();

        $parts = parse_url($data['url']);
        $origin = "{$parts['scheme']}://{$parts['host']}".(isset($parts['port']) ? ":{$parts['port']}" : '');
        $lines = ["Sensitive files for {$origin}:"];

        foreach (self::FILES as $path => [$label, $signature]) {
            try {
                // 7 paths x (5 s + 8 s) stays under ToolExecutor::MAX_SECONDS.
                $response = Http::connectTimeout(5)->timeout(8)->withoutRedirecting()->get($origin.$path);
            } catch (ConnectionException $e) {
                return "Could not connect to {$origin}: {$e->getMessage()}";
            }

            $lines[] = $response->status() === 200 && preg_match($signature, $response->body())
                ? "  - [exposed-sensitive-file] {$path}: EXPOSED ({$label})"
                : "  - {$path}: not exposed (HTTP {$response->status()})";
        }

        return implode("\n", $lines);
    }
}
