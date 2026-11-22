<div class="page-head">
    <div>
        <h1 class="page-title">Pengaturan</h1>
        <p class="page-description">Akun Anda dan layanan yang dipakai Jejaksa.</p>
    </div>
</div>
<div class="grid gap-6 xl:grid-cols-[1fr_320px]">
    <div class="space-y-6">
        <section class="panel p-6">
            <h2 class="font-semibold">Akun & profil</h2>
            <p class="mt-2 text-sm muted">{{ auth()->user()->name }} · {{ auth()->user()->email }} · <span class="capitalize">{{ auth()->user()->getRoleNames()->implode(', ') }}</span></p>
            <a class="btn-secondary mt-5" href="{{ route('profile') }}" wire:navigate>Edit profil & keamanan<x-jejaksa.icon name="arrow" size="16" /></a>
        </section>
        <section class="panel">
            <div class="panel-heading"><h2 class="font-semibold">Layanan</h2><span class="text-xs muted">Dibaca dari konfigurasi server</span></div>
            <div class="divide-y divide-slate-700 px-6">
                @foreach([
                    ['LLM', 'Model '.config('llm.model').' via '.(parse_url((string) config('llm.base_url'), PHP_URL_HOST) ?: '—'), filled(config('llm.api_key')) ? 'active' : 'failed', filled(config('llm.api_key')) ? 'API key terpasang' : 'API key kosong'],
                    ['Ollama lokal', 'Penyedia utama, Senopati sebagai fallback', config('llm.ollama.enabled') ? 'active' : 'inactive', config('llm.ollama.enabled') ? 'Aktif' : 'Nonaktif'],
                    ['Antrean (queue)', 'Scan dan agent berjalan di worker: php artisan queue:work', 'active', config('queue.default')],
                    ['Host yang boleh diuji', implode(', ', config('llm.allowed_hosts', [])), 'active', count(config('llm.allowed_hosts', [])).' host'],
                    ['Batas agent', config('llm.max_steps').' langkah · '.config('llm.turn_timeout').' detik per giliran', 'active', 'Aktif'],
                ] as [$name, $description, $status, $label])
                    <div class="flex flex-wrap items-center justify-between gap-3 py-5">
                        <div class="min-w-0">
                            <p class="text-sm">{{ $name }}</p>
                            <p class="mt-1 break-all text-xs muted">{{ $description }}</p>
                        </div>
                        <x-jejaksa.badge :value="$status" :label="$label" />
                    </div>
                @endforeach
            </div>
        </section>
    </div>
    <aside class="space-y-5">
        <section class="panel p-6">
            <p class="eyebrow text-[10px]">Identitas visual</p>
            <img class="my-6 w-36" src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" width="144" height="51">
            <h2 class="font-semibold">Midnight–Teal</h2>
            <div class="my-4 flex gap-2" aria-label="Palet warna">
                <span class="h-8 w-8 rounded border border-slate-600 bg-[#0B1220]" title="#0B1220"></span>
                <span class="h-8 w-8 rounded border border-slate-600 bg-[#111C2E]" title="#111C2E"></span>
                <span class="h-8 w-8 rounded bg-[#2DD4BF]" title="#2DD4BF"></span>
                <span class="h-8 w-8 rounded bg-[#67E8F9]" title="#67E8F9"></span>
            </div>
            <p class="text-xs leading-6 muted">Tema gelap Jejaksa. Reduced motion mengikuti preferensi sistem Anda.</p>
        </section>
        <div class="info-box">API key dan kredensial hanya diatur di file .env server dan tidak pernah ditampilkan di aplikasi.</div>
    </aside>
</div>
