<div class="page-head">
    <div>
        <h1 class="page-title">Pengguna & akses</h1>
        <p class="page-description">Hak akses yang jelas untuk peran yang berbeda.</p>
    </div>
    <a class="btn-secondary" href="{{ route('profile') }}" wire:navigate>Kelola profil saya<x-jejaksa.icon name="arrow" size="16" />
    </a>
</div>
<div class="info-box mb-6">Role Admin, Analyst, dan Viewer adalah kontrak kebutuhan FE/BE. RBAC domain belum terhubung; halaman ini tidak memberikan hak administrator atau mengubah permission pengguna.</div>
<section class="panel mb-6">
    <div class="panel-heading">
        <h2 class="text-sm font-semibold">Akun Anda</h2>
        <x-jejaksa.badge value="active" label="Terautentikasi" />
    </div>
    <div class="flex flex-wrap items-center gap-4 p-6">
        <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,2)) }}</span>
        <div>
            <p class="font-semibold">{{ auth()->user()->name }}</p>
            <p class="mt-1 text-xs muted">{{ auth()->user()->email }}</p>
        </div>
        <span class="ml-auto text-xs muted">Workspace personal · data demo terisolasi</span>
    </div>
</section>
<section class="panel">
    <div class="panel-heading">
        <h2 class="text-sm font-semibold">Matriks kemampuan yang direncanakan</h2>
        <span class="text-xs muted">Kontrak integrasi</span>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kemampuan</th>
                    <th>Admin</th>
                    <th>Analyst</th>
                    <th>Viewer</th>
                </tr>
            </thead>
            <tbody>
                @foreach([['Membaca data sesuai assignment','Ya','Ya','Ya'],['Mengelola target dan menjalankan scan','Ya','Ya','—'],['Meninjau temuan dan membuat laporan','Ya','Ya','—'],['Mengelola pengguna dan konfigurasi','Ya','—','—'],['Menyetujui sertifikat','Permission khusus','—','—']] as $row)
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
