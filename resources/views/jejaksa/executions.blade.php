<a href="{{ route('scans.show', $item) }}" class="text-link" wire:navigate>← Kembali ke pemindaian</a>
<div class="page-head mt-4">
    <div>
        <h1 class="page-title">Detail eksekusi tool</h1>
        <p class="page-description">Scan #{{ $item->id }} · {{ $item->target->nama }} · argumen dan output setiap tool yang dijalankan.</p>
    </div>
    <x-jejaksa.badge :value="$item->status" />
</div>
<div class="space-y-5">
    @forelse($toolCalls as $call)
        <section class="panel">
            <div class="panel-heading">
                <h2 class="font-semibold mono">{{ $call->tool->nama }}</h2>
                <span class="flex items-center gap-3 text-xs muted">{{ $call->created_at->translatedFormat('H:i:s') }} · {{ $call->updated_at->diffInSeconds($call->created_at, true) }} dtk
                    <x-jejaksa.badge :value="$call->status === 'success' ? 'completed' : ($call->status === 'failed' ? 'failed' : 'running')" :label="$call->status" /></span>
            </div>
            <div class="grid gap-5 p-6 lg:grid-cols-[1fr_2fr]">
                <div>
                    <h3 class="mb-3 text-xs muted">ARGUMEN</h3>
                    <pre class="rounded-lg border border-slate-700 bg-slate-950/40 p-4 text-teal-100">{{ json_encode($call->parameter, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
                <div>
                    <h3 class="mb-3 text-xs muted">OUTPUT</h3>
                    <pre class="rounded-lg border border-slate-700 bg-slate-950/40 p-4">{{ $call->hasil['output'] ?? $call->error_msg ?? '—' }}</pre>
                </div>
            </div>
        </section>
    @empty
        <div class="panel"><div class="empty"><h3>Belum ada tool yang dijalankan</h3><p>Output muncul di sini setelah worker antrean menjalankan scan.</p></div></div>
    @endforelse
</div>
