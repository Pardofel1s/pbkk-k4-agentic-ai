<?php

namespace App\Tools;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * DAST tool (Runtime Testing pillar): fetch a URL and check whether the cookies
 * it sets carry the Secure, HttpOnly and SameSite flags. Missing flags make
 * session cookies easier to steal (XSS) or send cross-site (CSRF).
 *
 * Read-only (a single GET) and limited to the target's scope.
 */
class CheckCookieFlagsTool implements Tool
{
    public function name(): string
    {
        return 'check_cookie_flags';
    }

    public function description(): string
    {
        return 'Fetch a URL and report whether each cookie it sets has the Secure, '
            .'HttpOnly and SameSite flags. Only target-scope hosts can be checked.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Absolute http(s) URL whose Set-Cookie headers to inspect.',
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
            $response = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()->get($data['url']);
        } catch (ConnectionException $e) {
            return "Could not connect to {$data['url']}: {$e->getMessage()}";
        }

        return $this->report($data['url'], $this->setCookieLines($response->headers()));
    }

    /**
     * Raw Set-Cookie header lines, matching the header name case-insensitively.
     *
     * @param  array<string, array<int, string>>  $headers
     * @return list<string>
     */
    private function setCookieLines(array $headers): array
    {
        foreach ($headers as $name => $values) {
            if (strtolower($name) === 'set-cookie') {
                return array_values($values);
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $cookies
     */
    private function report(string $url, array $cookies): string
    {
        if ($cookies === []) {
            return "No cookies are set by {$url}.";
        }

        $lines = ["Cookie flags for {$url}:"];

        foreach ($cookies as $cookie) {
            $cookieName = trim(explode('=', $cookie, 2)[0]);
            $missing = array_values(array_filter(
                ['Secure', 'HttpOnly', 'SameSite'],
                fn (string $flag): bool => ! $this->hasFlag($cookie, $flag),
            ));

            $lines[] = $missing === []
                ? "  - {$cookieName}: Secure, HttpOnly, SameSite all set"
                : "  - {$cookieName}: missing ".implode(', ', $missing);
        }

        return implode("\n", $lines);
    }

    private function hasFlag(string $cookie, string $flag): bool
    {
        // Flags appear as "; Secure", "; HttpOnly" or "; SameSite=Lax".
        return (bool) preg_match('/;\s*'.preg_quote($flag, '/').'\b/i', $cookie);
    }
}
