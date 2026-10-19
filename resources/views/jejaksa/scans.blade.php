@if(!$item)
    <div class="page-head">
        <div>
            <h1 class="page-title">Pemindaian</h1>
            <p class="page-description">Lacak setiap pengujian, dari antrean hingga bukti terakhir.</p>
        </div>
        <a href="{{ route('scans.create') }}" class="btn-primary" wire:navigate>
            <x-jejaksa.icon name="plus" size="17" />Pemindaian baru</a>
    </div>
    <div class="panel">
        <div class="panel-heading">
            <label class="w-full max-w-sm">
                <span class="sr-only">Cari pemindaian</span>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Cari ID atau nama target…">
            </label>
            <label>
                <span class="sr-only">Filter status pemindaian</span>
                <select wire:model.live="filter">
                    <option value="">Semua status</option>
                    <option value="queued">Antrean</option>
                    <option value="running">Berjalan</option>
                    <option value="completed">Selesai</option>
                    <option value="cancel_requested">Pembatalan diminta</option>
                    <option value="cancelled">Dibatalkan</option>
                    <option value="failed">Gagal</option>
                </select>
            </label>
        </div>
        @include('jejaksa.partials.scan-table',['scans'=>collect($state['scans'])->reverse()->filter(fn($scan)=>(!$filter || $scan['status'] === $filter) && str_contains(mb_strtolower($scan['id'].' '.$state['targets'][$scan['target']]['name']),mb_strtolower($search)))])
    </div>
