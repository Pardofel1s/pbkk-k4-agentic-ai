<div class="page-head">
    <div>
        <h1 class="page-title">Asisten AI</h1>
        <p class="page-description">Dari tujuan pengujian menuju rencana yang dapat ditinjau.</p>
    </div>
    <button wire:loading.attr="disabled" type="button" wire:click="newChat" class="btn-primary">
        <x-jejaksa.icon name="plus" size="17" />Sesi baru</button>
</div>
<div class="info-box mb-6">Asisten demo menggunakan respons contoh. Tidak ada pesan dikirim ke LLM. Rencana contoh hanya memeriksa header pada Fintech Sandbox.</div>
<div class="grid gap-5 lg:grid-cols-[260px_1fr]">
    <aside class="panel p-3">
        <h2 class="px-3 py-3 text-xs font-semibold muted">PERCAKAPAN</h2>
        @forelse(array_reverse($state['chats']) as $chat)
            <a class="mb-2 block rounded-lg border p-3 {{ $record === $chat['id'] ? 'border-teal-700 bg-teal-950/40' : 'border-transparent hover:border-slate-600' }}" href="{{ route('chat.show',$chat['id']) }}" wire:navigate>
                <p class="truncate text-sm">{{ $chat['title'] }}</p>
                <p class="mt-1 text-[10px] muted">{{ $state['targets'][$chat['target']]['name'] }} · {{ count($chat['messages']) }} pesan</p>
            </a>
        @empty
            <p class="px-3 text-xs muted">Belum ada percakapan.</p>
        @endforelse
    </aside>
    <section class="panel min-w-0">
        @if(!$item)
            <div class="empty py-24">
                <span class="feature-icon">
                    <x-jejaksa.icon name="sparkles" />
                </span>
                <h2 class="text-2xl font-semibold tracking-tight">Apa yang ingin Anda periksa?</h2>
                <p>Pilih percakapan atau buat sesi baru. Tinjau rencana sebelum mengonfirmasi pengujian simulasi.</p>
                <button wire:loading.attr="disabled" type="button" class="btn-secondary" wire:click="newChat">Mulai sesi demo<x-jejaksa.icon name="arrow" size="16" />
                </button>
            </div>
        @else
            <div class="panel-heading">
                <div>
                    <h2 class="text-sm font-semibold">{{ $item['title'] }}</h2>
                    <p class="mt-1 text-[10px] muted">Target tetap: {{ $state['targets'][$item['target']]['name'] }}</p>
                </div>
                <x-jejaksa.badge value="info" label="Respons demo" />
            </div>
            <div class="space-y-6 p-5 md:p-7" aria-label="Riwayat percakapan">
                @forelse($item['messages'] as $chatMessage)
                    <div>
                        <p class="mb-2 text-[10px] uppercase tracking-widest muted {{ $chatMessage['role'] === 'user' ? 'text-right' : '' }}">{{ $chatMessage['role'] === 'user' ? 'Anda' : 'Jejaksa · Demo' }}</p>
                        <div class="chat-bubble {{ $chatMessage['role'] === 'user' ? 'user' : '' }} whitespace-pre-wrap">{{ $chatMessage['text'] }}</div>
                    </div>
                @empty
                    <div class="py-10 text-center">
                        <x-jejaksa.icon name="sparkles" class="mx-auto mb-4 text-teal-300" size="28" />
                        <p class="text-sm">Mulai dengan tujuan pengujian Anda.</p>
                        <button wire:loading.attr="disabled" type="button" class="btn-secondary mt-5 text-xs" wire:click="$set('message','Periksa header target demo.')">Periksa header target demo.</button>
                    </div>
                @endforelse
                @if($item['plan'])
                    <div class="rounded-xl border border-teal-700 bg-teal-950/20 p-5">
                        <p class="eyebrow text-[10px]">Rencana contoh · Menunggu konfirmasi</p>
                        <h3 class="mt-3 text-sm font-semibold">Pemeriksaan header Fintech Sandbox</h3>
                        <ol class="mt-4 list-inside list-decimal space-y-2 text-xs muted">
                            <li>Periksa izin dan scope {{ $state['targets'][$item['target']]['scope'] }}.</li>
                            <li>Simulasikan tool HTTP Headers menggunakan profil Baseline Web.</li>
                            <li>Catat bukti contoh dan tinjau rekomendasi.</li>
                        </ol>
                        <p class="mt-4 text-xs muted">Target: {{ $state['targets'][$item['target']]['url'] }} · Tidak menjalankan perintah bebas.</p>
                        <button wire:loading.attr="disabled" type="button" wire:click="confirmPlan" class="btn-primary mt-5 text-xs" wire:confirm="Buat scan simulasi berdasarkan rencana ini?">Setujui & buat scan demo<x-jejaksa.icon name="arrow" size="15" />
                        </button>
                    </div>
                @endif
            </div>
            <form wire:submit="sendMessage" class="border-t border-slate-700 p-5">
                <label for="chat-message" class="sr-only">Pesan untuk asisten demo</label>
                <textarea id="chat-message" wire:model="message" rows="2" required maxlength="2000" placeholder="Jelaskan tujuan pengujian Anda…">
                </textarea>
                <div class="mt-3 flex items-center justify-between gap-4">
                    <p class="text-[10px] muted">Jangan masukkan password, token, atau data nasabah.</p>
                    <button type="submit" class="btn-primary shrink-0 text-xs" wire:loading.attr="disabled">Kirim<x-jejaksa.icon name="arrow" size="16" />
                    </button>
                </div>
            </form>
        @endif
    </section>
</div>
