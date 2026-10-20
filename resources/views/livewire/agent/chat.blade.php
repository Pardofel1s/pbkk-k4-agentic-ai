<?php

use App\Models\ChatSession;
use App\Models\Scan;
use App\Models\Target;
use App\Services\Agent\StartAgentTurn;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $sessionId = null;

    public int $targetId = 0;

    public string $prompt = '';

    public function mount(): void
    {
        // Default to the local demo target so the agent has a real host to test.
        $this->targetId = (int) $this->scannableTargets()->value('id');
        $this->sessionId = (int) ($this->sessions()->value('id') ?: 0) ?: null;
    }

    public function startSession(): void
    {
        $this->authorize('create', ChatSession::class);

        $this->sessionId = auth()->user()->chatSessions()->create([
            'model_llm' => config('llm.model'),
        ])->id;
        $this->prompt = '';
    }

    public function send(StartAgentTurn $agent): void
    {
        $session = $this->currentSession();
        abort_if($session === null, 404);

        $target = Target::query()->findOrFail($this->targetId);

        try {
            $agent->handle(auth()->user(), $session, $target, $this->prompt);
        } catch (ValidationException $e) {
            throw $e;
        }

        $this->prompt = '';
    }

    public function with(): array
    {
        $session = $this->currentSession();

        return [
            'sessions' => $this->sessions()->get(),
            'targets' => $this->scannableTargets()->get(),
            'session' => $session,
            'messages' => $session
                ? $session->chatMessages()->with(['toolCalls.tool'])->oldest('id')->get()
                : collect(),
            'busy' => $session ? StartAgentTurn::isBusy($session) : false,
        ];
    }

    private function sessions()
    {
        return auth()->user()->chatSessions()->latest('id');
    }

    private function scannableTargets()
    {
        return Target::query()->where('status_verifikasi', Target::VERIFIED)->orderByDesc('id');
    }

    private function currentSession(): ?ChatSession
    {
        if ($this->sessionId === null) {
            return null;
        }

        $session = ChatSession::query()->find($this->sessionId);

        if ($session === null || auth()->user()->cannot('view', $session)) {
            return null;
        }

        return $session;
    }
}; ?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-title">Asisten AI · Live</h1>
            <p class="page-description">Prompt dikirim ke {{ config('llm.model') }} (Senopati). Agent memanggil tool DAST pada target berizin.</p>
        </div>
        @can('create', App\Models\ChatSession::class)
            <button wire:click="startSession" wire:loading.attr="disabled" type="button" class="btn-primary">
                <x-jejaksa.icon name="plus" size="17" />Sesi baru
            </button>
        @endcan
    </div>

    <div class="rounded-lg border border-teal-700 bg-teal-950/30 p-3 text-xs text-teal-100 mb-6">
        Mode live — terhubung ke LLM dan tool nyata. Hanya target berstatus <strong>verified</strong> yang bisa diuji.
    </div>

    @error('prompt')
        <div role="alert" class="mb-4 rounded-lg border border-amber-500/50 bg-amber-950/30 p-3 text-sm text-amber-200">{{ $message }}</div>
    @enderror

    <div class="grid gap-5 lg:grid-cols-[260px_1fr]">
        <aside class="panel p-3">
            <h2 class="px-3 py-3 text-xs font-semibold muted">SESI SAYA</h2>
            @forelse($sessions as $item)
                <button type="button" wire:click="$set('sessionId', {{ $item->id }})"
                    class="mb-2 block w-full rounded-lg border p-3 text-left {{ $sessionId === $item->id ? 'border-teal-700 bg-teal-950/40' : 'border-transparent hover:border-slate-600' }}">
                    <p class="truncate text-sm">{{ $item->judul ?? 'Sesi #'.$item->id }}</p>
                    <p class="mt-1 text-[10px] muted">{{ $item->created_at?->diffForHumans() }}</p>
                </button>
            @empty
                <p class="px-3 text-xs muted">Belum ada sesi.</p>
            @endforelse
        </aside>

        <section class="panel min-w-0">
            @if($session === null)
                <div class="empty py-24">
                    <h2 class="text-2xl font-semibold tracking-tight">Mulai sesi pengujian</h2>
                    <p>Buat sesi baru, pilih target berizin, lalu tulis tujuan pengujian.</p>
                </div>
            @else
                <div class="panel-heading">
                    <div>
                        <h2 class="text-sm font-semibold">{{ $session->judul ?? 'Sesi #'.$session->id }}</h2>
                        <p class="mt-1 text-[10px] muted">Model: {{ $session->model_llm }}</p>
                    </div>
                    @if($busy)<x-jejaksa.badge value="warning" label="Agent memproses…" />@endif
                </div>

                <div @if($busy) wire:poll.3s @endif class="space-y-6 p-5 md:p-7" aria-label="Riwayat percakapan">
                    @forelse($messages as $message)
                        <div>
                            <p class="mb-2 text-[10px] uppercase tracking-widest muted {{ $message->role === 'user' ? 'text-right' : '' }}">
                                {{ $message->role === 'user' ? 'Anda' : 'Jejaksa' }}
                            </p>
                            @if($message->isi !== '')
                                <div class="chat-bubble {{ $message->role === 'user' ? 'user' : '' }} whitespace-pre-wrap">{{ $message->isi }}</div>
                            @endif
                            @foreach($message->toolCalls as $toolCall)
                                <div class="mt-2 rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-xs">
                                    <p class="font-semibold text-teal-200">tool · {{ $toolCall->tool?->nama ?? '—' }} <span class="muted">({{ $toolCall->status }})</span></p>
                                    @if($toolCall->hasil)
                                        <pre class="mt-2 overflow-x-auto whitespace-pre-wrap text-[11px] text-slate-300">{{ \Illuminate\Support\Str::limit(($toolCall->hasil['output'] ?? json_encode($toolCall->hasil)), 1200) }}</pre>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <p class="py-10 text-center text-sm muted">Tulis tujuan pengujian untuk memulai.</p>
                    @endforelse
                    @if($busy)
                        <p class="text-center text-xs muted">Agent sedang memanggil tool…</p>
                    @endif
                </div>

                <form wire:submit="send" class="border-t border-slate-700 p-5">
                    <label for="agent-target" class="block text-[10px] uppercase tracking-widest muted mb-1">Target</label>
                    <select id="agent-target" wire:model="targetId" class="mb-3 w-full rounded-lg bg-slate-900 text-sm">
                        @foreach($targets as $target)
                            <option value="{{ $target->id }}">{{ $target->nama }} — {{ $target->base_url }}</option>
                        @endforeach
                    </select>
                    <label for="agent-prompt" class="sr-only">Pesan untuk agent</label>
                    <textarea id="agent-prompt" wire:model="prompt" rows="2" required maxlength="4000"
                        placeholder="mis. Periksa header keamanan HTTP pada target ini." @disabled($busy)></textarea>
                    <div class="mt-3 flex items-center justify-between gap-4">
                        <p class="text-[10px] muted">Jangan masukkan kredensial. Tool hanya berjalan pada target berizin.</p>
                        <button type="submit" class="btn-primary shrink-0 text-xs" wire:loading.attr="disabled" @disabled($busy)>
                            Kirim<x-jejaksa.icon name="arrow" size="16" />
                        </button>
                    </div>
                </form>
            @endif
        </section>
    </div>
</div>
