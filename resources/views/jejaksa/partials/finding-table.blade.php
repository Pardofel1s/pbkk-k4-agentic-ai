<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th>Temuan</th>
                <th>Risiko</th>
                <th>Target</th>
                <th>Status</th>
                <th><span class="sr-only">Buka</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($findings as $finding)
                <tr>
                    <td>
                        <a class="row-title" href="{{ route('findings.show', $finding) }}" wire:navigate>{{ $finding->vulnerabilityType->nama }}</a>
                        <p class="mt-1 text-[10px] muted">#{{ $finding->id }} · {{ $finding->vulnerabilityType->kode_cwe }}{{ $finding->parameter ? ' · '.$finding->parameter : '' }}</p>
                    </td>
                    <td><x-jejaksa.badge :value="$finding->severity" /></td>
                    <td class="text-xs muted">{{ $finding->scan->target->nama }}</td>
                    <td><x-jejaksa.badge :value="$finding->status" /></td>
                    <td>
                        <a class="icon-btn" href="{{ route('findings.show', $finding) }}" aria-label="Buka temuan #{{ $finding->id }}" wire:navigate><x-jejaksa.icon name="chevron" size="16" /></a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="empty">
                            <x-jejaksa.icon name="shield" class="mx-auto muted" size="30" />
                            <h3>Tidak ada temuan yang cocok</h3>
                            <p>Ubah pencarian atau filter. Tidak adanya temuan bukan jaminan target bebas kerentanan.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
