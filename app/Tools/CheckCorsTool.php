<?php

namespace App\Tools;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * DAST tool for OWASP WSTG-CLNT-07: send a request with a foreign Origin and see
 * whether the CORS headers trust it. Reflecting any Origin lets another site
 * read the responses; with Allow-Credentials it reads them as the logged-in user.
 *
 * Read-only: a single GET to the given URL, no redirects.
 */
class CheckCorsTool implements Tool
{
    /**
     * A reserved domain (RFC 2606), so it can never be a real trusted origin.
     */
    public const PROBE_ORIGIN = 'https://jejaksa-cors-probe.example';

    public function name(): string
    {
        return 'check_cors';
    }

    public function description(): string
    {
        return 'Send a request with a foreign Origin header and report whether the CORS headers '
            .'(Access-Control-Allow-Origin/-Credentials) trust it. Only target-scope hosts can be checked.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Absolute http(s) URL to request, ideally an API endpoint.',
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

        try {
            $response = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()
                ->withHeaders(['Origin' => self::PROBE_ORIGIN])
                ->get($data['url']);
        } catch (ConnectionException $e) {
            return "Could not connect to {$data['url']}: {$e->getMessage()}";
        }

        $allowOrigin = $response->header('Access-Control-Allow-Origin');
        $credentials = strtolower($response->header('Access-Control-Allow-Credentials')) === 'true';

        $lines = [
            "CORS policy for {$data['url']} (HTTP {$response->status()}):",
            '  Origin sent: '.self::PROBE_ORIGIN,
            '  Access-Control-Allow-Origin: '.($allowOrigin === '' ? '[not set]' : $allowOrigin),
            '  Access-Control-Allow-Credentials: '.($credentials ? 'true' : '[not set]'),
        ];

        $lines[] = match (true) {
            $allowOrigin === self::PROBE_ORIGIN && $credentials => '  - [cors-any-origin-credentials] Access-Control-Allow-Origin: VULNERABLE, any origin is trusted with credentials',
            $allowOrigin === self::PROBE_ORIGIN => '  - [cors-any-origin] Access-Control-Allow-Origin: VULNERABLE, any origin is trusted',
            $allowOrigin === 'null' => '  - [cors-any-origin] Access-Control-Allow-Origin: VULNERABLE, the "null" origin (sandboxed pages) is trusted',
            $allowOrigin === '*' => 'Verdict: public wildcard (*); browsers never send cookies with it, so only public data is exposed.',
            default => 'Verdict: the foreign origin is not trusted.',
        };

        return implode("\n", $lines);
    }
}
