@props(['title' => 'Panel admin', 'heading' => null, 'actions' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => $title.' · Admin'])
</head>
@php
    $user = auth()->user();
    $nav = [
        ['admin.dashboard', 'ri-dashboard-3-line', 'Ringkasan', 'manage-content', 'admin.dashboard'],
        ['admin.campaigns.index', 'ri-image-2-line', 'Kampanye', 'manage-content', 'admin.campaigns.*'],
        ['admin.announcements.index', 'ri-megaphone-line', 'Pengumuman', 'manage-content', 'admin.announcements.*'],
        ['admin.apps.index', 'ri-apps-2-line', 'Aplikasi', 'admin', 'admin.apps.*'],
        ['admin.users.index', 'ri-team-line', 'Pengguna', 'admin', 'admin.users.*'],
        ['admin.mentari.index', 'ri-robot-2-line', 'Mentari', 'admin', 'admin.mentari.*'],
        ['admin.settings', 'ri-settings-4-line', 'Pengaturan', 'admin', 'admin.settings*'],
    ];
@endphp
<body class="min-h-svh bg-pit" x-data="{ menu: false }">
    {{-- Bilah atas (HP) --}}
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-white/[0.06] bg-pit/90 px-4 py-3 backdrop-blur-xl lg:hidden">
        <button type="button" @click="menu = true" class="icon-btn -ml-1" aria-label="Buka menu"><i class="ri-menu-2-line text-2xl"></i></button>
        <span class="grow font-display text-lg font-semibold">{{ $heading ?? $title }}</span>
        <a href="{{ route('home') }}" class="icon-btn" aria-label="Ke portal"><i class="ri-home-5-line text-xl"></i></a>
    </header>

    <div x-show="menu" x-cloak x-transition.opacity @click="menu = false" class="fixed inset-0 z-40 bg-black/70 lg:hidden"></div>

    {{-- Menu samping --}}
    <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col border-r border-white/[0.06] bg-bench transition-transform lg:translate-x-0">
        <div class="relative overflow-hidden px-5 pt-6 pb-5">
            <x-contour class="absolute -top-10 -right-24 h-40 w-80 opacity-40" />
            <a href="{{ route('admin.dashboard') }}" class="relative flex items-center gap-3">
                <img src="/images/plant-asto.png" alt="" class="h-10 w-auto">
                <span class="leading-tight">
                    <span class="block font-display text-lg font-bold">{{ $siteName }}</span>
                    <span class="block text-xs text-mist">Panel admin</span>
                </span>
            </a>
        </div>
        <nav class="grow space-y-0.5 overflow-y-auto px-3" aria-label="Menu admin">
            @foreach ($nav as [$route, $icon, $label, $ability, $pattern])
                @can($ability)
                    <a href="{{ route($route) }}" class="side-link" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                        <i class="{{ $icon }}"></i>{{ $label }}
                    </a>
                @endcan
            @endforeach
        </nav>
        <div class="border-t border-white/[0.06] p-3">
            <a href="{{ route('home') }}" class="side-link"><i class="ri-smartphone-line"></i>Lihat portal</a>
            <div class="mt-2 flex items-center gap-3 rounded-xl bg-white/[0.03] px-3 py-2.5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-neon font-display text-sm font-bold text-[#04170d]">{{ $user->initials() }}</span>
                <span class="min-w-0 grow leading-tight">
                    <span class="block truncate text-sm font-semibold">{{ $user->displayName() }}</span>
                    <span class="block text-xs text-mist">{{ $user->roleLabel() }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-btn" aria-label="Keluar" title="Keluar"><i class="ri-logout-box-r-line text-lg"></i></button></form>
            </div>
        </div>
    </aside>

    <main class="lg:pl-[260px]">
        <div class="mx-auto max-w-[1200px] px-4 py-6 lg:px-8 lg:py-8">
            <div class="mb-6 hidden items-center gap-4 lg:flex">
                <h1 class="grow font-display text-[1.75rem] font-bold">{{ $heading ?? $title }}</h1>
                {{ $actions }}
            </div>
            @if ($actions)
                <div class="mb-5 flex flex-wrap gap-2 lg:hidden">{{ $actions }}</div>
            @endif

            @if ($errors->any() && ! ($hideErrors ?? false))
                <div class="mb-5 flex gap-3 rounded-2xl border border-alarm/30 bg-alarm/10 p-4 text-sm">
                    <i class="ri-error-warning-fill text-xl text-alarm"></i>
                    <div>
                        <p class="font-semibold">Periksa kembali isian berikut:</p>
                        <ul class="mt-1 list-disc pl-5 text-fog">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                </div>
            @endif

            {{ $slot }}
        </div>
    </main>

    @include('partials.toast')
</body>
</html>
