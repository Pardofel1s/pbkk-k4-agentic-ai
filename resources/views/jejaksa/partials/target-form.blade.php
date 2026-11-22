<form wire:submit="saveTarget" class="grid gap-6 xl:grid-cols-[1fr_300px]">
    <div class="panel p-6">
        <h2 class="mb-6 text-base font-semibold">Informasi target</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field">
                <label for="target-name">Nama target</label>
                <input id="target-name" wire:model="targetForm.nama" required maxlength="100" placeholder="Contoh: Portal Nasabah Staging">
            </div>
            <div class="field">
                <label for="environment">Lingkungan</label>
                <select id="environment" wire:model="targetForm.lingkungan">
                    <option value="staging">Staging</option>
                    <option value="uat">UAT</option>
                </select>
                <p class="text-xs muted">Hanya staging/UAT: pengujian tidak boleh mengganggu pengguna produksi.</p>
            </div>
            <div class="field sm:col-span-2">
                <label for="target-url">Base URL</label>
                <input id="target-url" type="url" wire:model="targetForm.base_url" required placeholder="https://staging.contoh.co.id">
                <p class="text-xs muted">Gunakan HTTP/HTTPS. Jangan sertakan password atau token di URL.</p>
            </div>
            <div class="field">
                <label for="scope-include">Domain tambahan dalam scope</label>
                <textarea id="scope-include" rows="3" class="mono" wire:model="targetForm.include" placeholder="api.contoh.co.id&#10;*.staging.contoh.co.id"></textarea>
                <p class="text-xs muted">Satu pola per baris. Host base URL selalu termasuk.</p>
            </div>
            <div class="field">
                <label for="scope-exclude">Domain yang dikecualikan</label>
                <textarea id="scope-exclude" rows="3" class="mono" wire:model="targetForm.exclude" placeholder="payment.contoh.co.id"></textarea>
                <p class="text-xs muted">Pengecualian selalu menang atas pola dalam scope.</p>
            </div>
        </div>
        <div class="mt-7 flex flex-wrap justify-end gap-3 border-t border-slate-700 pt-5">
            <a class="btn-secondary" href="{{ $record ? route('targets.show', $record) : route('targets.index') }}" wire:navigate>Batal</a>
            <button class="btn-primary" type="submit" wire:loading.attr="disabled">Simpan target<x-jejaksa.icon name="check" size="16" /></button>
        </div>
    </div>
    <aside class="space-y-4">
        <div class="info-box">
            <x-jejaksa.icon name="shield" class="mb-3" />
            <h3 class="font-semibold">Scope adalah batas.</h3>
            <p class="mt-2 leading-6">Tool dan agent AI menolak request ke host di luar scope, termasuk lewat redirect.</p>
        </div>
        <div class="panel p-5 text-xs leading-6 muted">
            <h3 class="mb-2 font-semibold text-slate-200">Alur verifikasi</h3>
            @can('targets.verify')
                Target yang Anda buat sebagai admin langsung terverifikasi.
            @else
                Target baru berstatus <strong>menunggu verifikasi</strong>. Setelah admin memverifikasi izin pengujian, target dikunci dan analyst bisa mulai memindai.
            @endcan
        </div>
    </aside>
</form>
