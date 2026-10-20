<div class="page-head">
    <div>
        <h1 class="page-title">Sertifikat</h1>
        <p class="page-description">Kelayakan yang dapat diperiksa, sebelum hasil dibagikan.</p>
    </div>
    <span class="badge badge-warning">Penerbitan belum tersedia</span>
</div>
<div class="info-box mb-6">Pratinjau sertifikat selalu berwatermark CONTOH. Penerbitan memerlukan validasi backend, persetujuan berwenang, dan hasil retest yang terverifikasi.</div>
<div class="grid gap-6 xl:grid-cols-[340px_1fr]">
    <section class="panel p-6">
        <h2 class="font-semibold">Pemeriksaan kelayakan</h2>
        <p class="mt-2 text-xs muted">Fintech Sandbox · Scan S1</p>
        <ul class="mt-6 space-y-5 text-sm">
            <li class="flex gap-3">
                <x-jejaksa.icon name="check" class="shrink-0 text-green-300" size="18" />
                <span>Scan selesai <span class="block text-xs muted">Hanya status simulasi.</span>
                </span>
            </li>
            <li class="flex gap-3">
                <x-jejaksa.icon name="alert" class="shrink-0 text-amber-200" size="18" />
                <span>Review & risiko terbuka<span class="mt-1 block text-xs muted">{{ collect($state['findings'])->where('scan','s1')->whereNotIn('status',['false_positive','resolved'])->count() }} temuan belum ditutup dengan verifikasi.</span>
                </span>
            </li>
            <li class="flex gap-3">
                <x-jejaksa.icon name="lock" class="shrink-0 muted" size="18" />
                <span>Retest dan persetujuan backend<span class="mt-1 block text-xs muted">Belum tersedia pada tahap frontend.</span>
                </span>
            </li>
        </ul>
        <button type="button" class="btn-secondary mt-8 w-full" disabled aria-describedby="certificate-blocker">
            <x-jejaksa.icon name="lock" size="16" />Terbitkan sertifikat</button>
        <p id="certificate-blocker" class="mt-3 text-xs leading-6 muted">Penerbitan terkunci sampai seluruh syarat dan permission khusus diverifikasi oleh backend.</p>
        <a href="{{ route('findings.index') }}" class="text-link mt-5 inline-block" wire:navigate>Tinjau temuan →</a>
    </section>
    <section class="relative overflow-hidden rounded-xl border border-teal-800 bg-[#0d2029] p-8 text-center md:p-14">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 flex rotate-[-25deg] items-center justify-center text-[64px] font-bold tracking-widest text-white/[0.04]">CONTOH</div>
        <img class="relative mx-auto w-36" src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" width="144" height="51">
        <p class="mt-8 text-[10px] uppercase tracking-[.3em] text-teal-200">Pratinjau dokumen · Data simulasi</p>
        <h2 class="mt-5 font-serif text-3xl">Ringkasan Hasil Pengujian</h2>
        <p class="mt-3 text-xs muted">CONTOH — TIDAK BERLAKU SEBAGAI SERTIFIKAT</p>
        <div class="relative my-10 border-y border-teal-800/70 py-8">
            <p class="text-xs muted">Target contoh</p>
            <p class="mt-3 text-2xl font-semibold">Fintech Sandbox</p>
            <p class="mt-2 text-xs text-teal-200">staging.example.test</p>
            <p class="mx-auto mt-6 max-w-md text-xs leading-7 muted">Dokumen hanya merangkum scope dan kondisi pada waktu pengujian yang dicantumkan. Tidak menyatakan aplikasi 100% aman atau bebas dari seluruh kerentanan.</p>
        </div>
        <div class="flex justify-between gap-4 text-left text-[10px] muted">
            <span>Scope: /<br>Waktu: belum diterbitkan</span>
            <span>Peninjau: belum disetujui<br>Verifikasi: tidak tersedia</span>
        </div>
    </section>
</div>
