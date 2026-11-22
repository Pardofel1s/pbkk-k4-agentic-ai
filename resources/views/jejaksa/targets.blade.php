@if(!$item)
    <div class="page-head">
        <div>
            <h1 class="page-title">Target</h1>
            <p class="page-description">Kelola lingkungan, scope, dan akun untuk setiap pengujian.</p>
        </div>
        @can('create', App\Models\Target::class)
            <a class="btn-primary" href="{{ route('targets.create') }}" wire:navigate><x-jejaksa.icon name="plus" size="17" />Tambah target</a>
        @endcan
    </div>
    <div class="panel">
        <div class="panel-heading">
            <label class="relative w-full max-w-sm">
                <span class="sr-only">Cari target</span>
                <input wire:model.live.debounce.250ms="search" placeholder="Cari nama atau URL target…" type="search">
            </label>
            <span class="text-xs muted">{{ $targets->count() }} target</span>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Target</th>
                        <th>Lingkungan</th>
                        <th>Verifikasi</th>
                        <th>Akun uji</th>
                        <th><span class="sr-only">Buka</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($targets as $target)
                        <tr>
                            <td>
                                <a class="row-title flex items-center gap-3" href="{{ route('targets.show', $target) }}" wire:navigate>
                                    <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-600 bg-slate-800 text-teal-200"><x-jejaksa.icon name="target" size="17" /></span>
                                    <span>{{ $target->nama }}<span class="mt-1 block text-[11px] font-normal muted">{{ $target->base_url }}</span></span>
                                </a>
                            </td>
                            <td class="muted text-xs uppercase">{{ $target->lingkungan }}</td>
                            <td><x-jejaksa.badge :value="$target->status_verifikasi" /></td>
                            <td class="text-xs muted">{{ $target->target_test_accounts_count }} akun</td>
                            <td><a class="icon-btn" href="{{ route('targets.show', $target) }}" aria-label="Buka {{ $target->nama }}" wire:navigate><x-jejaksa.icon name="chevron" size="16" /></a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty">
                                    <h3>Target tidak ditemukan</h3>
                                    <p>Ubah kata kunci{{ auth()->user()->can('targets.create') ? ' atau daftarkan target baru' : '' }}.</p>
                                    @can('create', App\Models\Target::class)
                                        <a class="btn-primary" href="{{ route('targets.create') }}" wire:navigate>Tambah target</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    @php($canEdit = auth()->user()->can('update', $item))
    <a class="text-link" href="{{ route('targets.index') }}" wire:navigate>← Semua target</a>
    <div class="page-head mt-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="page-title">{{ $item->nama }}</h1>
                <x-jejaksa.badge :value="$item->status_verifikasi" />
            </div>
            <p class="page-description mono">{{ $item->base_url }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('verify', $item)
                @foreach([App\Models\Target::VERIFIED => 'Verifikasi', App\Models\Target::REJECTED => 'Tolak', App\Models\Target::PENDING => 'Kembalikan ke pending'] as $status => $label)
                    @if($item->status_verifikasi !== $status)
                        <button type="button" class="{{ $status === App\Models\Target::VERIFIED ? 'btn-primary' : 'btn-secondary' }}" wire:click="verifyTarget('{{ $status }}')" wire:loading.attr="disabled">{{ $label }}</button>
                    @endif
                @endforeach
            @endcan
            @can('create', [App\Models\Scan::class, $item])
                <a href="{{ route('scans.create', ['target' => $item->id]) }}" class="btn-primary" wire:navigate><x-jejaksa.icon name="scan" size="16" />Siapkan scan</a>
            @endcan
        </div>
    </div>
    <div class="tabs" x-data="workspaceTabs" @keydown="onKey($event)" role="tablist" aria-label="Detail target">
        @foreach(['overview' => 'Ringkasan', 'scope' => $canEdit ? 'Scope & edit' : 'Scope', 'accounts' => 'Akun uji', 'scans' => 'Pemindaian', 'reports' => 'Laporan'] as $key => $label)
            <button type="button" class="tab" role="tab" tabindex="{{ $tab === $key ? 0 : -1 }}" id="target-tab-{{ $key }}" aria-controls="target-panel" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>
    <div id="target-panel" role="tabpanel" aria-labelledby="target-tab-{{ $tab }}">
        @if($tab === 'overview')
            <div class="grid gap-5 lg:grid-cols-3">
                <section class="panel p-6 lg:col-span-2">
                    <h2 class="text-base font-semibold">Konteks pengujian</h2>
                    <dl class="mt-6 grid grid-cols-2 gap-6 text-sm">
                        <div><dt class="mb-2 text-xs muted">Pemilik</dt><dd>{{ $item->user->name }}</dd></div>
                        <div><dt class="mb-2 text-xs muted">Lingkungan</dt><dd class="uppercase">{{ $item->lingkungan }}</dd></div>
                        <div><dt class="mb-2 text-xs muted">Domain tambahan dalam scope</dt><dd><pre>{{ $item->targetScopeRules->where('tipe', 'include')->pluck('pola_domain')->implode("\n") ?: 'Hanya host base URL' }}</pre></dd></div>
                        <div><dt class="mb-2 text-xs muted">Domain dikecualikan</dt><dd><pre>{{ $item->targetScopeRules->where('tipe', 'exclude')->pluck('pola_domain')->implode("\n") ?: 'Tidak ada' }}</pre></dd></div>
                    </dl>
                </section>
                <div class="panel p-6">
                    <x-jejaksa.icon name="shield" class="text-teal-300" size="28" />
                    <h2 class="mt-4 font-semibold">{{ $item->isVerified() ? 'Siap diuji' : 'Belum bisa diuji' }}</h2>
                    <p class="mt-3 text-sm leading-7 muted">
                        @if($item->isVerified())
                            Admin sudah memverifikasi izin pengujian. Tool hanya mengirim request ke host target dan domain dalam scope.
                        @elseif($item->status_verifikasi === App\Models\Target::REJECTED)
                            Admin menolak target ini. Perbaiki data target lalu minta verifikasi ulang.
                        @else
                            Menunggu admin memverifikasi bahwa target ini boleh diuji. Scan dan agent AI belum bisa dijalankan.
                        @endif
                    </p>
                    <button type="button" wire:click="$set('tab', 'scope')" class="btn-secondary mt-5">Tinjau scope</button>
                </div>
            </div>
        @elseif($tab === 'scope')
            @if($canEdit)
                @include('jejaksa.partials.target-form')
                @can('delete', $item)
                    <div class="mt-6 flex justify-end">
                        <button type="button" class="btn-danger" wire:click="deleteTarget" wire:confirm="Hapus target ini beserta semua scan, temuan, dan laporannya?">Hapus target</button>
                    </div>
                @endcan
            @else
                <div class="info-box">Target yang sudah terverifikasi dikunci agar scope tidak berubah setelah izin diberikan. Hubungi admin untuk mengubahnya.</div>
            @endif
        @elseif($tab === 'accounts')
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="panel">
                    <div class="panel-heading"><h2 class="font-semibold">Akun uji target</h2><span class="text-xs muted">Password terenkripsi</span></div>
                    <div class="p-5 space-y-4">
                        @forelse($item->targetTestAccounts as $account)
                            <div class="flex items-center gap-3 rounded-lg border border-slate-700 p-4">
                                <span class="avatar"><x-jejaksa.icon name="users" size="16" /></span>
                                <div class="flex-1">
                                    <p class="text-sm">{{ $account->username }}</p>
                                    <p class="text-xs muted">Role pada aplikasi target: {{ $account->label_role }}</p>
                                </div>
                                @if($canEdit)
                                    <button type="button" class="icon-btn" wire:click="deleteAccount({{ $account->id }})" wire:confirm="Hapus akun uji ini?" aria-label="Hapus akun {{ $account->username }}"><x-jejaksa.icon name="close" size="16" /></button>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm muted">Belum ada akun uji. Akun uji dipakai untuk menguji halaman yang butuh login.</p>
                        @endforelse
                    </div>
                </section>
                @if($canEdit)
                    <form wire:submit="addAccount" class="panel p-6 space-y-4">
                        <h2 class="font-semibold">Tambah akun uji</h2>
                        <p class="text-xs muted">Gunakan akun khusus pengujian di lingkungan staging, bukan akun nasabah sungguhan.</p>
                        <div class="field">
                            <label for="account-role">Role pada aplikasi target</label>
                            <select id="account-role" wire:model="accountForm.label_role">
                                <option value="nasabah">Nasabah</option>
                                <option value="mitra">Mitra</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="field"><label for="account-user">Username</label><input id="account-user" wire:model="accountForm.username" required maxlength="100" autocomplete="off"></div>
                        <div class="field"><label for="account-password">Password</label><input id="account-password" type="password" wire:model="accountForm.password" required autocomplete="new-password"></div>
                        <button class="btn-primary" type="submit">Simpan akun uji</button>
                    </form>
                @endif
            </div>
        @elseif($tab === 'scans')
            <section class="panel">@include('jejaksa.partials.scan-table', ['scans' => $scans])</section>
        @elseif($tab === 'reports')
            <div class="panel p-6">
                <h2 class="mb-4 font-semibold">Laporan target</h2>
                @forelse($reports as $report)
                    <a href="{{ route('reports.show', $report) }}" wire:navigate class="flex justify-between border-t border-slate-700 py-4 text-sm">
                        <span>Scan #{{ $report->scan_id }} · Versi {{ $report->versi }} · {{ $report->generated_at->translatedFormat('d M Y, H:i') }}</span>
                        <span class="text-teal-300">Buka →</span>
                    </a>
                @empty
                    <p class="text-sm muted">Laporan tersedia setelah scan selesai dan analyst membuat laporannya.</p>
                @endforelse
            </div>
        @endif
    </div>
@endif
