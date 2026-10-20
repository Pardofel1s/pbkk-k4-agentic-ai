<?php

namespace App\Tools;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * DAST tool (Runtime Testing pillar): probe a URL's query parameters for
 * reflected XSS.
 *
 * For each parameter it sends a UNIQUE BENIGN MARKER containing HTML-significant
 * characters and checks whether the marker comes back in the page UNENCODED. It
 * never executes script; a raw reflection only signals that the page likely
 * fails to encode output. Limited to the target's scope.
 */
class TestReflectedXssTool implements Tool
{
    private const MAX_PARAMS = 8;

    public function name(): string
    {
        return 'test_reflected_xss';
    }

    public function description(): string
    {
        return 'Probe a URL\'s query parameters for reflected XSS by injecting a '
            .'benign marker and checking if it is reflected unencoded. Only '
            .'target-scope hosts can be tested.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Absolute http(s) URL, ideally with query parameters to test.',
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

        parse_str((string) parse_url($data['url'], PHP_URL_QUERY), $params);

        // No query parameters to test: probe a single injected one so the tool still runs.
        if ($params === []) {
            $params = ['q' => ''];
        }

        $results = [];

        foreach (array_slice(array_keys($params), 0, self::MAX_PARAMS) as $param) {
            $marker = 'cxss'.bin2hex(random_bytes(4));
            $payload = $marker.'"><svg/onload=1>';
            $probeUrl = $this->withParam($data['url'], $params, (string) $param, $payload);

            if (! $context->allowsUrl($probeUrl)) {
                continue;
            }

            try {
                $body = Http::connectTimeout(5)->timeout(10)->withoutRedirecting()->get($probeUrl)->body();
            } catch (ConnectionException $e) {
                return "Could not connect while testing {$data['url']}: {$e->getMessage()}";
            }

            $results[(string) $param] = $this->classify($body, $payload, $marker);
        }

        return $this->report($data['url'], $results);
    }

    /**
     * Rebuild the URL with one parameter replaced by the payload.
     *
     * @param  array<string, mixed>  $params
     */
    private function withParam(string $url, array $params, string $param, string $payload): string
    {
        $parts = parse_url($url);
        $params[$param] = $payload;

        $origin = "{$parts['scheme']}://{$parts['host']}".(isset($parts['port']) ? ":{$parts['port']}" : '');

        return $origin.($parts['path'] ?? '/').'?'.http_build_query($params);
    }

    private function classify(string $body, string $payload, string $marker): string
    {
        if (str_contains($body, $payload)) {
            return 'VULNERABLE: payload reflected unencoded';
        }

        if (str_contains($body, $marker)) {
            return 'reflected but encoded/sanitised (likely safe)';
        }

        return 'not reflected';
    }

    /**
     * @param  array<string, string>  $results
     */
    private function report(string $url, array $results): string
    {
        $lines = ["Reflected XSS probe for {$url}:"];

        foreach ($results as $param => $verdict) {
            $lines[] = "  - parameter \"{$param}\": {$verdict}";
        }

        if (! str_contains(implode("\n", $results), 'VULNERABLE')) {
            $lines[] = 'No unencoded reflection found in the tested parameters.';
        }

        return implode("\n", $lines);
    }
}
