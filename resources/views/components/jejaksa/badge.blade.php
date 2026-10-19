@props(['value', 'label' => null])
@php
 $tone = match ($value) {
     'critical' => 'critical', 'high', 'failed' => 'danger', 'medium', 'queued', 'cancel_requested', 'fixed_pending_retest' => 'warning',
     'low', 'running', 'ready', 'info' => 'info', 'completed', 'active', 'confirmed', 'resolved', 'success' => 'success', default => 'neutral',
 };
 $labels = ['critical'=>'Kritis','high'=>'Tinggi','medium'=>'Sedang','low'=>'Rendah','info'=>'Informasi','completed'=>'Selesai','running'=>'Berjalan','queued'=>'Antrean','cancel_requested'=>'Pembatalan diminta','cancelled'=>'Dibatalkan','failed'=>'Gagal','active'=>'Aktif','ready'=>'Siap','draft'=>'Draf','unreviewed'=>'Belum ditinjau','confirmed'=>'Terkonfirmasi','false_positive'=>'False positive','fixed_pending_retest'=>'Menunggu retest','resolved'=>'Terselesaikan'];
@endphp
<span {{ $attributes->class(['badge', 'badge-'.$tone]) }}>{{ $label ?? ($labels[$value] ?? $value) }}</span>
