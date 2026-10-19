<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th>Temuan</th>
                <th>Risiko</th>
                <th>Target</th>
                <th>Review</th>
                <th>
                    <span class="sr-only">Buka</span>
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse($findings as $finding)
                <tr>
                    <td>
                        <a class="row-title" href="{{ route('findings.show',$finding['id']) }}" wire:navigate>{{ $finding['title'] }}</a>
                        <p class="mt-1 text-[10px] muted">{{ strtoupper($finding['id']) }} · {{ $finding['type'] }}</p>
                    </td>
                    <td>
                        <x-jejaksa.badge :value="$finding['severity']" />
                    </td>
                    <td class="text-xs muted">{{ $state['targets'][$finding['target']]['name'] }}</td>
                    <td>
                        <x-jejaksa.badge :value="$finding['status']" />
                    </td>
                    <td>
                        <a class="icon-btn" href="{{ route('findings.show',$finding['id']) }}" aria-label="Buka {{ $finding['title'] }}" wire:navigate>
                            <x-jejaksa.icon name="chevron" size="16" />
                        </a>
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
