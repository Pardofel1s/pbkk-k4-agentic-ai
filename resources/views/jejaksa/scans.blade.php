@if(!$item)
    <div class="page-head">
        <div>
            <h1 class="page-title">Pemindaian</h1>
            <p class="page-description">Lacak setiap pengujian, dari antrean hingga bukti terakhir.</p>
        </div>
        @can('scans.run')
            <a href="{{ route('scans.create') }}" class="btn-primary" wire:navigate><x-jejaksa.icon name="plus" size="17" />Pemindaian baru</a>
        @endcan
    </div>
    <div class="panel" @if($scans->whereIn('status', ['queued', 'running'])->isNotEmpty()) wire:poll.5s @endif>
        <div class="panel-heading">
            <label class="w-full max-w-sm">
                <span class="sr-only">Cari pemindaian</span>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Cari #ID atau nama target…">
            </label>
            <label>
                <span class="sr-only">Filter status pemindaian</span>
                <select wire:model.live="filter">
                    <option value="">Semua status</option>
                    <option value="queued">Antrean</option>
                    <option value="running">Berjalan</option>
                    <option value="completed">Selesai</option>
                    <option value="cancelled">Dibatalkan</option>
                    <option value="failed">Gagal</option>
                </select>
            </label>
        </div>
        @include('jejaksa.partials.scan-table', ['scans' => $scans])
    </div>
