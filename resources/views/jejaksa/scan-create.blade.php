<a href="{{ route('scans.index') }}" wire:navigate class="text-link">← Semua pemindaian</a>
<div class="page-head mt-4">
    <div>
        <h1 class="page-title">Siapkan pemindaian</h1>
        <p class="page-description">Tetapkan konteks. Tinjau batasnya. Mulai dengan yakin.</p>
    </div>
    <span class="badge badge-info">Langkah {{ $step }}/4</span>
</div>
<ol class="mb-8 grid grid-cols-2 gap-4 rounded-xl border border-slate-700 bg-slate-900/60 p-5 lg:grid-cols-4" aria-label="Tahap pemindaian">
    @foreach(['Pilih target', 'Scope & akun', 'Profil pengujian', 'Tinjau & mulai'] as $label)
        <li class="step {{ $step === $loop->iteration ? 'active' : '' }}" @if($step === $loop->iteration) aria-current="step" @endif>
            <span class="step-number">{{ $step > $loop->iteration ? '✓' : $loop->iteration }}</span>{{ $label }}</li>
    @endforeach
</ol>
<div class="grid gap-6 xl:grid-cols-[1fr_300px]">
    <section class="panel p-6 md:p-8">
        <form wire:submit="{{ $step === 4 ? 'startScan' : 'nextStep' }}">
            @if($step === 1)
                <h2 class="text-xl font-semibold tracking-tight">Apa yang akan diuji?</h2>
                <p class="mb-6 mt-2 text-sm muted">Hanya target yang sudah diverifikasi admin yang bisa dipindai.</p>
                <div class="space-y-3">
                    @forelse($targets as $target)
                        <label class="flex cursor-pointer items-start gap-4 rounded-lg border p-4 {{ $targetId === $target->id ? 'border-teal-500 bg-teal-950/30' : 'border-slate-600' }}">
                            <input type="radio" wire:model.live="targetId" value="{{ $target->id }}" name="target" class="mt-1">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold">{{ $target->nama }}</span>
                                <span class="mt-1 block break-all text-xs font-normal muted">{{ $target->base_url }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm muted">Belum ada target terverifikasi.</p>
                    @endforelse
                </div>
            @elseif($step === 2)
                <h2 class="text-xl font-semibold tracking-tight">Pastikan batasnya jelas.</h2>
                <p class="mb-6 mt-2 text-sm muted">Tool hanya mengirim request ke host di dalam scope ini.</p>
                @if($selectedTarget)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-700 p-4">
                            <p class="mb-3 text-xs muted">Dalam scope</p>
                            <pre class="text-teal-200">{{ parse_url($selectedTarget->base_url, PHP_URL_HOST) }}
{{ $selectedTarget->targetScopeRules->where('tipe', 'include')->pluck('pola_domain')->implode("\n") }}</pre>
                        </div>
                        <div class="rounded-lg border border-slate-700 p-4">
                            <p class="mb-3 text-xs muted">Dikecualikan</p>
                            <pre class="text-amber-200">{{ $selectedTarget->targetScopeRules->where('tipe', 'exclude')->pluck('pola_domain')->implode("\n") ?: 'Tidak ada' }}</pre>
                        </div>
                    </div>
                    <div class="field mt-6">
                        <label for="scan-account">Akun uji (opsional)</label>
                        <select id="scan-account" wire:model="accountId">
                            <option value="">Tanpa akun · pengujian publik</option>
                            @foreach($selectedTarget->targetTestAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->username }} · {{ $account->label_role }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs muted">Dicatat pada scan sebagai konteks pengujian.</p>
                    </div>
                @endif
            @elseif($step === 3)
                <h2 class="text-xl font-semibold tracking-tight">Pilih kedalaman pengujian.</h2>
                <p class="mb-6 mt-2 text-sm muted">Profil menentukan tool yang dijalankan ke target.</p>
                <div class="space-y-3">
                    @forelse($profiles as $profile)
                        <label class="flex cursor-pointer items-start gap-4 rounded-lg border p-5 {{ $profileId === $profile->id ? 'border-teal-500 bg-teal-950/30' : 'border-slate-600' }}">
                            <input type="radio" wire:model.live="profileId" value="{{ $profile->id }}" name="profile" class="mt-1">
                            <span>
                                <span class="block text-sm font-semibold">{{ $profile->nama }}</span>
                                <span class="mt-1 block text-xs font-normal muted">{{ $profile->deskripsi }}</span>
                                <span class="mt-3 flex flex-wrap gap-2">
                                    @foreach($profile->pengaturan['tools'] ?? [] as $tool)
                                        <span class="badge badge-neutral mono">{{ $tool }}</span>
                                    @endforeach
                                </span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm muted">Belum ada profil scan. <a class="text-link" href="{{ route('scan-profiles.index') }}" wire:navigate>Buat profil</a>.</p>
                    @endforelse
                </div>
            @else
                <h2 class="text-xl font-semibold tracking-tight">Satu pemeriksaan terakhir.</h2>
                <p class="mb-6 mt-2 text-sm muted">Scan akan mengirim request sungguhan ke target.</p>
                <dl class="grid gap-5 sm:grid-cols-2 text-sm">
                    <div><dt class="text-xs muted">Target</dt><dd class="mt-1">{{ $selectedTarget?->nama ?? 'Target tidak tersedia' }}</dd></div>
                    <div><dt class="text-xs muted">Profil</dt><dd class="mt-1">{{ $selectedProfile?->nama ?? 'Profil tidak tersedia' }}</dd></div>
                    <div><dt class="text-xs muted">URL</dt><dd class="mt-1 break-all mono text-xs">{{ $selectedTarget?->base_url }}</dd></div>
                    <div><dt class="text-xs muted">Tool</dt><dd class="mt-1 mono text-xs">{{ implode(', ', $selectedProfile?->pengaturan['tools'] ?? []) }}</dd></div>
                </dl>
                <label class="mt-6 flex items-start gap-3">
                    <input type="checkbox" wire:model="acknowledged" class="mt-1 rounded">
                    <span class="text-xs font-normal leading-6">Saya sudah memeriksa scope dan memahami bahwa tool akan mengirim request ke target ini.</span>
                </label>
            @endif
            <div class="mt-8 flex justify-between gap-3 border-t border-slate-700 pt-5">
                <div>
                    @if($step > 1)
                        <button type="button" wire:click="previousStep" class="btn-secondary">Kembali</button>
                    @else
                        <a href="{{ route('scans.index') }}" wire:navigate class="btn-ghost">Batal</a>
                    @endif
                </div>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ $step === 4 ? 'Mulai scan' : 'Lanjutkan' }}<x-jejaksa.icon name="arrow" size="16" /></button>
            </div>
        </form>
    </section>
    <aside>
        <div class="panel p-5">
            <p class="eyebrow text-[10px]">Ringkasan konfigurasi</p>
            <div class="mt-5 space-y-5 text-sm">
                <div><p class="mb-1 text-xs muted">Target</p><p>{{ $selectedTarget?->nama ?? 'Belum dipilih' }}</p></div>
                <div><p class="mb-1 text-xs muted">Profil</p><p>{{ $selectedProfile?->nama ?? 'Belum dipilih' }}</p></div>
            </div>
        </div>
        <p class="mt-4 px-1 text-xs leading-6 muted">Scan berjalan di antrean (queue worker). Progres di halaman detail diperbarui otomatis.</p>
    </aside>
</div>
