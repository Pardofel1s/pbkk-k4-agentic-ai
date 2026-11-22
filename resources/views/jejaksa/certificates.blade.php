<div class="page-head">
    <div>
        <h1 class="page-title">Sertifikat</h1>
        <p class="page-description">"Sertifikat Lulus Uji Penetrasi" untuk re-test yang tidak lagi menyisakan temuan terbuka.</p>
    </div>
</div>
<div class="grid gap-6 xl:grid-cols-[380px_1fr]">
    <section class="panel p-6">
        <h2 class="font-semibold">Syarat penerbitan</h2>
        <ul class="mt-5 space-y-4 text-sm">
            <li class="flex gap-3"><x-jejaksa.icon name="check" class="shrink-0 text-teal-300" size="18" />Scan adalah re-test dan sudah selesai.</li>
            <li class="flex gap-3"><x-jejaksa.icon name="check" class="shrink-0 text-teal-300" size="18" />Tidak ada temuan berstatus terbuka atau terkonfirmasi.</li>
            <li class="flex gap-3"><x-jejaksa.icon name="lock" class="shrink-0 text-teal-300" size="18" />Diterbitkan oleh admin, satu sertifikat per scan.</li>
        </ul>
        <h3 class="mb-3 mt-8 text-xs muted">RE-TEST SELESAI TANPA SERTIFIKAT</h3>
        @forelse($candidates as $scan)
            <div class="flex items-center justify-between gap-3 border-t border-slate-700 py-3 text-sm">
                <a class="min-w-0" href="{{ route('scans.show', $scan) }}" wire:navigate>
                    <span class="block truncate">{{ $scan->target->nama }}</span>
                    <span class="block text-xs {{ $scan->unresolved_count ? 'text-amber-200' : 'muted' }}">Scan #{{ $scan->id }} · {{ $scan->unresolved_count }} temuan belum ditutup</span>
                </a>
                @can('create', App\Models\Certificate::class)
                    <button type="button" class="btn-primary shrink-0 text-xs" wire:click="issueCertificate({{ $scan->id }})" @disabled($scan->unresolved_count) wire:loading.attr="disabled">Terbitkan</button>
                @endcan
            </div>
        @empty
            <p class="text-sm muted">Belum ada re-test yang selesai.</p>
        @endforelse
    </section>
    <div class="space-y-6">
        @forelse($certificates as $certificate)
            <section class="relative overflow-hidden rounded-xl border border-teal-800 bg-[#0d2029] p-8 text-center md:p-12">
                <img class="relative mx-auto w-36" src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" width="144" height="51">
                <p class="mt-8 text-[10px] uppercase tracking-[.3em] text-teal-200">{{ $certificate->nomor }}</p>
                <h2 class="mt-5 font-serif text-3xl">Sertifikat Lulus Uji Penetrasi</h2>
                <div class="relative my-8 border-y border-teal-800/70 py-8">
                    <p class="text-xs muted">Diberikan kepada</p>
                    <p class="mt-3 text-2xl font-semibold">{{ $certificate->scan->target->nama }}</p>
                    <p class="mt-2 text-xs text-teal-200">{{ $certificate->scan->target->base_url }}</p>
                    <p class="mx-auto mt-6 max-w-md text-xs leading-7 muted">Re-test #{{ $certificate->scan_id }} tidak menyisakan temuan terbuka pada ruang lingkup dan waktu pengujian. Sertifikat tidak menyatakan aplikasi bebas dari seluruh kerentanan.</p>
                </div>
                <div class="flex justify-between gap-4 text-left text-[10px] muted">
                    <span>Diterbitkan: {{ $certificate->issued_at->translatedFormat('d M Y') }}</span>
                    <span>Berlaku hingga: {{ $certificate->due_date?->translatedFormat('d M Y') ?? '—' }}</span>
                </div>
            </section>
        @empty
            <div class="panel"><div class="empty"><x-jejaksa.icon name="award" class="mx-auto muted" size="30" /><h3>Belum ada sertifikat</h3><p>Perbaiki temuan, jalankan re-test, lalu admin menerbitkan sertifikat.</p></div></div>
        @endforelse
    </div>
</div>