@else
    @php($active = in_array($item->status, ['queued', 'running'], true))
    @php($canRun = auth()->user()->can('create', [App\Models\Scan::class, $item->target]))
    <div @if($active) wire:poll.3s @endif>
        <a href="{{ route('scans.index') }}" wire:navigate class="text-link">← Semua pemindaian</a>
        <div class="page-head mt-4">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="page-title">{{ $item->target->nama }}</h1>
                    <x-jejaksa.badge :value="$item->status" />
                </div>
                <p class="page-description">#{{ $item->id }} · {{ $item->scanProfile->nama }} · {{ $item->created_at->translatedFormat('d M Y, H:i') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('update', $item)
                    @if($active)
                        <button wire:loading.attr="disabled" type="button" class="btn-danger" wire:click="cancelScan" wire:confirm="Batalkan scan ini? Tool yang sedang berjalan diselesaikan dulu.">Batalkan scan</button>
                    @elseif($canRun)
                        <button wire:loading.attr="disabled" type="button" class="btn-secondary" wire:click="retryScan">{{ $item->status === 'completed' ? 'Re-test' : 'Jalankan ulang' }}</button>
                    @endif
                    @if($item->status === 'completed')
                        <button wire:loading.attr="disabled" type="button" class="btn-primary" wire:click="generateReport"><x-jejaksa.icon name="file" size="16" />Buat laporan</button>
                    @endif
                @endcan
            </div>
        </div>
        <section class="panel mb-6 p-5">
            <div>
                <p class="text-sm font-semibold">{{ match($item->status) {
                    'queued' => 'Menunggu worker antrean mengambil scan ini',
                    'running' => 'Tool sedang dijalankan ke target',
                    'cancelled' => 'Scan dibatalkan',
                    'failed' => 'Scan gagal (target tidak terverifikasi lagi, atau terjadi galat)',
                    default => 'Scan selesai, temuan siap ditinjau',
                } }}</p>
                <p class="mt-1 text-xs muted">{{ $item->progress }}% · {{ count($item->scanProfile->pengaturan['tools'] ?? []) }} tool pada profil ini{{ $item->started_at ? ' · mulai '.$item->started_at->translatedFormat('H:i:s') : '' }}{{ $item->finished_at ? ' · selesai '.$item->finished_at->translatedFormat('H:i:s') : '' }}</p>
            </div>
            <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-slate-700" role="progressbar" aria-label="Progres pemindaian" aria-valuenow="{{ $item->progress }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-teal-400" style="width:{{ $item->progress }}%"></div>
            </div>
        </section>
        @if($item->retestOf)
            <div class="info-box mb-5">Re-test dari <a class="underline" href="{{ route('scans.show', $item->retestOf) }}" wire:navigate>scan #{{ $item->retestOf->id }}</a>. Temuan scan asal yang tidak ditemukan lagi otomatis ditandai sudah diperbaiki.</div>
        @endif
        <div class="tabs" x-data="workspaceTabs" @keydown="onKey($event)" role="tablist" aria-label="Detail pemindaian">
            @foreach(array_filter(['overview' => 'Ringkasan', 'findings' => 'Temuan', 'logs' => $toolCalls->isNotEmpty() || auth()->user()->can('targets.view-any') ? 'Aktivitas tool' : null, 'config' => 'Konfigurasi', 'reports' => 'Laporan']) as $key => $label)
                <button type="button" class="tab" role="tab" tabindex="{{ $tab === $key ? 0 : -1 }}" id="scan-tab-{{ $key }}" aria-controls="scan-panel" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab','{{ $key }}')">{{ $label }}@if($key === 'findings')<span class="ml-2 rounded bg-slate-700 px-1.5 text-[10px]">{{ $findings->count() }}</span>@endif</button>
            @endforeach
        </div>
        <div id="scan-panel" role="tabpanel" aria-labelledby="scan-tab-{{ $tab }}">
            @if($tab === 'overview')
                <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
                    <section class="panel p-6">
                        <h2 class="font-semibold">Jejak pengujian</h2>
                        <div class="mt-6">
                            <div class="activity-item"><p class="text-sm">Scan dibuat</p><p class="mt-1 text-xs muted">{{ $item->created_at->translatedFormat('d M Y, H:i:s') }} · profil {{ $item->scanProfile->nama }}</p></div>
                            @if($item->started_at)
                                <div class="activity-item"><p class="text-sm">Tool dijalankan ke target</p><p class="mt-1 text-xs muted">{{ $item->target->base_url }}</p></div>
                            @endif
                            @if($item->finished_at)
                                <div class="activity-item"><p class="text-sm">{{ $item->status === 'completed' ? $findings->count().' temuan dicatat' : 'Scan berhenti' }}</p><p class="mt-1 text-xs muted">{{ $item->finished_at->translatedFormat('d M Y, H:i:s') }}</p></div>
                            @endif
                            @foreach($retests as $retest)
                                <div class="activity-item"><p class="text-sm">Re-test <a class="text-link" href="{{ route('scans.show', $retest) }}" wire:navigate>#{{ $retest->id }}</a></p><p class="mt-1 text-xs muted"><x-jejaksa.badge :value="$retest->status" /></p></div>
                            @endforeach
                        </div>
                    </section>
                    <section class="panel p-6">
                        <h2 class="font-semibold">Distribusi risiko</h2>
                        <div class="mt-5 space-y-3">
                            @foreach(['critical', 'high', 'medium', 'low', 'info'] as $severity)
                                <div class="flex items-center justify-between text-xs"><x-jejaksa.badge :value="$severity" /><span class="mono muted">{{ $findings->where('severity', $severity)->count() }}</span></div>
                            @endforeach
                        </div>
                    </section>
                </div>
            @elseif($tab === 'findings')
                <section class="panel">@include('jejaksa.partials.finding-table', ['findings' => $findings])</section>
            @elseif($tab === 'logs')
                <section class="panel">
                    <div class="panel-heading">
                        <h2 class="text-sm font-semibold">Aktivitas tool</h2>
                        @can('targets.view-any')<a href="{{ route('executions.show', $item) }}" class="text-link" wire:navigate>Detail eksekusi →</a>@endcan
                    </div>
                    <div class="divide-y divide-slate-700">
                        @forelse($toolCalls as $call)
                            <div class="flex items-center justify-between gap-4 px-6 py-4 text-sm">
                                <span class="mono">{{ $call->tool->nama }}</span>
                                <span class="text-xs muted">{{ $call->created_at->translatedFormat('H:i:s') }}</span>
                                <x-jejaksa.badge :value="$call->status === 'success' ? 'completed' : ($call->status === 'failed' ? 'failed' : 'running')" :label="$call->status" />
                            </div>
                        @empty
                            <p class="p-6 text-sm muted">Belum ada tool yang dijalankan.</p>
                        @endforelse
                    </div>
                </section>
            @elseif($tab === 'config')
                <section class="panel p-6">
                    <h2 class="mb-5 font-semibold">Konfigurasi scan</h2>
                    <dl class="grid gap-6 sm:grid-cols-2 text-sm">
                        @foreach([
                            'Target' => $item->target->base_url,
                            'Profil' => $item->scanProfile->nama.' ('.$item->scanProfile->tipe.')',
                            'Tool' => implode(', ', $item->scanProfile->pengaturan['tools'] ?? []) ?: '—',
                            'Akun uji' => $item->targetTestAccount ? $item->targetTestAccount->username.' ('.$item->targetTestAccount->label_role.')' : 'Tanpa akun · pengujian publik',
                        ] as $label => $value)
                            <div><dt class="mb-1 text-xs muted">{{ $label }}</dt><dd class="break-all">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @elseif($tab === 'reports')
                <section class="panel p-6">
                    <h2 class="mb-5 font-semibold">Riwayat laporan</h2>
                    @forelse($reports as $report)
                        <a class="flex justify-between border-t border-slate-700 py-4 text-sm" href="{{ route('reports.show', $report) }}" wire:navigate>
                            <span>Versi {{ $report->versi }} · {{ $report->generated_at->translatedFormat('d M Y, H:i') }}</span>
                            <span class="text-teal-300">Buka →</span>
                        </a>
                    @empty
                        <p class="text-sm muted">Belum ada laporan untuk scan ini.</p>
                    @endforelse
                </section>
            @endif
        </div>
    </div>
@endif
