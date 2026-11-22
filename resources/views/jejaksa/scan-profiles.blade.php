<div class="page-head">
    <div>
        <h1 class="page-title">Profil scan</h1>
        <p class="page-description">Paket tool yang konsisten untuk setiap tujuan pengujian.</p>
    </div>
    @can('scans.run')
        <button wire:loading.attr="disabled" type="button" class="btn-primary" wire:click="$toggle('showProfileForm')"><x-jejaksa.icon name="plus" size="17" />Profil baru</button>
    @endcan
</div>
@if($showProfileForm)
    <form wire:submit="saveProfile" class="panel mb-6 p-6">
        <h2 class="mb-5 font-semibold">Profil baru</h2>
        <div class="grid gap-5 md:grid-cols-3">
            <div class="field"><label for="profile-name">Nama profil</label><input id="profile-name" wire:model="profileForm.nama" required maxlength="100"></div>
            <div class="field">
                <label for="profile-type">Tipe</label>
                <select id="profile-type" wire:model="profileForm.tipe">
                    <option value="quick">Quick</option>
                    <option value="full">Full</option>
                    <option value="auth">Auth</option>
                </select>
            </div>
            <div class="field"><label for="profile-description">Deskripsi</label><input id="profile-description" wire:model="profileForm.deskripsi" maxlength="300"></div>
        </div>
        <fieldset class="mt-5">
            <legend class="mb-3 text-sm">Tool yang dijalankan</legend>
            <div class="flex flex-wrap gap-5">
                @foreach($toolNames as $tool)
                    <label class="flex items-center gap-2 mono text-xs"><input type="checkbox" wire:model="profileForm.tools" value="{{ $tool }}" class="rounded">{{ $tool }}</label>
                @endforeach
            </div>
        </fieldset>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn-primary">Simpan profil</button>
            <button type="button" class="btn-secondary" wire:click="$set('showProfileForm', false)">Batal</button>
        </div>
    </form>
@endif
<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
    @forelse($profiles as $profile)
        <article class="panel flex flex-col p-6">
            <div class="flex items-center justify-between">
                <span class="feature-icon mb-0"><x-jejaksa.icon name="settings" /></span>
                <span class="badge badge-neutral uppercase">{{ $profile->tipe }}</span>
            </div>
            <h2 class="mt-6 text-lg font-semibold">{{ $profile->nama }}</h2>
            <p class="mt-2 min-h-12 text-sm muted">{{ $profile->deskripsi }}</p>
            <div class="my-5 flex flex-wrap gap-2">
                @foreach($profile->pengaturan['tools'] ?? [] as $tool)
                    <span class="badge badge-neutral mono">{{ $tool }}</span>
                @endforeach
            </div>
            <p class="mt-auto border-t border-slate-700 pt-4 text-xs muted">{{ count($profile->pengaturan['tools'] ?? []) }} tool · dipakai {{ $profile->scans_count }} scan</p>
            @can('scans.run')
                <a href="{{ route('scans.create', ['profile' => $profile->id]) }}" class="text-link mt-4 flex items-center gap-2" wire:navigate>Gunakan di wizard<x-jejaksa.icon name="arrow" size="15" /></a>
            @endcan
        </article>
    @empty
        <div class="panel md:col-span-2 xl:col-span-3"><div class="empty"><h3>Belum ada profil scan</h3><p>Buat profil untuk memilih tool yang dijalankan.</p></div></div>
    @endforelse
</div>
