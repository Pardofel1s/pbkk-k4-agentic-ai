<div class="page-head">
    <div>
        <h1 class="page-title">Katalog tool</h1>
        <p class="page-description">Tool DAST yang bisa dijalankan scan dan agent AI.</p>
    </div>
</div>
<div class="info-box mb-6">Tool nonaktif ditolak oleh scan dan agent AI. Semua tool hanya mengirim request ke host di dalam scope target.</div>
<div class="grid gap-5 lg:grid-cols-3">
    @foreach($tools as $tool)
        <article class="panel flex flex-col p-6">
            <span class="feature-icon"><x-jejaksa.icon name="tool" /></span>
            <h2 class="text-lg font-semibold mono">{{ $tool->nama }}</h2>
            <p class="mt-2 min-h-12 text-sm muted">{{ $tool->deskripsi }}</p>
            <dl class="mt-6 space-y-4 text-xs">
                <div class="flex items-center justify-between"><dt class="muted">Status</dt><dd><x-jejaksa.badge :value="$tool->status ? 'active' : 'inactive'" /></dd></div>
                <div class="flex items-center justify-between"><dt class="muted">Dijalankan</dt><dd>{{ $tool->tool_calls_count }} kali</dd></div>
            </dl>
            @can('users.manage')
                <button wire:loading.attr="disabled" type="button" class="btn-secondary mt-auto w-full text-xs" wire:click="toggleTool({{ $tool->id }})">{{ $tool->status ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            @endcan
        </article>
    @endforeach
</div>
