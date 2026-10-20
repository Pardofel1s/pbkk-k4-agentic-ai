<?php

namespace App\Livewire;

class DemoWorkspace
{
    /** Demo state is session-scoped and never dispatches jobs or contacts a target. */
    public static function read(): array
    {
        abort_unless(auth()->check(), 403);

        $state = session()->get(self::key());
        if ($state === null) {
            $state = self::fixtures();
            foreach ($state['reports'] as &$report) {
                $scan = $state['scans'][$report['scan']];
                $report['target_snapshot'] = $state['targets'][$scan['target']];
                $report['findings_snapshot'] = collect($state['findings'])->where('scan', $report['scan'])->all();
            }
            unset($report);
            session()->put(self::key(), $state);
        }

        return $state;
    }

    public static function write(array $state): void
    {
        abort_unless(auth()->check(), 403);
        session()->put(self::key(), $state);
    }

    public static function find(string $collection, string $id): array
    {
        return self::read()[$collection][$id] ?? abort(404);
    }

    public static function key(): string
    {
        return 'jejaksa.demo.'.auth()->id();
    }

    public static function fixtures(): array
    {
        return [
            'targets' => [
                't1' => ['id' => 't1','name' => 'Fintech Sandbox','url' => 'https://staging.example.test','environment' => 'Staging','description' => 'Lingkungan pengujian aplikasi fintech. Semua data pada workspace ini merupakan simulasi.','scope' => '/','excluded' => "/logout\n/payments/confirm",'authorized' => true,'accounts' => [['name' => 'Analis demo','role' => 'Nasabah','username' => 'nasabah-demo']]],
                't2' => ['id' => 't2','name' => 'Merchant Portal','url' => 'https://merchant.example.test','environment' => 'Development','description' => 'Portal mitra untuk demonstrasi alur pengujian.','scope' => '/portal','excluded' => '/portal/delete','authorized' => true,'accounts' => []],
                't3' => ['id' => 't3','name' => 'Public API','url' => 'https://api.example.test','environment' => 'Staging','description' => 'Target contoh yang belum memiliki izin pengujian.','scope' => '/v1','excluded' => '/v1/transfer','authorized' => false,'accounts' => []],
            ],
            'profiles' => [
                'p1' => ['id' => 'p1','name' => 'Baseline Web','description' => 'Pemeriksaan awal dengan cakupan ringan.','tools' => ['HTTP Headers','TLS Check'],'active' => true],
                'p2' => ['id' => 'p2','name' => 'Web Discovery','description' => 'Pemetaan endpoint dan pemeriksaan konfigurasi.','tools' => ['HTTP Headers','URL Discovery'],'active' => true],
            ],
            'scans' => [
                's3' => ['id' => 's3','target' => 't1','profile' => 'p1','status' => 'cancelled','created' => '18 Okt 2026, 15:40','progress' => 18,'source' => 'Wizard','account' => '','scope' => '/','excluded' => '/logout'],
                's1' => ['id' => 's1','target' => 't1','profile' => 'p1','status' => 'completed','created' => '19 Okt 2026, 09:12','progress' => 100,'source' => 'Wizard','account' => '','scope' => '/','excluded' => '/logout; /payments/confirm'],
                's2' => ['id' => 's2','target' => 't2','profile' => 'p2','status' => 'running','created' => '19 Okt 2026, 10:24','progress' => 42,'source' => 'Asisten demo','account' => '','scope' => '/portal','excluded' => '/portal/delete'],
            ],
            'findings' => [
                'f1' => ['id' => 'f1','scan' => 's1','target' => 't1','title' => 'Content-Security-Policy belum diatur','severity' => 'medium','type' => 'Security Misconfiguration','location' => 'https://staging.example.test/','status' => 'unreviewed','evidence' => "HTTP/1.1 200 OK\nContent-Type: text/html\nContent-Security-Policy: [tidak ditemukan]\n\nBUKTI SIMULASI — bukan respons target nyata.",'recommendation' => 'Tetapkan Content-Security-Policy sesuai kebutuhan aplikasi. Uji dengan Report-Only sebelum penegakan penuh.','note' => ''],
                'f2' => ['id' => 'f2','scan' => 's1','target' => 't1','title' => 'Cookie sesi tanpa atribut Secure','severity' => 'high','type' => 'Identification & Authentication','location' => 'https://staging.example.test/login','status' => 'confirmed','evidence' => "Set-Cookie: demo_session=[REDACTED]; HttpOnly; SameSite=Lax\nAtribut Secure tidak ditemukan. BUKTI SIMULASI.",'recommendation' => 'Gunakan HTTPS dan atribut Secure pada cookie sesi. Pastikan konfigurasi proxy dan session benar.','note' => 'Perlu verifikasi konfigurasi sesi pada lingkungan staging.'],
                'f3' => ['id' => 'f3','scan' => 's1','target' => 't1','title' => 'Versi server terungkap pada header','severity' => 'low','type' => 'Information Disclosure','location' => 'https://staging.example.test/','status' => 'unreviewed','evidence' => "Server: ExampleServer/1.0\nBUKTI SIMULASI.",'recommendation' => 'Minimalkan informasi versi pada respons server.','note' => ''],
                'f4' => ['id' => 'f4','scan' => 's1','target' => 't1','title' => 'X-Content-Type-Options tidak ditemukan','severity' => 'low','type' => 'Security Misconfiguration','location' => 'https://staging.example.test/assets','status' => 'fixed_pending_retest','evidence' => "X-Content-Type-Options: [tidak ditemukan]\nBUKTI SIMULASI.",'recommendation' => 'Tambahkan X-Content-Type-Options: nosniff.','note' => 'Perbaikan dicatat; menunggu retest.'],
            ],
            'reports' => ['r1' => ['id' => 'r1','scan' => 's1','version' => 1,'created' => '19 Okt 2026, 09:30','status' => 'draft']],
            'chats' => ['c1' => ['id' => 'c1','title' => 'Pemeriksaan baseline fintech','target' => 't1','messages' => [['role' => 'assistant','text' => 'Selamat datang. Ini asisten demo dengan respons contoh, tanpa koneksi LLM. Pilih target berizin dan tulis tujuan untuk melihat rencana pemeriksaan header.']],'plan' => false]],
            'tools' => [
                'headers' => ['name' => 'HTTP Headers','description' => 'Inspeksi header keamanan HTTP.','active' => true,'ready' => false,'version' => 'Belum terhubung'],
                'tls' => ['name' => 'TLS Check','description' => 'Pemeriksaan konfigurasi TLS.','active' => true,'ready' => false,'version' => 'Belum terhubung'],
                'discovery' => ['name' => 'URL Discovery','description' => 'Pemetaan URL di dalam scope.','active' => false,'ready' => false,'version' => 'Belum terhubung'],
            ],
        ];
    }
}
