<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Workspace — Jejaksa' }}</title>

        <!-- Fonts -->
        <link rel="icon" href="{{ asset('brand/jejaksa-favicon-32.png') }}">
        <link rel="preload" href="{{ Vite::asset('resources/fonts/plus-jakarta-sans/PlusJakartaSans-Variable.ttf') }}" as="font" type="font/ttf" crossorigin>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <a class="skip-link" href="#main-content">Lewati ke konten</a>
        <div class="min-h-screen" x-data="{ navigationOpen: false, isMobile: window.innerWidth < 768 }" @resize.window="isMobile = window.innerWidth < 768" @keydown.escape.window="if(navigationOpen) { navigationOpen = false; $nextTick(() => document.getElementById('open-workspace-navigation')?.focus()); }">
            <livewire:layout.navigation />
            <div class="workspace-body" :inert="isMobile && navigationOpen">
                <header class="topbar">
                    <div class="flex items-center gap-3"><button id="open-workspace-navigation" type="button" class="icon-btn md:hidden" @click="navigationOpen = true; $nextTick(() => document.getElementById('close-workspace-navigation')?.focus())" aria-label="Buka navigasi" :aria-expanded="navigationOpen" aria-controls="workspace-navigation"><x-jejaksa.icon name="menu" /></button><a class="text-sm muted" href="{{ route('dashboard') }}" wire:navigate>Workspace <span class="mx-2 opacity-50">/</span> <span class="text-slate-200">Jejaksa Lab</span></a></div>
                    <div class="flex items-center gap-4"><a href="{{ route('settings') }}" class="hidden items-center gap-2 text-xs muted lg:flex"><span class="live-dot"></span> Lingkungan demo</a><span class="h-5 border-l border-slate-700"></span><a href="{{ route('profile') }}" aria-label="Profil {{ auth()->user()->name }}" wire:navigate><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span></a></div>
                </header>
                <div class="demo-strip"><span><strong>Mode demo — data simulasi</strong> <span class="hidden sm:inline">· Alur dapat dicoba; scanner dan AI belum terhubung.</span></span><span>F0 / Frontend preview</span></div>
                <main id="main-content" class="workspace-main" tabindex="-1">@if(isset($header))<div class="mb-6">{{ $header }}</div>@endif{{ $slot }}</main>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
