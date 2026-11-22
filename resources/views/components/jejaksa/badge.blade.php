@props(['value', 'label' => null])
@php
 $tone = match ($value) {
     'critical' => 'critical', 'high', 'failed', 'rejected' => 'danger', 'medium', 'queued', 'pending', 'open' => 'warning',
     'low', 'running', 'info' => 'info', 'completed', 'active', 'confirmed', 'fixed', 'verified', 'success' => 'success', default => 'neutral',
 };
 $labels = ['critical'=>'Kritis','high'=>'Tinggi','medium'=>'Sedang','low'=>'Rendah','info'=>'Informasi','completed'=>'Selesai','running'=>'Berjalan','queued'=>'Antrean','cancelled'=>'Dibatalkan','failed'=>'Gagal','active'=>'Aktif','inactive'=>'Nonaktif','open'=>'Terbuka','confirmed'=>'Terkonfirmasi','false_positive'=>'False positive','fixed'=>'Sudah diperbaiki','pending'=>'Menunggu verifikasi','verified'=>'Terverifikasi','rejected'=>'Ditolak'];
@endphp
<span {{ $attributes->class(['badge', 'badge-'.$tone]) }}>{{ $label ?? ($labels[$value] ?? $value) }}</span>
