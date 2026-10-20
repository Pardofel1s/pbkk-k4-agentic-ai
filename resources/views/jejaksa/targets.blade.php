@if(!$item)
    <div class="page-head">
        <div>
            <h1 class="page-title">Target</h1>
            <p class="page-description">Kelola lingkungan, scope, dan akun untuk setiap pengujian.</p>
        </div>
        <a class="btn-primary" href="{{ route('targets.create') }}" wire:navigate>
            <x-jejaksa.icon name="plus" size="17" />Tambah target</a>
    </div>
    <div class="panel">
        <div class="panel-heading">
            <label class="relative w-full max-w-sm">
                <span class="sr-only">Cari target</span>
                <input wire:model.live.debounce.250ms="search" placeholder="Cari nama atau URL target…" type="search">
            </label>
            <span class="text-xs muted">{{ count($state['targets']) }} target demo</span>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Target</th>
                        <th>Lingkungan</th>
                        <th>Izin pengujian</th>
                        <th>Akun uji</th>
                        <th>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(collect($state['targets'])->filter(fn($target)=>str_contains(mb_strtolower($target['name'].' '.$target['url']),mb_strtolower($search))) as $target)
                        <tr>
                            <td>
                                <a class="row-title flex items-center gap-3" href="{{ route('targets.show',$target['id']) }}" wire:navigate>
                                    <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-600 bg-slate-800 text-teal-200">
                                        <x-jejaksa.icon name="target" size="17" />
                                    </span>
                                    <span>{{ $target['name'] }}<span class="mt-1 block text-[11px] font-normal muted">{{ $target['url'] }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="muted text-xs">{{ $target['environment'] }}</td>
                            <td>
                                <x-jejaksa.badge :value="$target['authorized'] ? 'active' : 'draft'" :label="$target['authorized'] ? 'Izin dicatat' : 'Belum ada izin'" />
                            </td>
                            <td class="text-xs muted">{{ count($target['accounts']) }} akun</td>
                            <td>
                                <a class="icon-btn" href="{{ route('targets.show',$target['id']) }}" aria-label="Buka {{ $target['name'] }}" wire:navigate>
                                    <x-jejaksa.icon name="chevron" size="16" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty">
                                    <h3>Target tidak ditemukan</h3>
                                    <p>Ubah kata kunci atau tambahkan target baru untuk workspace Anda.</p>
                                    <a class="btn-primary" href="{{ route('targets.create') }}" wire:navigate>Tambah target</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    <a class="text-link" href="{{ route('targets.index') }}" wire:navigate>← Semua target</a>
    <div class="page-head mt-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="page-title">{{ $item['name'] }}</h1>
                <x-jejaksa.badge :value="$item['authorized'] ? 'active' : 'draft'" />
            </div>
            <p class="page-description mono">{{ $item['url'] }}</p>
        </div>
        <a href="{{ route('scans.create', ['target' => $item['id']]) }}" class="btn-primary" wire:navigate>
            <x-jejaksa.icon name="scan" size="16" />Siapkan scan</a>
    </div>
    <div class="tabs" x-data="workspaceTabs" @keydown="onKey($event)" role="tablist" aria-label="Detail target">
        @foreach(['overview'=>'Ringkasan','scope'=>'Scope & edit','accounts'=>'Akun uji','scans'=>'Pemindaian','reports'=>'Laporan'] as $key=>$label)
            <button type="button" class="tab" role="tab" tabindex="{{ $tab === $key ? 0 : -1 }}" id="target-tab-{{ $key }}" aria-controls="target-panel" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>
    <div id="target-panel" role="tabpanel" aria-labelledby="target-tab-{{ $tab }}">
        @if($tab === 'overview')
            <div class="grid gap-5 lg:grid-cols-3">
                <section class="panel p-6 lg:col-span-2">
                    <h2 class="text-base font-semibold">Konteks pengujian</h2>
                    <p class="mt-4 text-sm leading-7 muted">{{ $item['description'] ?: 'Belum ada deskripsi target.' }}</p>
                    <dl class="mt-6 grid grid-cols-2 gap-6 text-sm">
                        <div>
                            <dt class="mb-2 text-xs muted">Lingkungan</dt>
                            <dd>{{ $item['environment'] }}</dd>
                        </div>
                        <div>
                            <dt class="mb-2 text-xs muted">Izin pengujian</dt>
                            <dd>{{ $item['authorized'] ? 'Pernyataan izin dicatat (demo)' : 'Belum dicatat' }}</dd>
                        </div>
                        <div>
                            <dt class="mb-2 text-xs muted">Path dalam scope</dt>
                            <dd>
                                <pre>{{ $item['scope'] }}</pre>
                            </dd>
                        </div>
                        <div>
                            <dt class="mb-2 text-xs muted">Pengecualian</dt>
                            <dd>
                                <pre>{{ $item['excluded'] ?: 'Tidak ada' }}</pre>
                            </dd>
                        </div>
                    </dl>
                </section>
                <div class="panel p-6">
                    <x-jejaksa.icon name="shield" class="text-teal-300" size="28" />
                    <h2 class="mt-4 font-semibold">Periksa sebelum memulai</h2>
                    <p class="mt-3 text-sm leading-7 muted">Konfirmasikan izin, scope, profil, dan akun uji. Mengganti scope tidak mengubah snapshot scan terdahulu.</p>
                    <button type="button" wire:click="$set('tab', 'scope')" class="btn-secondary mt-5">Tinjau scope</button>
                </div>
            </div>
        @elseif($tab === 'scope')@include('jejaksa.partials.target-form')
        @elseif($tab === 'accounts')
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="panel">
                    <div class="panel-heading">
                        <h2 class="font-semibold">Akun uji target</h2>
                        <span class="text-xs muted">Metadata demo</span>
                    </div>
                    <div class="p-5 space-y-4">
                        @forelse($item['accounts'] as $accountItem)
                            <div class="flex items-center gap-3 rounded-lg border border-slate-700 p-4">
                                <span class="avatar">
                                    <x-jejaksa.icon name="users" size="16" />
                                </span>
                                <div>
                                    <p class="text-sm">{{ $accountItem['name'] }}</p>
                                    <p class="text-xs muted">{{ $accountItem['username'] }} · {{ $accountItem['role'] }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm muted">Belum ada metadata akun uji.</p>
                        @endforelse
                    </div>
                </section>
                <form wire:submit="addAccount" class="panel p-6 space-y-4">
                    <h2 class="font-semibold">Tambah metadata akun</h2>
                    <p class="text-xs muted">Kredensial rahasia akan dikelola backend. Jangan masukkan password atau token di field ini.</p>
                    <div class="field">
                        <label for="account-name">Nama akun</label>
                        <input id="account-name" wire:model="accountForm.name" required>
                    </div>
                    <div class="field">
                        <label for="account-user">Username demo</label>
                        <input id="account-user" wire:model="accountForm.username" required autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="account-role">Role pada target</label>
                        <select id="account-role" wire:model="accountForm.role">
                            <option>Nasabah</option>
                            <option>Mitra</option>
                            <option>Admin target</option>
                        </select>
                    </div>
                    <button class="btn-primary" type="submit">Simpan metadata</button>
                </form>
            </div>
        @elseif($tab === 'scans')
            <section class="panel">
                @include('jejaksa.partials.scan-table',['scans'=>collect($state['scans'])->where('target',$item['id'])])
            </section>
        @elseif($tab === 'reports')
            <div class="panel p-6">
                <h2 class="mb-4 font-semibold">Laporan target</h2>
                @forelse(collect($state['reports'])->filter(fn($report)=>$state['scans'][$report['scan']]['target'] === $item['id']) as $report)
                    <a href="{{ route('reports.show',$report['id']) }}" wire:navigate class="flex justify-between border-t border-slate-700 py-4 text-sm">
                        <span>{{ strtoupper($report['id']) }} · Versi {{ $report['version'] }}</span>
                        <span class="text-teal-300">Buka →</span>
                    </a>
                @empty
                    <p class="text-sm muted">Laporan tersedia setelah scan selesai dan laporan contoh dibuat.</p>
                @endforelse
            </div>
        @endif
    </div>
@endif
