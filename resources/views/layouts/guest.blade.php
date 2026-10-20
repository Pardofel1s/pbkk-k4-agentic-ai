<!DOCTYPE html>
<html lang="id">
    <head>
        <x-jejaksa.head title="Akses workspace — Jejaksa" />
    </head>
    <body class="antialiased">
        <a class="skip-link" href="#main-content">Lewati ke formulir</a>
        <div class="auth-layout">
            <aside class="auth-story">
                <a href="{{ route('home') }}" wire:navigate>
                    <img class="brand-lockup" src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" width="142" height="50">
                </a>
                <div class="my-auto py-16">
                    <p class="eyebrow mb-6">A little clarity. A safer web.</p>
                    <h1>Setiap celah,<br>
                        <span class="text-teal-300">jelas jejaknya.</span>
                    </h1>
                    <p class="mt-6 max-w-sm text-sm leading-7 muted">Satu ruang untuk mengelola target, mengikuti pengujian, dan mengubah bukti menjadi langkah perbaikan.</p>
                    <div class="mt-12 flex items-center gap-3 text-xs text-teal-200">
                        <x-jejaksa.icon name="shield" size="18" />Terarah. Terlacak. Dapat ditinjau.</div>
                </div>
                <p class="text-xs muted">Jejaksa · Proyek PBKK Kelompok 4</p>
            </aside>
            <main id="main-content" class="auth-main legacy-form">
                <div class="auth-form">
                    <a href="{{ route('home') }}" class="mb-10 block lg:hidden" wire:navigate>
                        <img class="brand-lockup" src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" width="142" height="50">
                    </a>{{ $slot }}<div class="mt-8 border-t border-slate-700 pt-5 text-center text-xs muted">
                        <a class="text-link" href="{{ route('home') }}" wire:navigate>← Kembali ke beranda</a>
                        <p class="mt-4">Workspace saat ini menggunakan data simulasi.</p>
                    </div>
                </div>
            </main>
        </div>
        @livewireScripts
    </body>
</html>
