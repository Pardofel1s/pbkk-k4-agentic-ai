@php($highCount = $openFindings->whereIn('severity', ['high', 'critical'])->count())
<div class="page-head">
    <div>
        <p class="mb-2 text-xs muted">WORKSPACE OVERVIEW</p>
        <h1 class="page-title">Semua jejak, satu pandangan.</h1>
        <p class="page-description">Selamat datang, {{ auth()->user()->name }}. Berikut kondisi target dan pengujian Anda.</p>
    </div>
    @can('scans.run')
        <a href="{{ route('scans.create') }}" class="btn-primary" wire:navigate><x-jejaksa.icon name="plus" size="17" />Pemindaian baru</a>
    @elsecan('targets.create')
        <a href="{{ route('targets.create') }}" class="btn-primary" wire:navigate><x-jejaksa.icon name="plus" size="17" />Daftarkan target</a>
    @endcan
</div>
<div class="mb-7 grid grid-cols-2 gap-4 xl:grid-cols-4">
    @foreach([
        ['Target terdaftar', $targetCount, 'target', $verifiedCount.' terverifikasi'],
        ['Pemindaian aktif', $activeScans, 'scan', 'Antrean dan sedang berjalan'],
        ['Temuan terbuka', $openFindings->count(), 'shield', $highCount.' prioritas tinggi'],
        ['Laporan', $reportCount, 'file', 'Semua versi laporan'],
    ] as [$label, $value, $icon, $caption])
        <article class="stat">
            <div class="stat-label">{{ $label }}<x-jejaksa.icon :name="$icon" size="17" /></div>
            <p class="stat-value {{ $icon === 'shield' ? 'text-amber-200' : '' }}">{{ str_pad($value, 2, '0', STR_PAD_LEFT) }}</p>
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
                        <span><span class="text-red-300">●</span> {{ $highCount }} Tinggi</span>
                        <span><span class="text-amber-200">●</span> {{ $openFindings->where('severity', 'medium')->count() }} Sedang</span>
                        <span><span class="text-sky-300">●</span> {{ $openFindings->where('severity', 'low')->count() }} Rendah</span>
                    </div>
                </div>
            </div>
            @forelse($openFindings->sortBy(fn ($finding) => array_search($finding->severity, ['critical', 'high', 'medium', 'low', 'info']))->take(3) as $finding)
                <a href="{{ route('findings.show', $finding) }}" class="flex items-center justify-between gap-4 border-t border-slate-700/70 py-3" wire:navigate>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium">{{ $finding->vulnerabilityType->nama }}</p>
                        <p class="mt-1 text-[10px] muted">{{ $finding->scan->target->nama }} · #{{ $finding->id }}</p>
                    </div>
                    <x-jejaksa.badge :value="$finding->severity" />
                </a>
            @empty
                <p class="border-t border-slate-700/70 pt-4 text-xs muted">Belum ada temuan terbuka.</p>
            @endforelse
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading">
            <h2 class="text-sm font-semibold">{{ $pendingTargets->isNotEmpty() ? 'Target menunggu verifikasi' : 'Langkah berikutnya' }}</h2>
            <x-jejaksa.icon name="sparkles" size="17" class="text-teal-300" />
        </div>
        <div class="p-5">
            @if($pendingTargets->isNotEmpty())
                @foreach($pendingTargets->take(4) as $target)
                    <a href="{{ route('targets.show', $target) }}" class="flex items-center justify-between gap-3 border-b border-slate-700/70 py-3 text-xs" wire:navigate>
                        <span class="min-w-0"><span class="block truncate font-medium">{{ $target->nama }}</span><span class="block truncate muted">{{ $target->base_url }}</span></span>
                        <x-jejaksa.icon name="arrow" size="14" class="muted" />
                    </a>
                @endforeach
            @else
                <p class="text-sm font-medium">Bangun pengujian yang terarah.</p>
                <p class="mt-2 text-xs leading-6 muted">Pastikan target terverifikasi dan scope benar sebelum memindai.</p>
                <div class="mt-6 space-y-4">
                    @foreach(array_filter([
                        ['targets.index', 'target', 'Periksa target & scope', true],
                        ['scans.create', 'scan', 'Jalankan pemindaian', auth()->user()->can('scans.run')],
                        ['agent.chat', 'chat', 'Analisis bersama asisten AI', auth()->user()->can('chat.use')],
                        ['reports.index', 'file', 'Baca laporan & beri komentar', ! auth()->user()->can('chat.use')],
                    ], fn ($step) => $step[3]) as [$route, $icon, $label])
                        <a href="{{ route($route) }}" class="flex items-center gap-3 text-xs" wire:navigate>
                            <span class="mono text-[10px] text-teal-300">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <x-jejaksa.icon :name="$icon" size="15" />
                            <span class="flex-1">{{ $label }}</span>
                            <x-jejaksa.icon name="arrow" size="14" class="muted" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
<section class="panel min-w-0">
    <div class="panel-heading">
        <h2 class="text-sm font-semibold">Pemindaian terakhir</h2>
        <a href="{{ route('scans.index') }}" class="text-link" wire:navigate>Semua pemindaian ↗</a>
    </div>
    @include('jejaksa.partials.scan-table', ['scans' => $recentScans])
</section>
