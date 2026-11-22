<div class="page-head">
    <div>
        <h1 class="page-title">Pengguna & akses</h1>
        <p class="page-description">Hak akses yang jelas untuk peran yang berbeda.</p>
    </div>
    <a class="btn-secondary" href="{{ route('profile') }}" wire:navigate>Kelola profil saya<x-jejaksa.icon name="arrow" size="16" /></a>
</div>
<section class="panel mb-6">
    <div class="panel-heading">
        <h2 class="text-sm font-semibold">Akun Anda</h2>
        <span class="badge badge-success capitalize">{{ auth()->user()->getRoleNames()->implode(', ') ?: 'tanpa role' }}</span>
    </div>
    <div class="flex flex-wrap items-center gap-4 p-6">
        <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span>
        <div>
            <p class="font-semibold">{{ auth()->user()->name }}</p>
            <p class="mt-1 text-xs muted">{{ auth()->user()->email }}</p>
        </div>
    </div>
</section>
@can('users.manage')
    <div class="mb-6 grid gap-6 xl:grid-cols-[1fr_360px]">
        <section class="panel">
            <div class="panel-heading"><h2 class="text-sm font-semibold">Semua pengguna</h2><span class="text-xs muted">{{ $users->count() }} akun</span></div>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Pengguna</th><th>Role</th><th>Terdaftar</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td><span class="row-title">{{ $user->name }}</span><span class="mt-1 block text-[11px] muted">{{ $user->email }}</span></td>
                                <td class="text-xs capitalize">{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                                <td class="text-xs muted whitespace-nowrap">{{ $user->created_at->translatedFormat('d M Y') }}</td>
                                <td>
                                    @can('delete', $user)
                                        <button type="button" class="text-link" wire:click="deleteUser({{ $user->id }})" wire:confirm="Hapus {{ $user->email }} beserta datanya?">Hapus</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <form wire:submit="registerAnalyst" class="panel h-fit space-y-4 p-6">
            <h2 class="font-semibold">Daftarkan analyst</h2>
            <p class="text-xs muted">Client mendaftar sendiri lewat halaman register. Admin hanya dibuat dari seeder.</p>
            <div class="field"><label for="user-name">Nama</label><input id="user-name" wire:model="userForm.name" required maxlength="255"></div>
            <div class="field"><label for="user-email">Email</label><input id="user-email" type="email" wire:model="userForm.email" required></div>
            <div class="field"><label for="user-password">Password</label><input id="user-password" type="password" wire:model="userForm.password" required autocomplete="new-password"></div>
            <div class="field"><label for="user-password-confirmation">Konfirmasi password</label><input id="user-password-confirmation" type="password" wire:model="userForm.password_confirmation" required autocomplete="new-password"></div>
            <button class="btn-primary w-full" type="submit" wire:loading.attr="disabled">Daftarkan</button>
        </form>
    </div>
@endcan
<section class="panel">
    <div class="panel-heading"><h2 class="text-sm font-semibold">Matriks hak akses</h2><span class="text-xs muted">docs/rbac.md</span></div>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Kemampuan</th><th>Admin</th><th>Analyst</th><th>Client</th></tr></thead>
            <tbody>
                @foreach([
                    ['Mendaftarkan target', 'Ya (langsung terverifikasi)', '—', 'Ya (menunggu verifikasi)'],
                    ['Memverifikasi target', 'Ya', '—', '—'],
                    ['Menjalankan scan & agent AI', '—', 'Ya', '—'],
                    ['Meninjau status temuan', '—', 'Ya', '—'],
                    ['Melihat laporan & sertifikat', 'Ya', 'Ya', 'Milik sendiri'],
                    ['Berkomentar di temuan & laporan', '—', 'Ya', 'Milik sendiri'],
                    ['Menerbitkan sertifikat', 'Ya', '—', '—'],
                    ['Mengelola pengguna & tool', 'Ya', '—', '—'],
                ] as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td class="{{ $loop->first ? '' : 'text-xs muted' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