@else
    @php($target = $state['targets'][$item['target']])
    <a href="{{ route('scans.index') }}" wire:navigate class="text-link">← Semua pemindaian</a>
    <div class="page-head mt-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="page-title">{{ $target['name'] }}</h1>
                <x-jejaksa.badge :value="$item['status']" />
            </div>
            <p class="page-description">{{ strtoupper($item['id']) }} · {{ $state['profiles'][$item['profile']]['name'] }} · {{ $item['created'] }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(in_array($item['status'],['queued','running']))
                <button wire:loading.attr="disabled" type="button" class="btn-danger" wire:click="cancelScan" wire:confirm="Minta pembatalan scan simulasi ini?">Batalkan scan</button>
            @elseif(in_array($item['status'],['completed','cancelled','failed']))
                <button wire:loading.attr="disabled" type="button" class="btn-secondary" wire:click="retryScan">{{ $item['status'] === 'completed' ? 'Retest demo' : 'Coba ulang' }}</button>
            @endif @if($item['status'] === 'completed')
            <button wire:loading.attr="disabled" type="button" class="btn-primary" wire:click="generateReport('{{ $item['id'] }}')">
                <x-jejaksa.icon name="file" size="16" />Buat laporan contoh</button>
        @endif
    </div>
</div>
<section class="panel mb-6 p-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold">{{ match($item['status']) { 'queued'=>'Menunggu dimulainya simulasi', 'running'=>'Pemeriksaan header · langkah simulasi', 'cancel_requested'=>'Pembatalan diminta, menunggu konfirmasi simulasi', 'cancelled'=>'Simulasi dibatalkan', 'failed'=>'Simulasi mengalami kegagalan', default=>'Simulasi selesai, bukti siap ditinjau' } }}</p>
            <p class="mt-1 text-xs muted">{{ $item['progress'] }}% progres contoh · Tidak ada tool yang dieksekusi.</p>
        </div>
        @if(in_array($item['status'],['queued','running','cancel_requested']))
            <button wire:loading.attr="disabled" type="button" class="btn-secondary text-xs" wire:click="advanceScan">{{ $item['status'] === 'cancel_requested' ? 'Simulasikan konfirmasi batal' : 'Lanjutkan tahap simulasi' }}<x-jejaksa.icon name="arrow" size="15" />
            </button>
        @endif
    </div>
    <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-slate-700" role="progressbar" aria-label="Progres simulasi" aria-valuenow="{{ $item['progress'] }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full rounded-full bg-teal-400" style="width:{{ $item['progress'] }}%">
        </div>
    </div>
</section>
@if(in_array($item['status'], ['queued','running']))
    <div class="mb-5 flex justify-end">
        <button type="button" wire:click="failScan" wire:loading.attr="disabled" class="text-link">Coba skenario kegagalan demo</button>
    </div>
@endif
@if(isset($item['retest_of']))
    <div class="info-box mb-5">Retest demo dari <a class="underline" href="{{ route('scans.show',$item['retest_of']) }}" wire:navigate>{{ strtoupper($item['retest_of']) }}</a>. Status temuan sumber tetap menunggu bukti terverifikasi.</div>
@endif
<div class="tabs" x-data="workspaceTabs" @keydown="onKey($event)" role="tablist" aria-label="Detail pemindaian">
    @foreach(['overview'=>'Ringkasan','findings'=>'Temuan','logs'=>'Aktivitas tool','config'=>'Konfigurasi','reports'=>'Laporan'] as $key=>$label)
        <button wire:loading.attr="disabled" type="button" class="tab" role="tab" tabindex="{{ $tab === $key ? 0 : -1 }}" id="scan-tab-{{ $key }}" aria-controls="scan-panel" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab','{{ $key }}')">{{ $label }}@if($key === 'findings')
            <span class="ml-2 rounded bg-slate-700 px-1.5 text-[10px]">{{ collect($state['findings'])->where('scan',$item['id'])->count() }}</span>
        @endif
    </button>
@endforeach
</div>
<div id="scan-panel" role="tabpanel" aria-labelledby="scan-tab-{{ $tab }}">
    @if($tab === 'overview')
        <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
            <section class="panel p-6">
                <h2 class="font-semibold">Jejak pengujian</h2>
                <div class="mt-6">
                    <div class="activity-item">
                        <p class="text-sm">Konfigurasi dicatat</p>
                        <p class="mt-1 text-xs muted">Snapshot scope dan profil disimpan saat scan dibuat.</p>
                    </div>
                    <div class="activity-item">
                        <p class="text-sm">{{ $item['status'] === 'queued' ? 'Menunggu simulasi' : 'Scope diperiksa (simulasi)' }}</p>
                        <p class="mt-1 text-xs muted">{{ $target['url'] }}</p>
                    </div>
                    <div class="activity-item">
                        <p class="text-sm">{{ $item['status'] === 'completed' ? 'Bukti simulasi dicatat' : 'Aktivitas tool menunggu tahap berikutnya' }}</p>
                        <a class="text-link mt-2 inline-block" href="{{ route('executions.show',$item['id']) }}" wire:navigate>Buka detail eksekusi contoh ↗</a>
                    </div>
                </div>
            </section>
            <section class="panel p-6">
                <h2 class="font-semibold">Distribusi risiko</h2>
                <p class="mt-2 text-xs muted">Bukti contoh pada pemindaian ini.</p>
                <div class="mt-5 space-y-3">
                    @foreach(['critical'=>'Kritis','high'=>'Tinggi','medium'=>'Sedang','low'=>'Rendah','info'=>'Informasi'] as $severity=>$label)
                        <div class="flex items-center justify-between text-xs">
                            <x-jejaksa.badge :value="$severity" />
                            <span class="mono muted">{{ collect($state['findings'])->where('scan',$item['id'])->where('severity',$severity)->count() }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    @elseif($tab === 'findings')
        <section class="panel">
            @include('jejaksa.partials.finding-table',['findings'=>collect($state['findings'])->where('scan',$item['id'])])
        </section>
    @elseif($tab === 'logs')
        <section class="panel">
            <div class="panel-heading">
                <h2 class="text-sm font-semibold">Aktivitas tool · simulasi</h2>
                <a href="{{ route('executions.show',$item['id']) }}" class="text-link" wire:navigate>Detail eksekusi →</a>
            </div>
            <div class="p-6">
                <pre class="text-slate-300">[DEMO] {{ $item['created'] }}  scan.created
                    [DEMO] target={{ $target['url'] }}
                    [DEMO] scope.snapshot={{ $item['scope'] }}
                    [DEMO] status={{ $item['status'] }}
                    [DEMO] Tidak ada proses eksternal atau HTTP request ke target.</pre>
            </div>
        </section>
    @elseif($tab === 'config')
        <section class="panel p-6">
            <h2 class="mb-5 font-semibold">Snapshot konfigurasi</h2>
            <dl class="grid gap-6 sm:grid-cols-2 text-sm">
                @foreach(['Target'=>$target['url'],'Scope'=>$item['scope'],'Pengecualian'=>$item['excluded'] ?: 'Tidak ada','Akun'=>$item['account'] ?: 'Tanpa akun','Profil'=>$state['profiles'][$item['profile']]['name'],'Sumber'=>$item['source']] as $label=>$value)
                    <div>
                        <dt class="mb-1 text-xs muted">{{ $label }}</dt>
                        <dd class="break-all">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @elseif($tab === 'reports')
        <section class="panel p-6">
            <h2 class="mb-5 font-semibold">Riwayat laporan contoh</h2>
            @forelse(collect($state['reports'])->where('scan',$item['id']) as $report)
                <a class="flex justify-between border-t border-slate-700 py-4 text-sm" href="{{ route('reports.show',$report['id']) }}" wire:navigate>
                    <span>Versi {{ $report['version'] }} · {{ $report['created'] }}</span>
                    <span class="text-teal-300">Buka →</span>
                </a>
            @empty
                <p class="text-sm muted">Belum ada laporan. Selesaikan simulasi dan buat laporan contoh.</p>
            @endforelse
        </section>
    @endif
</div>
@endif
