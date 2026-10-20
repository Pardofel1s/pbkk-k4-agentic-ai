@php($scan = $state['scans'][$report['scan']])
@php($target = $report['target_snapshot'] ?? $state['targets'][$scan['target']])
@php($findings = $report['findings_snapshot'] ?? collect($state['findings'])->where('scan',$report['scan'])->all())
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Laporan contoh Jejaksa · {{ $target['name'] }}</title>
        <style>body{font:15px/1.7 'Segoe UI',sans-serif;color:#0f172a;max-width:850px;margin:48px auto;padding:0 24px}h1{font-size:32px}h2{margin-top:32px}.label{border:2px solid #92400e;background:#fef3c7;padding:12px;font-weight:700}article{border-top:1px solid #cbd5e1;padding:18px 0}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f1f5f9;padding:16px;font-size:12px}.muted{color:#475569}footer{margin-top:40px;border-top:1px solid #cbd5e1;padding-top:20px;font-size:12px}@media print{body{margin:0;max-width:none}article{break-inside:avoid}.label{background:white}}</style>
    </head>
    <body>
        <div class="label">CONTOH — DATA SIMULASI · BUKAN HASIL PENGUJIAN NYATA</div>
        <img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('brand/jejaksa-logo-light.svg'))) }}" alt="jejaksa" width="144" height="51" style="margin-top:28px">
        <p class="muted">LAPORAN PENGUJIAN WEB / CONTOH</p>
        <h1>{{ $target['name'] }}</h1>
        <p>{{ $target['url'] }}</p>
        <p>Versi {{ $report['version'] }} · {{ $report['created'] }} · {{ strtoupper($report['id']) }}</p>
        <h2>Scope snapshot</h2>
        <pre>{{ $scan['scope'] }}</pre>
        <p>Dikecualikan: {{ $scan['excluded'] ?: 'Tidak ada' }}</p>
        <h2>Ringkasan</h2>
        <p>Dokumen contoh berisi {{ count($findings) }} temuan simulasi. Tidak ada scanner dijalankan terhadap target. Gunakan menu Cetak pada browser untuk menyimpan salinan PDF contoh.</p>
        <h2>Temuan & rekomendasi</h2>
        @foreach($findings as $finding)
            <article>
                <h3>{{ $finding['title'] }}</h3>
                <p>Risiko: {{ $finding['severity'] }} · Review: {{ $finding['status'] }}</p>
                <p>Lokasi: {{ $finding['location'] }}</p>
                <pre>{{ $finding['evidence'] }}</pre>
                <p>{{ $finding['recommendation'] }}</p>
                <p class="muted">Catatan: {{ $finding['note'] ?: 'Belum ditinjau.' }}</p>
            </article>
        @endforeach
        <footer>CONTOH — DATA SIMULASI. Berlaku hanya pada scope yang dicantumkan. Tidak menjamin bebas kerentanan; bukan sertifikat atau laporan pengujian produksi. Jejaksa · Setiap celah, jelas jejaknya.</footer>
    </body>
</html>
