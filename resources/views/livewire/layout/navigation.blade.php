<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div x-cloak x-show="navigationOpen" class="fixed inset-0 z-30 bg-black/70 md:hidden" @click="navigationOpen = false; $nextTick(() => document.getElementById('open-workspace-navigation')?.focus())" aria-hidden="true"></div>
    <aside id="workspace-navigation" class="sidebar" :inert="isMobile && !navigationOpen" :class="{ 'is-open': navigationOpen }" @keydown.tab="if (isMobile && navigationOpen) { const items = [...$el.querySelectorAll('a,button')].filter(el => el.offsetParent !== null); if ($event.shiftKey && document.activeElement === items[0]) { $event.preventDefault(); items.at(-1).focus(); } else if (!$event.shiftKey && document.activeElement === items.at(-1)) { $event.preventDefault(); items[0].focus(); } }">
        <button id="close-workspace-navigation" type="button" class="icon-btn absolute right-2 top-2 md:hidden" @click="navigationOpen = false; $nextTick(() => document.getElementById('open-workspace-navigation')?.focus())" aria-label="Tutup navigasi"><x-jejaksa.icon name="close" /></button>
        <a href="{{ route('home') }}" aria-label="Jejaksa beranda" wire:navigate><img class="brand-lockup" src="{{ asset('brand/jejaksa-logo-dark.svg') }}" alt="jejaksa" width="150" height="53"></a>
        @php($user = auth()->user())
        <div class="workspace-picker"><span class="flex h-8 w-8 items-center justify-center rounded-md border border-teal-700 bg-teal-950 text-teal-200"><x-jejaksa.icon name="shield" size="17" /></span><div><div class="text-xs font-semibold">Jejaksa Lab</div><div class="text-[10px] muted capitalize">Workspace {{ $user->getRoleNames()->implode(', ') }}</div></div></div>
        <nav aria-label="Navigasi workspace" class="flex-1 overflow-y-auto">
            @foreach([
                'Workspace' => [['dashboard','grid','Ringkasan',true],['targets.index','target','Target',$user->can('viewAny', App\Models\Target::class)],['scans.index','scan','Pemindaian',true],['findings.index','shield','Temuan',true],['agent.chat','sparkles','Asisten AI',$user->can('create', App\Models\ChatSession::class)]],
                'Output' => [['reports.index','file','Laporan',true],['certificates.index','award','Sertifikat',true]],
                'Konfigurasi' => [['scan-profiles.index','settings','Profil scan',$user->can('targets.view-any')],['tools.index','tool','Katalog tool',$user->can('targets.view-any')],['vulnerability-types.index','code','Jenis kerentanan',true],['users.index','users','Pengguna & akses',true]],
            ] as $group => $items)
                <p class="nav-group">{{ $group }}</p>
                @foreach(array_filter($items, fn ($item) => $item[3]) as [$route, $icon, $label])
                    @php($active = request()->routeIs(in_array($route, ['dashboard', 'agent.chat'], true) ? $route : str_replace('.index', '.*', $route)))
                    <a href="{{ route($route) }}" wire:navigate class="nav-item {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif><x-jejaksa.icon :name="$icon" size="18" /><span>{{ $label }}</span></a>
                @endforeach
            @endforeach
        </nav>
        <div class="mt-4 border-t border-slate-800 pt-3"><a class="nav-item {{ request()->routeIs('settings', 'profile') ? 'active' : '' }}" href="{{ route('settings') }}" wire:navigate><x-jejaksa.icon name="settings" size="18" />Pengaturan</a><button type="button" wire:click="logout" class="nav-item w-full"><x-jejaksa.icon name="logout" size="18" />Keluar</button></div>
        <div class="mt-3 flex items-center gap-3 border-t border-slate-800 px-3 pt-4"><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span><div class="min-w-0"><p class="truncate text-xs font-medium" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" @profile-updated.window="name = $event.detail.name"></p><p class="truncate text-[10px] muted">{{ auth()->user()->email }}</p></div></div>
    </aside>
</div>
