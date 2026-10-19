<?php

namespace App\Tools;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * First DAST tool: fetch a target URL and report which common HTTP security
 * headers are present or missing. Read-only (GET only) and limited to the
 * hosts in config('llm.allowed_hosts'), so it only touches targets we are
 * allowed to test.
 */
class CheckSecurityHeadersTool implements Tool
{
    /**
     * Headers we grade, with a short note on what each one mitigates.
     */
    private const SECURITY_HEADERS = [
        'Strict-Transport-Security' => 'forces HTTPS (HSTS)',
        'Content-Security-Policy' => 'limits script/content sources (XSS)',
        'X-Frame-Options' => 'blocks clickjacking via framing',
        'X-Content-Type-Options' => 'stops MIME-type sniffing',
        'Referrer-Policy' => 'controls the Referer header',
        'Permissions-Policy' => 'restricts browser features/APIs',
    ];

    private const MAX_REDIRECTS = 5;

    public function name(): string
    {
        return 'check_security_headers';
    }

    public function description(): string
    {
        return 'Fetch a URL and report which common HTTP security headers '
            .'(HSTS, CSP, X-Frame-Options, etc.) are present or missing. '
            .'Only allowed target hosts can be checked.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Absolute http(s) URL of the page to inspect.',
                ],
            ],
            'required' => ['url'],
        ];
    }

    /**
     * Arguments come straight from the LLM, so validate before making a request.
     */
    public function handle(array $arguments): string
    {
        $data = Validator::make($arguments, [
            'url' => ['bail', 'required', 'url:http,https', function (string $attribute, mixed $value, Closure $fail) {
                if (! $this->isAllowed($value)) {
                    $fail('The :attribute host is not in the allowed target list.');
                }
            }],
        ])->validate();

        $url = $data['url'];
        $redirects = [];

        // Follow redirects ourselves so every hop is checked against the allowlist.
        for ($hop = 0; ; $hop++) {
            try {
                $response = $this->fetch($url);
            } catch (ConnectionException $e) {
                return "Could not connect to {$url}: {$e->getMessage()}";
            }

            $location = $response->header('Location');

            if (! $response->redirect() || $location === '') {
                break;
            }

            if ($hop >= self::MAX_REDIRECTS) {
                return "Stopped at {$url}: more than ".self::MAX_REDIRECTS.' redirects.';
            }

            $next = $this->resolve($url, $location);

            if (! $this->isAllowed($next)) {
                return "{$url} redirects to {$next}, which is not in the allowed target list, so it was not requested.";
            }

            $redirects[] = "{$url} -> {$next}";
            $url = $next;
        }

        return $this->report($url, $response, $redirects);
    }

    private function fetch(string $url): Response
    {
        return Http::connectTimeout(5)
            ->timeout(15)
            ->withoutRedirecting()
            ->get($url);
    }

    /**
     * Only http(s) URLs whose host is in config('llm.allowed_hosts').
     */
    private function isAllowed(mixed $url): bool
    {
        if (! is_string($url)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        $allowed = array_map('strtolower', config('llm.allowed_hosts', []));

        return in_array($scheme, ['http', 'https'], true) && in_array($host, $allowed, true);
    }

    /**
     * Turn a (possibly relative) Location header into an absolute URL.
     */
    private function resolve(string $base, string $location): string
    {
        // Already absolute (any scheme; isAllowed() rejects non-http ones).
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'];

        if (str_starts_with($location, '//')) {
            return "{$scheme}:{$location}";
        }

        $origin = "{$scheme}://{$parts['host']}".(isset($parts['port']) ? ":{$parts['port']}" : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/') ?: '/';

        return $origin.$directory.$location;
    }

    /**
     * @param  list<string>  $redirects
     */
    private function report(string $url, Response $response, array $redirects): string
    {
        $present = [];
        $missing = [];

        foreach (self::SECURITY_HEADERS as $header => $purpose) {
            if ($response->hasHeader($header)) {
                $present[] = "{$header}: {$response->header($header)}";
            } else {
                $missing[] = "{$header} ({$purpose})";
            }
        }

        $lines = ["Security headers for {$url} (HTTP {$response->status()}):", ''];

        if ($redirects !== []) {
            $lines[] = 'Redirects followed:';
            $lines[] = '  - '.implode("\n  - ", $redirects);
            $lines[] = '';
        }

        $lines[] = 'Present:';
        $lines[] = $present === [] ? '  (none)' : '  - '.implode("\n  - ", $present);

        $lines[] = '';
        $lines[] = 'Missing:';
        $lines[] = $missing === [] ? '  (none â€” all checked headers set)' : '  - '.implode("\n  - ", $missing);

        return implode("\n", $lines);
    }
}
