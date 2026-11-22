<?php

namespace App\Services\Scan;

use App\Models\VulnerabilityType;

/**
 * Turns the text a DAST tool returns into findings, so a scan gives the same
 * result every time without an LLM. The patterns follow the report format of
 * each tool in app/Tools; change both together.
 */
class FindingRules
{
    /**
     * Vulnerability types the rules can raise, keyed by a short slug.
     */
    public const TYPES = [
        'missing-csp' => ['Content-Security-Policy tidak dipasang', 'CWE-693', 'A05:2021 Security Misconfiguration', 'medium',
            'Tanpa CSP, browser tidak membatasi sumber skrip sehingga dampak XSS menjadi lebih besar.',
            'Tetapkan header Content-Security-Policy yang membatasi sumber skrip; uji dulu dengan Content-Security-Policy-Report-Only.'],
        'missing-hsts' => ['Strict-Transport-Security tidak dipasang', 'CWE-319', 'A02:2021 Cryptographic Failures', 'medium',
            'Tanpa HSTS, pengguna bisa diturunkan ke HTTP biasa (SSL stripping) pada kunjungan berikutnya.',
            'Kirim Strict-Transport-Security: max-age=31536000; includeSubDomains pada setiap respons HTTPS.'],
        'missing-frame-options' => ['Perlindungan clickjacking tidak ada', 'CWE-1021', 'A05:2021 Security Misconfiguration', 'medium',
            'Halaman bisa dimuat di dalam iframe situs lain sehingga pengguna bisa dijebak mengklik tombol tersembunyi.',
            'Kirim X-Frame-Options: DENY atau CSP frame-ancestors \'none\'.'],
        'missing-content-type-options' => ['X-Content-Type-Options tidak dipasang', 'CWE-693', 'A05:2021 Security Misconfiguration', 'low',
            'Browser boleh menebak tipe konten (MIME sniffing) sehingga file unggahan bisa dieksekusi sebagai skrip.',
            'Kirim X-Content-Type-Options: nosniff.'],
        'missing-referrer-policy' => ['Referrer-Policy tidak dipasang', 'CWE-200', 'A01:2021 Broken Access Control', 'low',
            'URL lengkap (termasuk token di query string) bisa bocor ke situs lain lewat header Referer.',
            'Kirim Referrer-Policy: strict-origin-when-cross-origin atau yang lebih ketat.'],
        'missing-permissions-policy' => ['Permissions-Policy tidak dipasang', 'CWE-693', 'A05:2021 Security Misconfiguration', 'low',
            'Fitur browser sensitif (kamera, mikrofon, lokasi) tidak dibatasi untuk halaman dan iframe.',
            'Kirim Permissions-Policy yang mematikan fitur yang tidak dipakai, mis. camera=(), microphone=(), geolocation=().'],
        'cookie-without-secure' => ['Cookie tanpa atribut Secure', 'CWE-614', 'A05:2021 Security Misconfiguration', 'medium',
            'Cookie bisa terkirim lewat HTTP biasa dan disadap di jaringan.',
            'Set atribut Secure pada cookie dan layani aplikasi hanya lewat HTTPS.'],
        'cookie-without-httponly' => ['Cookie tanpa atribut HttpOnly', 'CWE-1004', 'A05:2021 Security Misconfiguration', 'medium',
            'Skrip di halaman bisa membaca cookie, sehingga XSS bisa mencuri sesi.',
            'Set atribut HttpOnly pada cookie yang tidak perlu dibaca JavaScript, terutama cookie sesi.'],
        'cookie-without-samesite' => ['Cookie tanpa atribut SameSite', 'CWE-1275', 'A01:2021 Broken Access Control', 'low',
            'Cookie ikut terkirim pada permintaan lintas situs sehingga memperbesar risiko CSRF.',
            'Set SameSite=Lax (atau Strict) pada cookie.'],
        'form-without-csrf-token' => ['Form POST tanpa token CSRF', 'CWE-352', 'A01:2021 Broken Access Control', 'high',
            'Situs lain bisa mengirim form ini atas nama pengguna yang sedang login (Cross-Site Request Forgery).',
            'Sisipkan token CSRF per sesi di setiap form POST dan validasi di server.'],
        'reflected-xss' => ['Reflected Cross-Site Scripting', 'CWE-79', 'A03:2021 Injection', 'high',
            'Input dari parameter URL dipantulkan ke halaman tanpa encoding sehingga penyerang bisa menjalankan skrip di browser korban.',
            'Encode output sesuai konteks (HTML, atribut, JS) dan validasi input; tambahkan CSP sebagai lapisan kedua.'],
    ];

