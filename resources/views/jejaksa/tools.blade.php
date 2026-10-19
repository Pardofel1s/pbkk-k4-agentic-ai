<div class="page-head">
    <div>
        <h1 class="page-title">Katalog tool</h1>
        <p class="page-description">Pisahkan izin penggunaan dari kesiapan eksekusi.</p>
    </div>
    <span class="badge badge-neutral">Konfigurasi demo</span>
</div>
<div class="info-box mb-6">Aktif berarti dipilih dalam katalog demo. Siap berarti dependensi, versi, dan health check sudah lolos. Integrasi tool belum tersedia; mengaktifkan tool tidak menjalankannya.</div>
<div class="grid gap-5 lg:grid-cols-3">
    @foreach($state['tools'] as $id=>$tool)
        <article class="panel p-6">
            <span class="feature-icon">
                <x-jejaksa.icon name="tool" />
            </span>
            <h2 class="text-lg font-semibold">{{ $tool['name'] }}</h2>
            <p class="mt-2 min-h-12 text-sm muted">{{ $tool['description'] }}</p>
            <dl class="mt-6 space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <dt class="muted">Status katalog</dt>
                    <dd>
                        <x-jejaksa.badge :value="$tool['active'] ? 'active' : 'draft'" :label="$tool['active'] ? 'Aktif' : 'Nonaktif'" />
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="muted">Kesiapan eksekusi</dt>
                    <dd class="text-amber-200">Belum siap</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="muted">Versi / adapter</dt>
                    <dd>{{ $tool['version'] }}</dd>
                </div>
            </dl>
            <button wire:loading.attr="disabled" type="button" class="btn-secondary mt-6 w-full text-xs" wire:click="toggleTool('{{ $id }}')">{{ $tool['active'] ? 'Nonaktifkan di demo' : 'Aktifkan di demo' }}</button>
        </article>
    @endforeach
</div>
