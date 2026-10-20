@php
$openFindings = collect($state['findings'])->whereNotIn('status',['resolved','false_positive']);
$highCount = $openFindings->whereIn('severity',['high','critical'])->count();
@endphp
<div class="page-head">
    <div>
        <p class="mb-2 text-xs muted">WORKSPACE OVERVIEW</p>
        <h1 class="page-title">Semua jejak, satu pandangan.</h1>
        <p class="page-description">Selamat datang, {{ auth()->user()->name }}. Ini kondisi workspace demo Anda.</p>
    </div>
    <a href="{{ route('scans.create') }}" class="btn-primary" wire:navigate>
        <x-jejaksa.icon name="plus" size="17" />Pemindaian baru</a>
</div>
<div class="mb-7 grid grid-cols-2 gap-4 xl:grid-cols-4">
    @foreach([['Target terdaftar',count($state['targets']),'target',collect($state['targets'])->pluck('environment')->unique()->count().' lingkungan pengujian'],['Pemindaian aktif',collect($state['scans'])->whereIn('status',['queued','running'])->count(),'scan','Lanjutkan simulasi dari detail'],['Temuan terbuka',$openFindings->count(),'shield',$highCount.' prioritas tinggi'],['Laporan tersedia',count($state['reports']),'file','Dokumen contoh, belum final']] as [$label,$value,$icon,$caption])
        <article class="stat">
            <div class="stat-label">{{ $label }}<x-jejaksa.icon :name="$icon" size="17" />
            </div>
            <p class="stat-value {{ $icon === 'shield' ? 'text-amber-200' : '' }}">{{ str_pad($value,2,'0',STR_PAD_LEFT) }}</p>
            <p class="mt-3 text-[11px] muted">{{ $caption }}</p>
        </article>
    @endforeach
</div>
<div class="mb-7 grid gap-5 xl:grid-cols-[1.65fr_1fr]">
    <section class="panel">
        <div class="panel-heading">
            <h2 class="text-sm font-semibold">Prioritas peninjauan</h2>
            <a class="text-link" href="{{ route('findings.index') }}" wire:navigate>Lihat semua ↗</a>
        </div>
        <div class="p-5">
            <div class="mb-6 flex items-center gap-5">
                <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-[7px] border-amber-300/70 text-2xl font-semibold">{{ $openFindings->count() }}</span>
                <div>
                    <p class="text-sm font-medium">Temuan menunggu tindak lanjut</p>
                    <p class="mt-1 text-xs muted">Mulai dari risiko tertinggi dan validasi buktinya.</p>
                    <div class="mt-3 flex gap-4 text-[10px] muted">
                        <span>
                            <span class="text-red-300">●</span> {{ $highCount }} Tinggi</span>
                        <span>
                            <span class="text-amber-200">●</span> {{ $openFindings->where('severity','medium')->count() }} Sedang</span>
                        <span>
                            <span class="text-sky-300">●</span> {{ $openFindings->where('severity','low')->count() }} Rendah</span>
                    </div>
                </div>
            </div>
            @foreach($openFindings->sortBy(fn($finding)=>array_search($finding['severity'],['critical','high','medium','low','info']))->take(2) as $finding)
                <a href="{{ route('findings.show',$finding['id']) }}" class="flex items-center justify-between gap-4 border-t border-slate-700/70 py-3" wire:navigate>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium">{{ $finding['title'] }}</p>
                        <p class="mt-1 text-[10px] muted">{{ $state['targets'][$finding['target']]['name'] }} · {{ strtoupper($finding['id']) }}</p>
                    </div>
                    <x-jejaksa.badge :value="$finding['severity']" />
                </a>
            @endforeach
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading">
            <h2 class="text-sm font-semibold">Langkah berikutnya</h2>
            <x-jejaksa.icon name="sparkles" size="17" class="text-teal-300" />
        </div>
        <div class="p-5">
            <p class="text-sm font-medium">Bangun pengujian yang terarah.</p>
            <p class="mt-2 text-xs leading-6 muted">Pastikan izin dan scope sudah benar sebelum melanjutkan ke pemindaian.</p>
            <div class="mt-6 space-y-4">
                @foreach([['targets.index','target','01','Periksa target & scope'],['scans.create','scan','02','Siapkan pemindaian'],['chat.index','chat','03','Diskusikan rencana dengan asisten']] as [$route,$icon,$number,$label])
                    <a href="{{ route($route) }}" class="flex items-center gap-3 text-xs" wire:navigate>
                        <span class="mono text-[10px] text-teal-300">{{ $number }}</span>
                        <x-jejaksa.icon :name="$icon" size="15" />
                        <span class="flex-1">{{ $label }}</span>
                        <x-jejaksa.icon name="arrow" size="14" class="muted" />
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</div>
<div class="grid gap-5 xl:grid-cols-[1.65fr_1fr]">
    <section class="panel min-w-0">
        <div class="panel-heading">
            <h2 class="text-sm font-semibold">Pemindaian terakhir</h2>
            <a href="{{ route('scans.index') }}" class="text-link" wire:navigate>Semua pemindaian ↗</a>
        </div>
        @include('jejaksa.partials.scan-table',['scans'=>collect($state['scans'])->reverse()->take(3)])
    </section>
    <section class="panel">
        <div class="panel-heading">
            <h2 class="text-sm font-semibold">Aktivitas workspace</h2>
            <span class="text-[10px] muted">CONTOH</span>
        </div>
        <div class="p-5">
            <div class="activity-item">
                <p class="text-xs">Baseline Web selesai</p>
                <p class="mt-1 text-[11px] muted">4 bukti simulasi tersedia untuk ditinjau.</p>
                <p class="mt-2 text-[10px] muted">19 Okt · 09:18</p>
            </div>
            <div class="activity-item">
                <p class="text-xs">Laporan contoh dibuat</p>
                <p class="mt-1 text-[11px] muted">Fintech Sandbox · Versi 1</p>
                <p class="mt-2 text-[10px] muted">19 Okt · 09:30</p>
            </div>
            <div class="activity-item">
                <p class="text-xs">Web Discovery berjalan</p>
                <p class="mt-1 text-[11px] muted">Merchant Portal · Progres simulasi</p>
            </div>
        </div>
    </section>
</div>
