<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Laporan Jejaksa · {{ $scan->target->nama }} · v{{ $report->versi }}</title>
        <style>body{font:15px/1.7 'Segoe UI',sans-serif;color:#0f172a;max-width:850px;margin:48px auto;padding:0 24px}h1{font-size:32px}h2{margin-top:32px}article{border-top:1px solid #cbd5e1;padding:18px 0}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f1f5f9;padding:16px;font-size:12px}.muted{color:#475569}table{border-collapse:collapse}td{padding:2px 16px 2px 0}footer{margin-top:40px;border-top:1px solid #cbd5e1;padding-top:20px;font-size:12px}@media print{body{margin:0;max-width:none}article{break-inside:avoid}}</style>
    </head>
    <body>
        <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('brand/jejaksa-logo-light.svg'))) }}" alt="jejaksa" width="144" height="51">
        <p class="muted">LAPORAN PENGUJIAN KEAMANAN WEB (DAST)</p>
        <h1>{{ $scan->target->nama }}</h1>
        <p>{{ $scan->target->base_url }}</p>
        <p>Versi {{ $report->versi }} · dibuat {{ $report->generated_at->translatedFormat('d M Y, H:i') }} · Scan #{{ $scan->id }}{{ $scan->retest_of_id ? ' (re-test scan #'.$scan->retest_of_id.')' : '' }}</p>
        <h2>Ruang lingkup</h2>
        <table>
            <tr><td class="muted">Lingkungan</td><td>{{ $scan->target->lingkungan }}</td></tr>
            <tr><td class="muted">Profil scan</td><td>{{ $scan->scanProfile->nama }} ({{ implode(', ', $scan->scanProfile->pengaturan['tools'] ?? []) }})</td></tr>
            <tr><td class="muted">Waktu scan</td><td>{{ $scan->started_at?->translatedFormat('d M Y, H:i') ?? '—' }} – {{ $scan->finished_at?->translatedFormat('H:i') ?? '—' }}</td></tr>
        </table>
        <h2>Ringkasan</h2>
        <p>
            Scan menemukan {{ $findings->count() }} temuan:
            @foreach(['critical' => 'kritis', 'high' => 'tinggi', 'medium' => 'sedang', 'low' => 'rendah', 'info' => 'informasi'] as $severity => $label)
                @if($count = $findings->where('severity', $severity)->count()){{ $count }} {{ $label }}{{ $loop->last ? '' : ',' }} @endif
            @endforeach
        </p>
        <h2>Temuan & rekomendasi</h2>
        @forelse($findings->sortBy(fn ($f) => array_search($f->severity, ['critical', 'high', 'medium', 'low', 'info'])) as $finding)
            <article>
                <h3>{{ $finding->vulnerabilityType->nama }} <span class="muted">({{ $finding->vulnerabilityType->kode_cwe }} · {{ $finding->vulnerabilityType->kategori_owasp }})</span></h3>
                <p>Risiko: {{ $finding->severity }} · Status: {{ $finding->status }}</p>
                <p>Lokasi: {{ $finding->url_terdampak }}{{ $finding->parameter ? ' · '.$finding->parameter : '' }}</p>
                <p>{{ $finding->vulnerabilityType->deskripsi }}</p>
                @if($finding->proof)<pre>{{ $finding->proof }}</pre>@endif
                <p><strong>Rekomendasi:</strong> {{ $finding->rekomendasi ?: $finding->vulnerabilityType->rekomendasi }}</p>
            </article>
        @empty
            <p>Tidak ada temuan dari tool yang dijalankan pada profil ini.</p>
        @endforelse
        <footer>Berlaku hanya untuk ruang lingkup dan waktu yang tercantum. Hasil pemindaian otomatis tidak menjamin aplikasi bebas dari kerentanan; verifikasi temuan secara manual. Jejaksa · Setiap celah, jelas jejaknya.</footer>
    </body>
</html>
