@if(!$item)
    <div class="page-head">
        <div>
            <h1 class="page-title">Temuan</h1>
            <p class="page-description">Bukti yang bisa ditinjau. Keputusan yang bisa ditelusuri.</p>
        </div>
        <span class="badge badge-neutral">{{ $findings->count() }} temuan</span>
    </div>
    <section class="panel">
        <div class="panel-heading">
            <label class="w-full max-w-sm">
                <span class="sr-only">Cari temuan</span>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Cari jenis, CWE, atau URL…">
            </label>
            <label>
                <span class="sr-only">Filter risiko</span>
                <select wire:model.live="filter">
                    <option value="">Semua risiko</option>
                    <option value="critical">Kritis</option>
                    <option value="high">Tinggi</option>
                    <option value="medium">Sedang</option>
                    <option value="low">Rendah</option>
                    <option value="info">Informasi</option>
                </select>
            </label>
        </div>
        @include('jejaksa.partials.finding-table', ['findings' => $findings])
    </section>
@else
    @php($type = $item->vulnerabilityType)
    <a class="text-link" href="{{ route('findings.index') }}" wire:navigate>← Semua temuan</a>
    <div class="page-head mt-4">
        <div>
            <div class="mb-3 flex gap-2">
                <x-jejaksa.badge :value="$item->severity" />
                <x-jejaksa.badge :value="$item->status" />
            </div>
            <h1 class="page-title">{{ $type->nama }}</h1>
            <p class="page-description">#{{ $item->id }} · {{ $type->kode_cwe }} · {{ $type->kategori_owasp }}</p>
        </div>
    </div>
    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div class="space-y-5">
            <section class="panel p-6">
                <h2 class="text-sm font-semibold">Konteks & lokasi</h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2 text-sm">
                    <div><dt class="mb-1 text-xs muted">Target</dt><dd><a class="text-link" href="{{ route('targets.show', $item->scan->target) }}" wire:navigate>{{ $item->scan->target->nama }} ↗</a></dd></div>
                    <div><dt class="mb-1 text-xs muted">Pemindaian sumber</dt><dd><a class="text-link" href="{{ route('scans.show', $item->scan) }}" wire:navigate>#{{ $item->scan_id }} ↗</a></dd></div>
                    <div class="sm:col-span-2"><dt class="mb-1 text-xs muted">Lokasi</dt><dd class="mono break-all text-xs">{{ $item->url_terdampak }}{{ $item->parameter ? ' · '.$item->parameter : '' }}</dd></div>
                </dl>
                <p class="mt-6 text-sm leading-7 muted">{{ $type->deskripsi }}</p>
            </section>
            @if($item->proof)
                <section class="panel">
                    <div class="panel-heading">
                        <h2 class="text-sm font-semibold">Bukti (output tool)</h2>
                        @can('targets.view-any')<a class="text-link" href="{{ route('executions.show', $item->scan) }}" wire:navigate>Jejak tool ↗</a>@endcan
                    </div>
                    <pre class="p-6 text-teal-100">{{ $item->proof }}</pre>
                </section>
            @endif
            <section class="panel p-6">
                <h2 class="text-sm font-semibold">Rekomendasi perbaikan</h2>
                <p class="mt-4 text-sm leading-7 muted">{{ $item->rekomendasi ?: $type->rekomendasi }}</p>
            </section>
            @include('jejaksa.partials.comments')
        </div>
        <aside class="space-y-4">
            @can('update', $item)
                <form wire:submit="saveReview" class="panel p-6 space-y-5">
                    <h2 class="font-semibold">Tinjau temuan</h2>
                    <div class="field">
                        <label for="review-status">Status</label>
                        <select id="review-status" wire:model="reviewStatus">
                            <option value="open">Terbuka</option>
                            <option value="confirmed">Terkonfirmasi</option>
                            <option value="false_positive">False positive</option>
                            @if($item->status === 'fixed')<option value="fixed" disabled>Sudah diperbaiki (dari re-test)</option>@endif
                        </select>
                    </div>
                    <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Simpan status</button>
                    <p class="text-[11px] leading-6 muted">Status "sudah diperbaiki" diberikan otomatis oleh re-test yang tidak lagi menemukan celah ini. Tulis alasan keputusan di diskusi.</p>
                </form>
            @endcan
            @if(in_array($item->status, ['open', 'confirmed'], true) && auth()->user()->can('create', [App\Models\Scan::class, $item->scan->target]))
                <button type="button" class="btn-secondary w-full" wire:click="retestFinding" wire:loading.attr="disabled"><x-jejaksa.icon name="scan" size="16" />Re-test scan sumber</button>
            @endif
        </aside>
    </div>
@endif
