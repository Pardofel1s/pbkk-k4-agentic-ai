<a href="{{ route('scans.index') }}" wire:navigate class="text-link">← Semua pemindaian</a>
<div class="page-head mt-4">
    <div>
        <h1 class="page-title">Siapkan pemindaian</h1>
        <p class="page-description">Tetapkan konteks. Tinjau batasnya. Mulai dengan yakin.</p>
    </div>
    <span class="badge badge-info">Wizard demo · {{ $step }}/4</span>
</div>
<ol class="mb-8 grid grid-cols-2 gap-4 rounded-xl border border-slate-700 bg-slate-900/60 p-5 lg:grid-cols-4" aria-label="Tahap pemindaian">
    @foreach(['Pilih target','Scope & akun','Profil pengujian','Tinjau & mulai'] as $label)
        <li class="step {{ $step === $loop->iteration ? 'active' : '' }}" @if($step === $loop->iteration) aria-current="step" @endif>
            <span class="step-number">{{ $step > $loop->iteration ? '✓' : $loop->iteration }}</span>{{ $label }}</li>
    @endforeach
</ol>
<div class="grid gap-6 xl:grid-cols-[1fr_300px]">
    <section class="panel p-6 md:p-8">
        <form wire:submit="{{ $step === 4 ? 'startScan' : 'nextStep' }}">
            @if($step === 1)
                <h2 class="text-xl font-semibold tracking-tight">Apa yang akan diuji?</h2>
                <p class="mb-6 mt-2 text-sm muted">Pilih satu target dari workspace Anda.</p>
                <div class="space-y-3">
                    @foreach($state['targets'] as $target)
                        <label class="flex cursor-pointer items-start gap-4 rounded-lg border p-4 {{ $targetId === $target['id'] ? 'border-teal-500 bg-teal-950/30' : 'border-slate-600' }}">
                            <input type="radio" wire:model.live="targetId" value="{{ $target['id'] }}" name="target" class="mt-1">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold">{{ $target['name'] }}</span>
                                <span class="mt-1 block break-all text-xs font-normal muted">{{ $target['url'] }}</span>
                                <span class="mt-2 inline-block">
                                    <x-jejaksa.badge :value="$target['authorized'] ? 'active' : 'draft'" :label="$target['authorized'] ? 'Izin dicatat' : 'Belum ada izin'" />
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <a href="{{ route('targets.create') }}" class="text-link mt-5 inline-flex items-center gap-2" wire:navigate>
                    <x-jejaksa.icon name="plus" size="14" />Tambahkan target lain</a>
            @elseif($step === 2)@php($selectedTarget = $state['targets'][$targetId] ?? null)
                <h2 class="text-xl font-semibold tracking-tight">Pastikan batasnya jelas.</h2>
                <p class="mb-6 mt-2 text-sm muted">Scope berikut disalin menjadi snapshot saat scan dibuat.</p>
                @if($selectedTarget)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-700 p-4">
                            <p class="mb-3 text-xs muted">Dalam scope</p>
                            <pre class="text-teal-200">{{ $selectedTarget['scope'] }}</pre>
                        </div>
                        <div class="rounded-lg border border-slate-700 p-4">
                            <p class="mb-3 text-xs muted">Dikecualikan</p>
                            <pre class="text-amber-200">{{ $selectedTarget['excluded'] ?: 'Tidak ada' }}</pre>
                        </div>
                    </div>
                    <div class="field mt-6">
                        <label for="scan-account">Akun uji (opsional)
                        </label>
                        <select id="scan-account" wire:model="account">
                            <option value="">Tanpa akun · Pengujian publik</option>
                            @foreach($selectedTarget['accounts'] as $accountOption)
                                <option value="{{ $accountOption['username'] }}">{{ $accountOption['name'] }} · {{ $accountOption['role'] }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs muted">Demo hanya menyertakan metadata; tidak melakukan login ke target.</p>
                    </div>
                    <a href="{{ route('targets.show',$targetId) }}" class="text-link mt-5 inline-block" wire:navigate>Edit scope pada detail target ↗</a>
                @endif
            @elseif($step === 3)
                <h2 class="text-xl font-semibold tracking-tight">Pilih kedalaman pengujian.</h2>
                <p class="mb-6 mt-2 text-sm muted">Profil menentukan tool yang direncanakan. Tidak ada tool nyata dijalankan pada demo.</p>
                <div class="space-y-3">
                    @foreach($state['profiles'] as $profile)
                        <label class="flex cursor-pointer items-start gap-4 rounded-lg border p-5 {{ $profileId === $profile['id'] ? 'border-teal-500 bg-teal-950/30' : 'border-slate-600' }}">
                            <input type="radio" wire:model.live="profileId" value="{{ $profile['id'] }}" name="profile" class="mt-1">
                            <span>
                                <span class="block text-sm font-semibold">{{ $profile['name'] }}</span>
                                <span class="mt-1 block text-xs font-normal muted">{{ $profile['description'] }}</span>
                                <span class="mt-3 flex flex-wrap gap-2">
                                    @foreach($profile['tools'] as $tool)
                                        <span class="badge badge-neutral">{{ $tool }}</span>
                                    @endforeach
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @else @php($selectedTarget = $state['targets'][$targetId] ?? null) @php($selectedProfile = $state['profiles'][$profileId] ?? null)
                <h2 class="text-xl font-semibold tracking-tight">Satu pemeriksaan terakhir.</h2>
                <p class="mb-6 mt-2 text-sm muted">Tinjau konfigurasi sebelum membuat scan simulasi.</p>
                <dl class="grid gap-5 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-xs muted">Target</dt>
                        <dd class="mt-1">{{ $selectedTarget['name'] ?? 'Target tidak tersedia' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs muted">Profil</dt>
                        <dd class="mt-1">{{ $selectedProfile['name'] ?? 'Profil tidak tersedia' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs muted">Scope</dt>
                        <dd class="mt-1">
                            <pre>{{ $selectedTarget['scope'] ?? '' }}</pre>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs muted">Akun</dt>
                        <dd class="mt-1">{{ $account ?: 'Tanpa akun' }}</dd>
                    </div>
                </dl>
                <div class="mt-6 space-y-3 rounded-lg border border-slate-700 p-4 text-xs">
                    <p class="flex items-center gap-2 {{ ($selectedTarget['authorized'] ?? false) ? 'text-green-300' : 'text-amber-200' }}">
                        <x-jejaksa.icon :name="($selectedTarget['authorized'] ?? false) ? 'check' : 'alert'" size="16" />Izin target {{ ($selectedTarget['authorized'] ?? false) ? 'dicatat' : 'belum dicatat — lengkapi pada target' }}</p>
                    <p class="flex items-center gap-2 text-teal-200">
                        <x-jejaksa.icon name="check" size="16" />Data demo siap untuk simulasi</p>
                    <p class="flex items-center gap-2 muted">
                        <x-jejaksa.icon name="alert" size="16" />Backend scanner, queue, dan LLM belum terhubung</p>
                </div>
                <label class="mt-6 flex items-start gap-3">
                    <input type="checkbox" wire:model="acknowledged" class="mt-1 rounded">
                    <span class="text-xs font-normal leading-6">Saya telah memeriksa scope dan memahami bahwa ini simulasi, bukan pengujian target nyata.</span>
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
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ $step === 4 ? 'Buat scan simulasi' : 'Lanjutkan' }}<x-jejaksa.icon name="arrow" size="16" />
                </button>
            </div>
        </form>
    </section>
    <aside>
        <div class="panel p-5">
            <p class="eyebrow text-[10px]">Ringkasan konfigurasi</p>
            <div class="mt-5 space-y-5 text-sm">
                <div>
                    <p class="mb-1 text-xs muted">Target</p>
                    <p>{{ $state['targets'][$targetId]['name'] ?? 'Belum dipilih' }}</p>
                </div>
                <div>
                    <p class="mb-1 text-xs muted">Profil</p>
                    <p>{{ $state['profiles'][$profileId]['name'] ?? 'Belum dipilih' }}</p>
                </div>
                <div>
                    <p class="mb-1 text-xs muted">Mode</p>
                    <span class="text-teal-200">Simulasi frontend</span>
                </div>
            </div>
        </div>
        <p class="mt-4 px-1 text-xs leading-6 muted">Progres simulasi dipindahkan secara manual dari halaman detail. Tidak ada estimasi waktu scanner nyata.</p>
    </aside>
</div>
