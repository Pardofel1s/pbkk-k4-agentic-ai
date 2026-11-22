<?php

namespace Database\Seeders;

use App\Models\ScanProfile;
use App\Models\Target;
use App\Models\Tool;
use App\Models\User;
use App\Services\Scan\FindingRules;
use App\Tools\ToolRegistry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Logins, the scan catalogue and the team's own test targets. Scans, findings
     * and reports are not seeded: they come from running real scans.
     */
    public function run(): void
    {
        $this->call(RolesAndAdminSeeder::class);

        // Dev logins (password: "password"); the admin comes from ADMIN_* in .env.
        User::factory()->create(['name' => 'Analyst', 'email' => 'analyst@example.com'])->assignRole('analyst');
        $client = User::factory()->create(['name' => 'Client PT XXX', 'email' => 'client@example.com'])->assignRole('client');

        foreach (app(ToolRegistry::class)->all() as $tool) {
            Tool::query()->firstOrCreate(['nama' => $tool->name()], ['deskripsi' => $tool->description(), 'status' => true]);
        }

        $rules = app(FindingRules::class);
        foreach (array_keys(FindingRules::TYPES) as $slug) {
            $rules->type($slug);
        }

        foreach ([
            ['Baseline Web', 'quick', 'Header keamanan dan atribut cookie. Cepat dan aman untuk pemeriksaan awal.', ['check_security_headers', 'check_cookie_flags']],
            ['Web Lengkap', 'full', 'Baseline ditambah token CSRF pada form dan probe reflected XSS.', ['check_security_headers', 'check_cookie_flags', 'check_csrf_token', 'test_reflected_xss']],
            ['Port Discovery', 'quick', 'Pemetaan port TCP umum dengan nmap (nmap harus terpasang di server).', ['scan_open_ports']],
        ] as [$nama, $tipe, $deskripsi, $tools]) {
            ScanProfile::query()->create(['nama' => $nama, 'tipe' => $tipe, 'deskripsi' => $deskripsi, 'pengaturan' => ['tools' => $tools]]);
        }

        // Team-owned deployments we may test; their hosts must also be in LLM_TOOL_ALLOWED_HOSTS.
        // The local app is seeded last so /agent picks it as the default target.
        foreach ([
            'SchemaCrypt' => 'https://sch-schemacrypt.vercel.app',
            'Cloudflare Tunnel App' => 'https://female-dat-economy-characteristics.trycloudflare.com',
            'Jejaksa Local' => 'http://localhost:8000',
        ] as $nama => $baseUrl) {
            Target::factory()->for($client)->create([
                'nama' => $nama,
                'base_url' => $baseUrl,
                'lingkungan' => 'staging',
                'status_verifikasi' => Target::VERIFIED,
            ]);
        }
    }
}