    private const HEADER_TYPES = [
        'Content-Security-Policy' => 'missing-csp',
        'Strict-Transport-Security' => 'missing-hsts',
        'X-Frame-Options' => 'missing-frame-options',
        'X-Content-Type-Options' => 'missing-content-type-options',
        'Referrer-Policy' => 'missing-referrer-policy',
        'Permissions-Policy' => 'missing-permissions-policy',
    ];

    private const COOKIE_TYPES = [
        'Secure' => 'cookie-without-secure',
        'HttpOnly' => 'cookie-without-httponly',
        'SameSite' => 'cookie-without-samesite',
    ];

    /**
     * Findings in a tool's output. scan_open_ports raises none: an open port is
     * only a risk in context, so it is left to the analyst and the AI analysis.
     *
     * @return list<array{type: string, url: string, parameter: ?string, proof: string}>
     */
    public function extract(string $tool, string $output, string $url): array
    {
        // The URL the tool ended on (after redirects) ends its first line, e.g. "... for https://x/login (HTTP 200):".
        $checked = preg_match('#\bfor (\S+?)(?: \(HTTP \d+\))?:$#m', $output, $m) ? $m[1] : $url;

        return match ($tool) {
            'check_security_headers' => $this->missingHeaders($output, $checked),
            'check_cookie_flags' => $this->cookieFlags($output, $checked),
            'check_csrf_token' => $this->formsWithoutToken($output, $checked),
            'test_reflected_xss' => $this->reflectedXss($output, $checked),
            default => [],
        };
    }

    /**
     * The catalogue row for a type slug, created the first time it is needed.
     */
    public function type(string $slug): VulnerabilityType
    {
        [$nama, $cwe, $owasp, , $deskripsi, $rekomendasi] = self::TYPES[$slug];

        return VulnerabilityType::query()->firstOrCreate(
            ['nama' => $nama],
            ['kode_cwe' => $cwe, 'kategori_owasp' => $owasp, 'deskripsi' => $deskripsi, 'rekomendasi' => $rekomendasi],
        );
    }

    public function severity(string $slug): string
    {
        return self::TYPES[$slug][3];
    }

    private function missingHeaders(string $output, string $url): array
    {
        $missing = str_contains($output, "\nMissing:\n") ? explode("\nMissing:\n", $output, 2)[1] : '';
        $findings = [];

        foreach (self::HEADER_TYPES as $header => $slug) {
            // HSTS only means something on HTTPS.
            if ($slug === 'missing-hsts' && ! str_starts_with($url, 'https://')) {
                continue;
            }

            if (str_contains($missing, "- {$header} (")) {
                $findings[] = $this->finding($slug, $url, $header, "{$header}: [tidak ditemukan]\n\n{$output}");
            }
        }

        return $findings;
    }

    private function cookieFlags(string $output, string $url): array
    {
        preg_match_all('/^  - (.+?): missing (.+)$/m', $output, $lines, PREG_SET_ORDER);
        $findings = [];

        foreach ($lines as [$line, $cookie, $flags]) {
            foreach (explode(', ', $flags) as $flag) {
                if (isset(self::COOKIE_TYPES[$flag])) {
                    $findings[] = $this->finding(self::COOKIE_TYPES[$flag], $url, $cookie, trim($line));
                }
            }
        }

        return $findings;
    }

    private function formsWithoutToken(string $output, string $url): array
    {
        preg_match_all('/^  - (form #\d+ action="[^"]*")$/m', $output, $forms);

        return array_map(fn (string $form): array => $this->finding('form-without-csrf-token', $url, $form, $output), $forms[1]);
    }

    private function reflectedXss(string $output, string $url): array
    {
        preg_match_all('/^  - parameter "(.+?)": VULNERABLE/m', $output, $params);

        return array_map(fn (string $param): array => $this->finding('reflected-xss', $url, $param, $output), $params[1]);
    }

    private function finding(string $slug, string $url, ?string $parameter, string $proof): array
    {
        return ['type' => $slug, 'url' => $url, 'parameter' => $parameter, 'proof' => $proof];
    }
}
