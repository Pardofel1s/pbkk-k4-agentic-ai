<?php

namespace App\Services\Agent;

use App\Models\Target;
use App\Models\TargetScopeRule;
use Illuminate\Support\Str;

/**
 * Decides whether a tool may send a request to a URL while testing a target.
 *
 * A URL is in scope only when all of these hold (docs/rbac.md, team rules):
 * - the scheme is http or https;
 * - the port is the target's base_url port (80/443 when none is written), so a
 *   target on localhost:8080 cannot reach other services on localhost;
 * - the host is in config('llm.allowed_hosts') (hosts we own or may test);
 * - the host is the target's own base_url host or matches an `include` rule;
 * - the host matches no `exclude` rule (exclude always wins).
 */
class TargetScope
{
    public function allows(Target $target, string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = $this->host($url);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($this->port($url) !== $this->port($target->base_url)) {
            return false;
        }

        if (! in_array($host, array_map('strtolower', config('llm.allowed_hosts', [])), true)) {
            return false;
        }

        $rules = $target->loadMissing('targetScopeRules')->targetScopeRules;

        if ($rules->contains(fn (TargetScopeRule $rule): bool => $rule->tipe === 'exclude' && $this->matches($rule, $host))) {
            return false;
        }

        return $host === $this->host($target->base_url)
            || $rules->contains(fn (TargetScopeRule $rule): bool => $rule->tipe === 'include' && $this->matches($rule, $host));
    }

    /**
     * Lower-cased host of a URL, without the brackets of an IPv6 literal.
     */
    private function host(string $url): string
    {
        return strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
    }

    /**
     * The port a request to this URL really uses: the written one, else the
     * scheme's default (443 for https, 80 otherwise).
     */
    private function port(string $url): int
    {
        $port = parse_url($url, PHP_URL_PORT);

        if (is_int($port)) {
            return $port;
        }

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80;
    }

    /**
     * `pola_domain` is a host pattern such as `app.test` or `*.app.test`.
     */
    private function matches(TargetScopeRule $rule, string $host): bool
    {
        return Str::is(strtolower($rule->pola_domain), $host);
    }
}
