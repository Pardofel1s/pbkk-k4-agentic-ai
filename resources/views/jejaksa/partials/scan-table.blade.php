<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th>Pemindaian / target</th>
                <th>Status</th>
                <th>Dibuat</th>
                <th><span class="sr-only">Buka</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($scans as $scan)
                <tr>
                    <td>
                        <a class="row-title" href="{{ route('scans.show', $scan) }}" wire:navigate>{{ $scan->target->nama }}</a>
                        <p class="mt-1 text-[10px] muted">#{{ $scan->id }} · {{ $scan->scanProfile->nama }}{{ $scan->retest_of_id ? ' · re-test #'.$scan->retest_of_id : '' }}</p>
                    </td>
                    <td><x-jejaksa.badge :value="$scan->status" /></td>
                    <td class="text-xs muted whitespace-nowrap">{{ $scan->created_at->translatedFormat('d M Y, H:i') }}</td>
                    <td>
                        <a class="icon-btn" aria-label="Buka pemindaian #{{ $scan->id }}" href="{{ route('scans.show', $scan) }}" wire:navigate><x-jejaksa.icon name="chevron" size="16" /></a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <div class="empty">
                            <x-jejaksa.icon name="scan" class="mx-auto muted" size="30" />
                            <h3>Belum ada pemindaian</h3>
                            <p>Pemindaian dijalankan analyst pada target yang sudah terverifikasi.</p>
                            @can('scans.run')
                                <a class="btn-primary" href="{{ route('scans.create') }}" wire:navigate>Siapkan scan</a>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
