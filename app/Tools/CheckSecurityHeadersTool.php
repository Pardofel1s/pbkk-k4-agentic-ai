<?php

namespace App\Tools;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * First DAST tool: fetch a target URL and report which common HTTP security
 * headers are present or missing. Read-only (a single GET), so it fits the
 * "only test what you are allowed to" ethic of the project.
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

    public function name(): string
    {
        return 'check_security_headers';
    }

    public function description(): string
    {
        return 'Fetch a URL and report which common HTTP security headers '
            .'(HSTS, CSP, X-Frame-Options, etc.) are present or missing.';
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
            'url' => ['required', 'url:http,https'],
        ])->validate();

        $response = Http::connectTimeout(5)
            ->timeout(15)
            ->get($data['url']);

        $present = [];
        $missing = [];

        foreach (self::SECURITY_HEADERS as $header => $purpose) {
            if ($response->hasHeader($header)) {
                $present[] = "{$header}: {$response->header($header)}";
            } else {
                $missing[] = "{$header} ({$purpose})";
            }
        }

        return $this->report($data['url'], $response->status(), $present, $missing);
    }

    /**
     * @param  list<string>  $present
     * @param  list<string>  $missing
     */
    private function report(string $url, int $status, array $present, array $missing): string
    {
        $lines = ["Security headers for {$url} (HTTP {$status}):", ''];

        $lines[] = 'Present:';
        $lines[] = $present === [] ? '  (none)' : '  - '.implode("\n  - ", $present);

        $lines[] = '';
        $lines[] = 'Missing:';
        $lines[] = $missing === [] ? '  (none — all checked headers set)' : '  - '.implode("\n  - ", $missing);

        return implode("\n", $lines);
    }
}
