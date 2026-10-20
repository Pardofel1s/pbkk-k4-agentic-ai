<div class="page-head">
    <div>
        <h1 class="page-title">Pengaturan</h1>
        <p class="page-description">Identitas workspace dan kesiapan layanan, dalam satu tempat.</p>
    </div>
</div>
<div class="grid gap-6 xl:grid-cols-[1fr_320px]">
    <div class="space-y-6">
        <section class="panel p-6">
            <h2 class="font-semibold">Akun & profil</h2>
            <p class="mt-2 text-sm muted">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
            <a class="btn-secondary mt-5" href="{{ route('profile') }}" wire:navigate>Edit profil & keamanan<x-jejaksa.icon name="arrow" size="16" />
            </a>
        </section>
        <section class="panel">
            <div class="panel-heading">
                <h2 class="font-semibold">Kesiapan integrasi</h2>
                <span class="text-xs muted">Status frontend</span>
            </div>
            <div class="divide-y divide-slate-700 px-6">
                @foreach([['Autentikasi','Laravel Breeze + Livewire','active','Terhubung'],['Data domain','Target, scan, temuan, dan laporan','draft','Session demo'],['LLM / agent','Adapter percakapan dan rencana','draft','Belum terhubung'],['Scanner / queue','Eksekusi tool dan progres nyata','draft','Belum terhubung'],['RBAC domain','Permission dan assignment','draft','Menunggu backend'],['Sertifikat','Persetujuan, nomor, dan verifikasi','draft','Belum tersedia']] as [$name,$description,$status,$label])
                    <div class="flex flex-wrap items-center justify-between gap-3 py-5">
                        <div>
                            <p class="text-sm">{{ $name }}</p>
                            <p class="mt-1 text-xs muted">{{ $description }}</p>
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
                <span class="h-8 w-8 rounded border border-slate-600 bg-[#0B1220]" title="#0B1220">
                </span>
                <span class="h-8 w-8 rounded bg-[#111C2E] border border-slate-600" title="#111C2E">
                </span>
                <span class="h-8 w-8 rounded bg-[#2DD4BF]" title="#2DD4BF">
                </span>
                <span class="h-8 w-8 rounded bg-[#67E8F9]" title="#67E8F9">
                </span>
            </div>
            <p class="text-xs leading-6 muted">Tema gelap sesuai GSM Jejaksa. Reduced motion mengikuti preferensi sistem Anda.</p>
        </section>
        <div class="info-box">Konfigurasi koneksi dan API key hanya dikelola melalui backend. Nilai rahasia tidak ditampilkan atau diminta di demo.</div>
    </aside>
</div>
