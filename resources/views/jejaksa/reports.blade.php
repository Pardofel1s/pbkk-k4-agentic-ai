@if(!$item)
    <div class="page-head">
        <div>
            <h1 class="page-title">Laporan</h1>
            <p class="page-description">Rangkum konteks, bukti, dan tindak lanjut dalam satu dokumen.</p>
        </div>
        <a class="btn-secondary" href="{{ route('scans.index') }}" wire:navigate>Lihat scan selesai<x-jejaksa.icon name="arrow" size="16" />
        </a>
    </div>
    <div class="info-box mb-6">Laporan demo dapat diunduh sebagai HTML dan dicetak ke PDF dari browser. Dokumen selalu berlabel contoh, bukan hasil pengujian nyata.</div>
    <section class="panel">
        <div class="panel-heading">
            <h2 class="text-sm font-semibold">Arsip laporan contoh</h2>
            <label class="max-w-xs">
                <span class="sr-only">Cari laporan</span>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Cari target atau ID laporan…">
            </label>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Laporan / target</th>
                        <th>Versi</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(collect($state['reports'])->reverse()->filter(fn($report)=>str_contains(mb_strtolower($report['id'].' '.$state['targets'][$state['scans'][$report['scan']]['target']]['name']),mb_strtolower($search))) as $report)
                        <tr>
                            <td>
                                <a href="{{ route('reports.show',$report['id']) }}" class="row-title" wire:navigate>{{ $state['targets'][$state['scans'][$report['scan']]['target']]['name'] }}</a>
                                <p class="mt-1 text-[10px] muted">{{ strtoupper($report['id']) }} · {{ strtoupper($report['scan']) }}</p>
                            </td>
                            <td class="mono text-xs">v{{ $report['version'] }}</td>
                            <td>
                                <x-jejaksa.badge value="draft" label="Draf contoh" />
                            </td>
                            <td class="text-xs muted whitespace-nowrap">{{ $report['created'] }}</td>
                            <td>
                                <a class="text-link" href="{{ route('reports.show',$report['id']) }}" wire:navigate>Buka →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty">
                                    <h3>Belum ada laporan yang cocok</h3>
                                    <p>Ubah pencarian, atau buat laporan contoh dari pemindaian yang telah selesai.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@else
    @php($reportScan = $state['scans'][$item['scan']]) @php($reportTarget = $item['target_snapshot'] ?? $state['targets'][$reportScan['target']]) @php($reportFindings = $item['findings_snapshot'] ?? collect($state['findings'])->where('scan',$item['scan'])->all())
    <a href="{{ route('reports.index') }}" class="text-link" wire:navigate>← Semua laporan</a>
    <div class="page-head mt-4">
        <div>
            <h1 class="page-title">Laporan {{ $reportTarget['name'] }}</h1>
            <p class="page-description">{{ strtoupper($item['id']) }} · Versi {{ $item['version'] }} · {{ $item['created'] }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:loading.attr="disabled" type="button" class="btn-secondary" wire:click="generateReport('{{ $item['scan'] }}')">Buat versi baru</button>
            <button wire:loading.attr="disabled" type="button" class="btn-primary" wire:click="downloadReport">
                <x-jejaksa.icon name="download" size="16" />Unduh HTML contoh</button>
        </div>
    </div>
    <section class="panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-amber-500/40 bg-amber-950/30 px-6 py-3 text-xs text-amber-200">
            <strong>CONTOH — DATA SIMULASI</strong>
            <span>Draf · Tidak untuk sertifikasi keamanan</span>
        </div>
        <div class="p-6 md:p-10">
            <img src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" class="mb-8 w-32" width="128" height="45">
            <p class="eyebrow">Laporan pengujian web · Demo</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ $reportTarget['name'] }}</h2>
            <p class="mt-3 text-sm muted">{{ $reportTarget['url'] }}</p>
            <div class="mt-8 grid gap-6 border-y border-slate-700 py-6 text-sm sm:grid-cols-3">
                <div>
                    <p class="text-xs muted">Scope snapshot</p>
                    <pre class="mt-2">{{ $reportScan['scope'] }}</pre>
                </div>
                <div>
                    <p class="text-xs muted">Jumlah bukti contoh</p>
                    <p class="mt-2">{{ count($reportFindings) }} temuan</p>
                </div>
                <div>
                    <p class="text-xs muted">Scan sumber</p>
                    <a href="{{ route('scans.show',$item['scan']) }}" class="text-link mt-2 inline-block" wire:navigate>{{ strtoupper($item['scan']) }} ↗</a>
                </div>
            </div>
            <h3 class="mt-8 font-semibold">Ringkasan eksekutif</h3>
            <p class="mt-3 max-w-3xl text-sm leading-7 muted">Dokumen ini mendemonstrasikan struktur laporan Jejaksa. Data temuan bersifat simulasi; tidak ada pengujian jaringan atau validasi keamanan terhadap target. Tinjau setiap bukti dan prioritaskan tindak lanjut berdasarkan konteks aplikasi.</p>
            <h3 class="mb-4 mt-8 font-semibold">Temuan & rekomendasi</h3>
            @foreach($reportFindings as $finding)
                <article class="mb-4 rounded-lg border border-slate-700 p-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-jejaksa.badge :value="$finding['severity']" />
                        <h4 class="text-sm font-semibold">{{ $finding['title'] }}</h4>
                    </div>
                    <p class="mt-3 text-xs leading-6 muted">{{ $finding['recommendation'] }}</p>
                    <p class="mt-3 text-[11px] muted">Review: {{ $finding['status'] }} · {{ $finding['note'] ?: 'Belum ada catatan.' }}</p>
                </article>
            @endforeach
            <p class="mt-8 border-t border-slate-700 pt-5 text-xs leading-6 muted">Batasan: hanya scope dan bukti yang tercantum. Hasil tidak menjamin aplikasi bebas dari kerentanan. Laporan ini adalah contoh dan tidak dapat digunakan sebagai bukti pengujian produksi.</p>
        </div>
    </section>
@endif
