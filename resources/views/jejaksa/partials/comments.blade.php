<section class="panel">
    <div class="panel-heading">
        <h2 class="text-sm font-semibold">Diskusi</h2>
        <span class="text-xs muted">{{ $comments->count() }} komentar</span>
    </div>
    <div class="space-y-4 p-6">
        @forelse($comments as $note)
            <article class="rounded-lg border {{ str_starts_with($note->isi, App\Actions\AnalyzeReport::PREFIX) ? 'border-teal-700 bg-teal-950/20' : 'border-slate-700' }} p-4">
                <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                    <span class="font-medium">{{ $note->user->name }} <span class="muted">· {{ $note->created_at->translatedFormat('d M Y, H:i') }}</span></span>
                    @can('delete', $note)
                        <button type="button" class="text-link" wire:click="deleteComment({{ $note->id }})" wire:confirm="Hapus komentar ini?">Hapus</button>
                    @endcan
                </div>
                <div class="chat-md text-sm">{!! \Illuminate\Support\Str::markdown($note->isi, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
            </article>
        @empty
            <p class="text-sm muted">Belum ada komentar.</p>
        @endforelse
        @can('create', [App\Models\Comment::class, $item])
            <form wire:submit="addComment" class="space-y-3 border-t border-slate-700 pt-4">
                <label for="comment" class="sr-only">Komentar</label>
                <textarea id="comment" wire:model="comment" rows="3" maxlength="2000" required placeholder="Tulis pertanyaan, konfirmasi perbaikan, atau catatan…"></textarea>
                <button type="submit" class="btn-secondary text-xs" wire:loading.attr="disabled">Kirim komentar</button>
            </form>
        @endcan
    </div>
</section>
