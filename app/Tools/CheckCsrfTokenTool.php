<?php

namespace App\Tools;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * DAST tool (Runtime Testing pillar): fetch a page and check whether each
 * state-changing form (method POST) carries an anti-CSRF token. A POST form
 * without a token can be submitted by another site on the user's behalf (CSRF).
 *
 * Read-only (a single GET) and limited to the target's scope.
 */
class CheckCsrfTokenTool implements Tool
{
    /**
     * A hidden input is treated as a CSRF token when its name contains one of
     * these fragments (Laravel `_token`, Rails `authenticity_token`, etc.).
     *
     * @var list<string>
     */
    private const TOKEN_HINTS = ['csrf', 'token', 'authenticity', 'nonce'];

    public function name(): string
    {
        return 'check_csrf_token';
    }

    public function description(): string
    {
        return 'Fetch a page and report which POST forms are missing an anti-CSRF '
            .'token field. Only target-scope hosts can be checked.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Absolute http(s) URL of the page whose forms to inspect.',
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
            $response = Http::connectTimeout(5)->timeout(15)->get($data['url']);
        } catch (ConnectionException $e) {
            return "Could not connect to {$data['url']}: {$e->getMessage()}";
        }

        return $this->report($data['url'], $response->body());
    }

    private function report(string $url, string $html): string
    {
        // Each <form ...>...</form> block; "s" lets . span newlines, "*?" is non-greedy.
        preg_match_all('#<form\b([^>]*)>(.*?)</form>#is', $html, $forms, PREG_SET_ORDER);

        $postForms = array_filter($forms, fn (array $form): bool => $this->isPost($form[1]));

        if ($postForms === []) {
            return "No POST forms found on {$url}.";
        }

        $withToken = 0;
        $missing = [];

        foreach ($postForms as $i => $form) {
            if ($this->hasToken($form[2])) {
                $withToken++;
            } else {
                $missing[] = '  - form #'.($i + 1).' action="'.$this->action($form[1]).'"';
            }
        }

        $total = count($postForms);
        $lines = ["CSRF token check for {$url}: {$total} POST form(s), {$withToken} with a token."];

        if ($missing === []) {
            $lines[] = 'All POST forms carry a token field.';
        } else {
            $lines[] = 'POST forms without a token field:';
            $lines = array_merge($lines, $missing);
        }

        return implode("\n", $lines);
    }

    private function isPost(string $attributes): bool
    {
        return (bool) preg_match('/\bmethod\s*=\s*["\']?\s*post\b/i', $attributes);
    }

    private function hasToken(string $formBody): bool
    {
        if (! preg_match_all('/<input\b[^>]*name\s*=\s*["\']?([^"\'\s>]+)/i', $formBody, $names)) {
            return false;
        }

        foreach ($names[1] as $name) {
            foreach (self::TOKEN_HINTS as $hint) {
                if (str_contains(strtolower($name), $hint)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function action(string $attributes): string
    {
        return preg_match('/\baction\s*=\s*["\']?([^"\'\s>]*)/i', $attributes, $m) ? $m[1] : '(self)';
    }
}
