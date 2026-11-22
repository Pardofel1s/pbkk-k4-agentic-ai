@if(!$item)
    <div class="page-head">
        <div>
            <h1 class="page-title">Laporan</h1>
            <p class="page-description">Rangkum konteks, bukti, dan tindak lanjut dalam satu dokumen.</p>
        </div>
        <a class="btn-secondary" href="{{ route('scans.index', ['filter' => 'completed']) }}" wire:navigate>Lihat scan selesai<x-jejaksa.icon name="arrow" size="16" /></a>
    </div>
    <section class="panel">
        <div class="panel-heading">
            <h2 class="text-sm font-semibold">Arsip laporan</h2>
            <label class="max-w-xs">
                <span class="sr-only">Cari laporan</span>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Cari nama target…">
            </label>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Laporan / target</th><th>Versi</th><th>Format</th><th>Dibuat</th><th><span class="sr-only">Buka</span></th></tr></thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td>
                                <a href="{{ route('reports.show', $report) }}" class="row-title" wire:navigate>{{ $report->scan->target->nama }}</a>
                                <p class="mt-1 text-[10px] muted">Laporan #{{ $report->id }} · scan #{{ $report->scan_id }}</p>
                            </td>
                            <td class="mono text-xs">v{{ $report->versi }}</td>
                            <td class="text-xs uppercase muted">{{ $report->format }}</td>
                            <td class="text-xs muted whitespace-nowrap">{{ $report->generated_at->translatedFormat('d M Y, H:i') }}</td>
                            <td><a class="text-link" href="{{ route('reports.show', $report) }}" wire:navigate>Buka →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><h3>Belum ada laporan</h3><p>Laporan dibuat analyst dari scan yang sudah selesai.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@else
    @php($scan = $item->scan)
    <a href="{{ route('reports.index') }}" class="text-link" wire:navigate>← Semua laporan</a>
    <div class="page-head mt-4">
        <div>
            <h1 class="page-title">Laporan {{ $scan->target->nama }}</h1>
            <p class="page-description">Laporan #{{ $item->id }} · Versi {{ $item->versi }} · {{ $item->generated_at->translatedFormat('d M Y, H:i') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $scan)
                <button wire:loading.attr="disabled" type="button" class="btn-secondary" wire:click="generateReport">Buat versi baru</button>
            @endcan
            @can('create', [App\Models\Comment::class, $item])
                @can('chat.use')
                    <button wire:loading.attr="disabled" type="button" class="btn-secondary" wire:click="analyzeReport"><x-jejaksa.icon name="sparkles" size="16" /><span wire:loading.remove wire:target="analyzeReport">Analisis AI</span><span wire:loading wire:target="analyzeReport">Menganalisis…</span></button>
                @endcan
            @endcan
            <button wire:loading.attr="disabled" type="button" class="btn-primary" wire:click="downloadReport"><x-jejaksa.icon name="download" size="16" />Unduh HTML</button>
        </div>
    </div>
    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <section class="panel overflow-hidden">
            <div class="p-6 md:p-10">
                <img src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" class="mb-8 w-32" width="128" height="45">
                <p class="eyebrow">Laporan pengujian keamanan web</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ $scan->target->nama }}</h2>
                <p class="mt-3 text-sm muted">{{ $scan->target->base_url }}</p>
                <div class="mt-8 grid gap-6 border-y border-slate-700 py-6 text-sm sm:grid-cols-3">
                    <div><p class="text-xs muted">Profil scan</p><p class="mt-2">{{ $scan->scanProfile->nama }}</p></div>
                    <div><p class="text-xs muted">Jumlah temuan</p><p class="mt-2">{{ $findings->count() }} temuan</p></div>
                    <div><p class="text-xs muted">Scan sumber</p><a href="{{ route('scans.show', $scan) }}" class="text-link mt-2 inline-block" wire:navigate>#{{ $scan->id }} ↗</a></div>
                </div>
                <h3 class="mb-4 mt-8 font-semibold">Temuan & rekomendasi</h3>
                <p class="mb-4 text-xs muted">Status di bawah adalah status terkini; file unduhan menyimpan kondisi saat versi ini dibuat.</p>
                @forelse($findings->sortBy(fn ($f) => array_search($f->severity, ['critical', 'high', 'medium', 'low', 'info'])) as $finding)
                    <article class="mb-4 rounded-lg border border-slate-700 p-5">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-jejaksa.badge :value="$finding->severity" />
                            <a class="text-sm font-semibold hover:underline" href="{{ route('findings.show', $finding) }}" wire:navigate>{{ $finding->vulnerabilityType->nama }}</a>
                            <x-jejaksa.badge :value="$finding->status" />
                        </div>
                        <p class="mt-2 mono text-[11px] muted break-all">{{ $finding->url_terdampak }}{{ $finding->parameter ? ' · '.$finding->parameter : '' }}</p>
                        <p class="mt-3 text-xs leading-6 muted">{{ $finding->rekomendasi ?: $finding->vulnerabilityType->rekomendasi }}</p>
                    </article>
                @empty
                    <p class="text-sm muted">Tidak ada temuan dari tool pada profil scan ini.</p>
                @endforelse
                <p class="mt-8 border-t border-slate-700 pt-5 text-xs leading-6 muted">Berlaku hanya untuk ruang lingkup dan waktu yang tercantum. Hasil pemindaian otomatis tidak menjamin aplikasi bebas dari kerentanan.</p>
            </div>
        </section>
        <div>@include('jejaksa.partials.comments')</div>
    </div>
@endif
