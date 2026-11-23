<?php

namespace App\Tools;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * DAST tool for OWASP WSTG-INFO-02 and WSTG-ERRH-01/02: look for headers that
 * reveal software versions, and for debug pages or stack traces on the page and
 * on a path that does not exist. Both help an attacker pick a known exploit.
 *
 * Read-only: two GETs on the target's origin, no redirects.
 */
class CheckInfoDisclosureTool implements Tool
{
    public const PROBE_PATH = '/jejaksa-not-found-probe';

    private const VERSION_HEADERS = ['Server', 'X-Powered-By', 'X-AspNet-Version', 'X-AspNetMvc-Version', 'X-Generator'];

    /**
     * Body text => what it gives away.
     */
    private const DEBUG_SIGNATURES = [
        'Stack trace:' => 'PHP stack trace',
        'SQLSTATE[' => 'database error message',
        'vendor/laravel/framework' => 'Laravel framework paths',
        'Whoops, looks like something went wrong' => 'Whoops debug page',
        'Traceback (most recent call last)' => 'Python traceback',
        'Exception in thread "' => 'Java stack trace',
        'Server Error in \'/\' Application' => 'ASP.NET error page',
        '<b>Fatal error</b>:' => 'PHP fatal error',
        '<b>Warning</b>:' => 'PHP warning',
    ];

    public function name(): string
    {
        return 'check_info_disclosure';
    }

    public function description(): string
    {
        return 'Report response headers that reveal software versions (Server, X-Powered-By, ...) and '
            .'debug pages or stack traces on the page and on a non-existent path. Only target-scope hosts can be checked.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Absolute http(s) URL of the page to inspect; a missing path on its origin is also requested.',
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
        $probe = "{$parts['scheme']}://{$parts['host']}".(isset($parts['port']) ? ":{$parts['port']}" : '').self::PROBE_PATH;

        try {
            $page = $this->fetch($data['url']);
            $missing = $this->fetch($probe);
        } catch (ConnectionException $e) {
            return "Could not connect to {$data['url']}: {$e->getMessage()}";
        }

        $lines = ["Information disclosure for {$data['url']} (HTTP {$page->status()}):"];

        foreach (self::VERSION_HEADERS as $header) {
            $value = $page->header($header);

            // A bare product name ("nginx", "cloudflare") is harmless; a version number is not.
            if (preg_match('/\d+\.\d+/', $value)) {
                $lines[] = "  - [version-disclosure] {$header}: reveals \"{$value}\"";
            }
        }

        foreach ([$data['url'] => $page, $probe => $missing] as $url => $response) {
            foreach (self::DEBUG_SIGNATURES as $needle => $label) {
                if (str_contains($response->body(), $needle)) {
                    $lines[] = "  - [debug-error-page] {$url}: {$label} (\"{$needle}\", HTTP {$response->status()})";
                    break;
                }
            }
        }

        if (count($lines) === 1) {
            $lines[] = 'No version headers or debug output found.';
        }

        return implode("\n", $lines);
    }

    private function fetch(string $url): Response
    {
        return Http::connectTimeout(5)->timeout(15)->withoutRedirecting()->get($url);
    }
}
