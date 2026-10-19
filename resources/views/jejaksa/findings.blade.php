@if(!$item)
    <div class="page-head">
        <div>
            <h1 class="page-title">Temuan</h1>
            <p class="page-description">Bukti yang bisa ditinjau. Keputusan yang bisa ditelusuri.</p>
        </div>
        <span class="badge badge-neutral">{{ count($state['findings']) }} bukti simulasi</span>
    </div>
    <section class="panel">
        <div class="panel-heading">
            <label class="w-full max-w-sm">
                <span class="sr-only">Cari temuan</span>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Cari judul atau jenis kerentanan…">
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
        @include('jejaksa.partials.finding-table',['findings'=>collect($state['findings'])->filter(fn($finding)=>(!$filter || $finding['severity'] === $filter) && str_contains(mb_strtolower($finding['title'].' '.$finding['type']),mb_strtolower($search)))])
    </section>
@else
    <a class="text-link" href="{{ route('findings.index') }}" wire:navigate>← Semua temuan</a>
    <div class="page-head mt-4">
        <div>
            <div class="mb-3 flex gap-2">
                <x-jejaksa.badge :value="$item['severity']" />
                <x-jejaksa.badge :value="$item['status']" />
            </div>
            <h1 class="page-title">{{ $item['title'] }}</h1>
            <p class="page-description">{{ strtoupper($item['id']) }} · {{ $item['type'] }} · Bukti simulasi</p>
        </div>
    </div>
    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div class="space-y-5">
            <section class="panel p-6">
                <h2 class="text-sm font-semibold">Konteks & lokasi</h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="mb-1 text-xs muted">Target</dt>
                        <dd>
                            <a class="text-link" href="{{ route('targets.show',$item['target']) }}" wire:navigate>{{ $state['targets'][$item['target']]['name'] }} ↗</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="mb-1 text-xs muted">Pemindaian sumber</dt>
                        <dd>
                            <a class="text-link" href="{{ route('scans.show',$item['scan']) }}" wire:navigate>{{ strtoupper($item['scan']) }} ↗</a>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="mb-1 text-xs muted">Lokasi</dt>
                        <dd class="mono break-all text-xs">{{ $item['location'] }}</dd>
                    </div>
                </dl>
            </section>
            <section class="panel">
                <div class="panel-heading">
                    <h2 class="text-sm font-semibold">Bukti simulasi</h2>
                    <a class="text-link" href="{{ route('executions.show',$item['scan']) }}" wire:navigate>Jejak tool ↗</a>
                </div>
                <pre class="p-6 text-teal-100">{{ $item['evidence'] }}</pre>
            </section>
            <section class="panel p-6">
                <h2 class="text-sm font-semibold">Rekomendasi perbaikan</h2>
                <p class="mt-4 text-sm leading-7 muted">{{ $item['recommendation'] }}</p>
                <div class="info-box mt-5">Verifikasi konteks dan dampak secara manual. Temuan dari simulasi tidak menyatakan kondisi target nyata.</div>
            </section>
        </div>
        <aside>
            <form wire:submit="saveReview" class="panel p-6 space-y-5">
                <h2 class="font-semibold">Tinjau temuan</h2>
                <p class="text-xs leading-6 muted">Catat alasan keputusan agar tim dapat melanjutkan tindak lanjut.</p>
                <div class="field">
                    <label for="review-status">Status peninjauan</label>
                    <select id="review-status" wire:model="reviewStatus">
                        <option value="unreviewed">Belum ditinjau</option>
                        <option value="confirmed">Terkonfirmasi</option>
                        <option value="false_positive">False positive</option>
                        <option value="fixed_pending_retest">Menunggu retest</option>
                    </select>
                </div>
                <div class="field">
                    <label for="review-note">Catatan / alasan</label>
                    <textarea id="review-note" rows="5" wire:model="reviewNote" required minlength="8" maxlength="2000" placeholder="Jelaskan bukti dan keputusan Anda…">
                    </textarea>
                </div>
                <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Simpan review demo</button>
                <p class="text-[11px] leading-6 muted">Status Terselesaikan menunggu retest terverifikasi dari backend. Menandai perbaikan tidak otomatis menutup temuan.</p>
            </form>
            @if(in_array($item['status'], ['confirmed','fixed_pending_retest']))
                <button type="button" class="btn-secondary mt-4 w-full" wire:click="retestFinding" wire:loading.attr="disabled">
                    <x-jejaksa.icon name="scan" size="16" />Buat retest demo</button>
            @endif
        </aside>
    </div>
@endif
