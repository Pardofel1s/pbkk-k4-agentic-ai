<?php

namespace App\Tools;

use Closure;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * DAST tool for OWASP WSTG-CRYP-01: open one TLS handshake to an HTTPS target
 * and check the negotiated protocol and the certificate (expiry, self-signed,
 * host name). Sends no HTTP request at all.
 *
 * shortcut: it reports the protocol the server prefers and does not probe each
 * old version or verify the CA chain; add both if targets need a full TLS audit.
 */
class CheckTlsTool implements Tool
{
    private const WEAK_PROTOCOLS = ['SSLv2', 'SSLv3', 'TLSv1', 'TLSv1.0', 'TLSv1.1'];

    public function name(): string
    {
        return 'check_tls';
    }

    public function description(): string
    {
        return 'Check the TLS setup of an https URL: negotiated protocol version and the certificate '
            .'(expired, self-signed, wrong host name). Only target-scope hosts can be checked.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Absolute https URL of the target; plain http has no TLS to check.',
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

        if (strtolower($parts['scheme']) !== 'https') {
            return "{$data['url']} uses plain HTTP, so there is no TLS to check.";
        }

        $host = trim(strtolower($parts['host']), '[]');

        try {
            ['protocol' => $protocol, 'certificate' => $cert] = $this->handshake($host, $parts['port'] ?? 443);
        } catch (RuntimeException $e) {
            return "Could not complete a TLS handshake with {$data['url']}: {$e->getMessage()}";
        }

        $validTo = (int) $cert['validTo_time_t'];
        $daysLeft = (int) floor(($validTo - now()->timestamp) / 86400);
        $names = $this->names($cert);

        $lines = [
            "TLS for {$data['url']}:",
            "  Protocol: {$protocol}",
            '  Certificate: subject '.$this->label($cert['subject']).', issuer '.$this->label($cert['issuer'])
                .', valid until '.date('Y-m-d', $validTo)." ({$daysLeft} days left), names ".implode(', ', $names),
        ];

        if (in_array($protocol, self::WEAK_PROTOCOLS, true)) {
            $lines[] = "  - [tls-weak-protocol] protocol: {$protocol} is deprecated (RFC 8996)";
        }

        if ($validTo < now()->timestamp) {
            $lines[] = '  - [tls-certificate-expired] certificate: EXPIRED on '.date('Y-m-d', $validTo);
        }

        if ($cert['subject'] == $cert['issuer']) {
            $lines[] = '  - [tls-certificate-self-signed] certificate: self-signed, browsers will not trust it';
        }

        if (! collect($names)->contains(fn (string $name): bool => $this->matchesHost(strtolower($name), $host))) {
            $lines[] = "  - [tls-hostname-mismatch] certificate: issued for ".implode(', ', $names).", not {$host}";
        }

        return implode("\n", $lines);
    }

    /**
     * One handshake without verification, so a bad certificate can still be read.
     *
     * @return array{protocol: string, certificate: array<string, mixed>}
     */
    protected function handshake(string $host, int $port): array
    {
        $stream = @stream_socket_client("ssl://{$host}:{$port}", $errno, $error, 10, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'peer_name' => $host,
        ]]));

        if ($stream === false) {
            throw new RuntimeException($error !== '' ? $error : "error {$errno}");
        }

        $certificate = openssl_x509_parse(stream_context_get_params($stream)['options']['ssl']['peer_certificate']);
        $protocol = stream_get_meta_data($stream)['crypto']['protocol'] ?? 'unknown';
        fclose($stream);

        return ['protocol' => $protocol, 'certificate' => $certificate ?: throw new RuntimeException('unreadable certificate')];
    }

    /**
     * DNS names the certificate is valid for: its subjectAltName, else its CN.
     *
     * @param  array<string, mixed>  $cert
     * @return list<string>
     */
    private function names(array $cert): array
    {
        preg_match_all('/DNS:([^,\s]+)/', $cert['extensions']['subjectAltName'] ?? '', $m);

        return $m[1] ?: array_values(array_filter((array) ($cert['subject']['CN'] ?? [])));
    }

    private function matchesHost(string $name, string $host): bool
    {
        // A wildcard covers exactly one label: *.app.test matches a.app.test, not app.test or a.b.app.test.
        if (str_starts_with($name, '*.')) {
            $suffix = substr($name, 1);

            return str_ends_with($host, $suffix) && ! str_contains(substr($host, 0, -strlen($suffix)), '.');
        }

        return $name === $host;
    }

    /**
     * @param  array<string, mixed>  $dn
     */
    private function label(array $dn): string
    {
        $value = $dn['CN'] ?? $dn['O'] ?? '?';

        return is_array($value) ? implode('/', $value) : (string) $value;
    }
}
