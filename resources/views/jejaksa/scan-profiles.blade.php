<div class="page-head">
    <div>
        <h1 class="page-title">Profil scan</h1>
        <p class="page-description">Paket pemeriksaan yang konsisten untuk setiap tujuan pengujian.</p>
    </div>
    <button wire:loading.attr="disabled" type="button" class="btn-primary" wire:click="$toggle('showProfileForm')">
        <x-jejaksa.icon name="plus" size="17" />Profil baru</button>
</div>
@if($showProfileForm)
    <form wire:submit="saveProfile" class="panel mb-6 p-6">
        <h2 class="mb-5 font-semibold">Profil demo baru</h2>
        <div class="grid gap-5 md:grid-cols-2">
            <div class="field">
                <label for="profile-name">Nama profil</label>
                <input id="profile-name" wire:model="profileForm.name" required maxlength="80">
            </div>
            <div class="field">
                <label for="profile-description">Deskripsi</label>
                <input id="profile-description" wire:model="profileForm.description" maxlength="300">
            </div>
        </div>
        <fieldset class="mt-5">
            <legend class="mb-3 text-sm">Tool yang direncanakan</legend>
            <div class="flex flex-wrap gap-5">
                @foreach(['HTTP Headers','TLS Check','URL Discovery'] as $tool)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="profileForm.tools" value="{{ $tool }}" class="rounded">{{ $tool }}</label>
                @endforeach
            </div>
        </fieldset>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn-primary">Simpan profil demo</button>
            <button wire:loading.attr="disabled" type="button" class="btn-secondary" wire:click="$set('showProfileForm',false)">Batal</button>
        </div>
    </form>
@endif
<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
    @foreach($state['profiles'] as $profile)
        <article class="panel flex flex-col p-6">
            <div class="flex items-center justify-between">
                <span class="feature-icon mb-0">
                    <x-jejaksa.icon name="settings" />
                </span>
                <x-jejaksa.badge :value="$profile['active'] ? 'active' : 'draft'" />
            </div>
            <h2 class="mt-6 text-lg font-semibold">{{ $profile['name'] }}</h2>
            <p class="mt-2 min-h-12 text-sm muted">{{ $profile['description'] }}</p>
            <div class="my-5 flex flex-wrap gap-2">
                @foreach($profile['tools'] as $tool)
                    <span class="badge badge-neutral">{{ $tool }}</span>
                @endforeach
            </div>
            <p class="mt-auto border-t border-slate-700 pt-4 text-xs muted">{{ count($profile['tools']) }} tool direncanakan · eksekusi demo</p>
            <a href="{{ route('scans.create', ['profile' => $profile['id']]) }}" class="text-link mt-4 flex items-center gap-2" wire:navigate>Gunakan di wizard<x-jejaksa.icon name="arrow" size="15" />
            </a>
        </article>
    @endforeach
</div>
