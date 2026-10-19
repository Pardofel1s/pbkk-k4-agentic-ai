<a href="{{ route('scans.show',$item['id']) }}" class="text-link" wire:navigate>← Kembali ke pemindaian</a>
<div class="page-head mt-4">
    <div>
        <h1 class="page-title">Detail eksekusi tool</h1>
        <p class="page-description">Jejak terstruktur untuk aktivitas pemeriksaan header contoh.</p>
    </div>
    <x-jejaksa.badge :value="$item['status']" label="Eksekusi simulasi" />
</div>
<div class="grid gap-6 lg:grid-cols-[1fr_300px]">
    <section class="panel">
        <div class="panel-heading">
            <h2 class="font-semibold">HTTP Headers</h2>
            <span class="mono text-xs muted">{{ strtoupper($item['id']) }} / TOOL-01</span>
        </div>
        <div class="p-6">
            <h3 class="mb-3 text-xs muted">ARGUMEN CONTOH</h3>
            <pre class="rounded-lg border border-slate-700 bg-slate-950/40 p-4 text-teal-100">{{ json_encode(['target'=>$state['targets'][$item['target']]['url'],'scope'=>$item['scope'],'mode'=>'demo','external_execution'=>false], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            <h3 class="mb-3 mt-6 text-xs muted">KELUARAN SIMULASI</h3>
            <pre class="rounded-lg border border-slate-700 bg-slate-950/40 p-4">[DEMO] Status: {{ $item['status'] }}
                @if($item['status'] === 'completed')[DEMO] HTTP 200 · Content-Type: text/html
                    [DEMO] Content-Security-Policy: [tidak ditemukan]
                    [DEMO] Output contoh; bukan respons jaringan nyata.@else[DEMO] Bukti belum tersedia pada tahap ini.@endif
                    [DEMO] Tidak ada command shell yang dieksekusi.</pre>
            </div>
        </section>
        <aside class="panel h-fit p-6">
            <h2 class="font-semibold">Konteks</h2>
            <dl class="mt-5 space-y-5 text-xs">
                <div>
                    <dt class="mb-1 muted">Scan sumber</dt>
                    <dd>{{ strtoupper($item['id']) }}</dd>
                </div>
                <div>
                    <dt class="mb-1 muted">Waktu contoh</dt>
                    <dd>{{ $item['created'] }}</dd>
                </div>
                <div>
                    <dt class="mb-1 muted">Sumber</dt>
                    <dd>{{ $item['source'] }}</dd>
                </div>
                <div>
                    <dt class="mb-1 muted">Kode keluar nyata</dt>
                    <dd>Tidak tersedia · simulasi</dd>
                </div>
                <div>
                    <dt class="mb-1 muted">Durasi nyata</dt>
                    <dd>Tidak diukur</dd>
                </div>
            </dl>
            <p class="mt-6 border-t border-slate-700 pt-4 text-xs leading-6 muted">Kredensial harus disamarkan pada adapter backend. UI menampilkan output sebagai teks, tidak sebagai HTML.</p>
        </aside>
    </div>
