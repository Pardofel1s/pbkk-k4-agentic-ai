<form wire:submit="saveTarget" class="grid gap-6 xl:grid-cols-[1fr_300px]">
    <div class="panel p-6">
        <h2 class="mb-6 text-base font-semibold">Informasi target</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field">
                <label for="target-name">Nama target</label>
                <input id="target-name" wire:model="targetForm.name" required maxlength="100" placeholder="Contoh: Fintech Sandbox">
            </div>
            <div class="field">
                <label for="environment">Lingkungan</label>
                <select id="environment" wire:model="targetForm.environment">
                    <option>Staging</option>
                    <option>Development</option>
                    <option>Production</option>
                </select>
            </div>
            <div class="field sm:col-span-2">
                <label for="target-url">Base URL</label>
                <input id="target-url" type="url" wire:model="targetForm.url" required placeholder="https://staging.example.test">
                <p class="text-xs muted">Gunakan HTTP/HTTPS. Jangan sertakan password atau token di URL.</p>
            </div>
            <div class="field sm:col-span-2">
                <label for="description">Deskripsi</label>
                <textarea id="description" rows="2" wire:model="targetForm.description" maxlength="1000" placeholder="Tujuan dan konteks target ini">
                </textarea>
            </div>
            <div class="field">
                <label for="scope">Path dalam scope</label>
                <textarea id="scope" rows="3" class="mono" wire:model="targetForm.scope" required placeholder="/">
                </textarea>
                <p class="text-xs muted">Satu path per baris; / mencakup base URL.</p>
            </div>
            <div class="field">
                <label for="excluded">Path yang dikecualikan</label>
                <textarea id="excluded" rows="3" class="mono" wire:model="targetForm.excluded" placeholder="/logout&#10;/payments/confirm">
                </textarea>
            </div>
        </div>
        <label class="mt-6 flex items-start gap-3 rounded-lg border border-slate-600 p-4">
            <input type="checkbox" wire:model="targetForm.authorized" class="mt-1 rounded">
            <span>
                <span class="block text-sm">Saya memiliki izin untuk menguji target ini.</span>
                <span class="mt-1 block text-xs font-normal muted">Pernyataan dicatat hanya untuk simulasi frontend. Target tanpa izin dapat disimpan sebagai draf.</span>
            </span>
        </label>
        <div class="mt-7 flex flex-wrap justify-end gap-3 border-t border-slate-700 pt-5">
            <a class="btn-secondary" href="{{ route('targets.index') }}" wire:navigate>Batal</a>
            <button class="btn-primary" type="submit" wire:loading.attr="disabled">Simpan target demo<x-jejaksa.icon name="check" size="16" />
            </button>
        </div>
    </div>
    <aside class="space-y-4">
        <div class="info-box">
            <x-jejaksa.icon name="shield" class="mb-3" />
            <h3 class="font-semibold">Scope adalah batas.</h3>
            <p class="mt-2 leading-6">Masukkan hanya lingkungan yang Anda miliki atau diizinkan untuk diuji. Path yang dikecualikan harus tetap di luar pengujian.</p>
        </div>
        <div class="panel p-5 text-xs leading-6 muted">
            <h3 class="mb-2 font-semibold text-slate-200">Mode demo</h3>URL disimpan sebagai teks. Menyimpan target tidak menghubungi host atau menjalankan scanner.</div>
    </aside>
</form>
