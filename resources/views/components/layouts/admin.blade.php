@props(['title' => 'Panel admin', 'heading' => null, 'actions' => null, 'hideHeading' => false, 'subtitle' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => $title.' · Admin'])
</head>
@php
    $user = auth()->user();
    // [route, ikon, label, izin, pola aktif]
    $groups = [
        'Utama' => [
            ['admin.dashboard', 'ri-dashboard-3-line', 'Ringkasan', 'manage-content', 'admin.dashboard'],
        ],
        'Absensi' => [
            ['admin.attendance.index', 'ri-calendar-event-line', 'Sesi & event', 'host-attendance', ['admin.attendance.index', 'admin.attendance.show', 'admin.attendance.create', 'admin.attendance.edit', 'admin.attendance.screen']],
            ['admin.attendance.recap', 'ri-bar-chart-2-line', 'Rekap', 'host-attendance', 'admin.attendance.recap*'],
            ['admin.attendance.schedules', 'ri-repeat-line', 'Jadwal rutin', 'manage-content', 'admin.attendance.schedules*'],
            ['admin.attendance.locations', 'ri-map-pin-range-line', 'Area GPS', 'manage-content', 'admin.attendance.locations*'],
        ],
        'Poin HEBAT' => [
            ['admin.points.index', 'ri-coin-line', 'Ringkasan poin', 'manage-points', 'admin.points.index'],
            ['admin.points.grants.index', 'ri-trophy-line', 'Pemberian poin', 'manage-points', 'admin.points.grants.*'],
            ['admin.points.redemptions.index', 'ri-coupon-3-line', 'Kupon & penyerahan', 'manage-points', 'admin.points.redemptions.*'],
            ['admin.points.rewards.index', 'ri-gift-2-line', 'Katalog hadiah', 'manage-points', 'admin.points.rewards.*'],
            ['admin.points.ledger', 'ri-file-list-3-line', 'Riwayat & aturan', 'manage-points', ['admin.points.ledger*', 'admin.points.rules*']],
        ],
        'Ujian Event' => [
            ['admin.exams.index', 'ri-medal-2-line', 'Event ujian', 'manage-exams', ['admin.exams.index', 'admin.exams.show', 'admin.exams.create', 'admin.exams.edit', 'admin.exams.attempt']],
            ['admin.exams.bank', 'ri-question-answer-line', 'Bank soal', 'manage-exams', 'admin.exams.bank*'],
        ],
        'Konten' => [
            ['admin.campaigns.index', 'ri-image-2-line', 'Kampanye', 'manage-content', 'admin.campaigns.*'],
            ['admin.announcements.index', 'ri-megaphone-line', 'Pengumuman', 'manage-content', 'admin.announcements.*'],
        ],
        'Sistem' => [
            ['admin.apps.index', 'ri-apps-2-line', 'Aplikasi', 'admin', 'admin.apps.*'],
            ['admin.users.index', 'ri-team-line', 'Pengguna', 'admin', 'admin.users.*'],
            ['admin.mentari.index', 'ri-robot-2-line', 'Mentari', 'admin', 'admin.mentari.*'],
            ['admin.attendance.types', 'ri-price-tag-3-line', 'Jenis kegiatan', 'admin', 'admin.attendance.types*'],
            ['admin.settings', 'ri-settings-4-line', 'Pengaturan', 'admin', 'admin.settings*'],
        ],
    ];
    // Palet perintah (Ctrl+K): menu + aksi cepat yang boleh diakses
    $palette = collect($groups)->flatMap(fn ($items, $group) => collect($items)->map(fn ($i) => [$i[2], $i[0], $i[1], $i[3], $group]))
        ->merge([
            ['Kampanye baru', 'admin.campaigns.create', 'ri-add-circle-line', 'manage-content', 'Aksi cepat'],
            ['Pengumuman baru', 'admin.announcements.create', 'ri-megaphone-line', 'manage-content', 'Aksi cepat'],
            ['Event absensi baru', 'admin.attendance.create', 'ri-calendar-event-line', 'host-attendance', 'Aksi cepat'],
            ['Cetak poster QR absensi', 'admin.attendance.poster', 'ri-printer-line', 'host-attendance', 'Aksi cepat'],
            ['Tambah pengguna', 'admin.users.create', 'ri-user-add-line', 'admin', 'Aksi cepat'],
            ['Tambah aplikasi', 'admin.apps.create', 'ri-apps-line', 'admin', 'Aksi cepat'],
            ['Beri poin untuk event', 'admin.points.grants.create', 'ri-trophy-line', 'manage-points', 'Aksi cepat'],
            ['Tambah hadiah', 'admin.points.rewards.create', 'ri-gift-2-line', 'manage-points', 'Aksi cepat'],
            ['Buat ujian event', 'admin.exams.create', 'ri-medal-2-line', 'manage-exams', 'Aksi cepat'],
            ['Impor soal dari Excel', 'admin.exams.bank.import', 'ri-file-excel-2-line', 'manage-exams', 'Aksi cepat'],
            ['Aturan poin otomatis', 'admin.points.rules', 'ri-settings-3-line', 'manage-points', 'Aksi cepat'],
            ['Buka portal', 'home', 'ri-home-5-line', 'host-attendance', 'Aksi cepat'],
        ])
        ->filter(fn ($i) => $user->can($i[3]))
        ->map(fn ($i) => ['label' => $i[0], 'href' => route($i[1]), 'icon' => $i[2], 'group' => $i[4]])->values();
    $search = array_filter([
        $user->can('admin') ? ['label' => 'Cari pengguna', 'href' => route('admin.users.index'), 'icon' => 'ri-user-search-line'] : null,
        $user->can('admin') ? ['label' => 'Cari aplikasi', 'href' => route('admin.apps.index'), 'icon' => 'ri-search-eye-line'] : null,
        $user->can('manage-exams') ? ['label' => 'Cari di bank soal', 'href' => route('admin.exams.bank'), 'icon' => 'ri-question-answer-line'] : null,
        $user->can('manage-points') ? ['label' => 'Cari kupon / riwayat poin', 'href' => route('admin.points.ledger'), 'icon' => 'ri-coin-line'] : null,
        $user->can('host-attendance') ? ['label' => 'Cari di rekap absensi', 'href' => route('admin.attendance.recap'), 'icon' => 'ri-file-search-line'] : null,
    ]);
    $pendingCoupons = $user->can('manage-points') ? \App\Models\Redemption::where('status', 'pending')->count() : 0;
    $pendingGrants = $user->can('manage-points') ? \App\Models\PointGrant::where('status', 'pending')->count() : 0;
    $liveExams = $user->can('manage-exams') ? \App\Models\Exam::openNow()->count() : 0;
    $liveSessions = $user->can('host-attendance') ? \App\Models\AttendanceSession::openNow()->visibleTo($user)->count() : 0;
    $hero = \App\Models\Setting::hero();
@endphp
<body class="min-h-svh bg-pit" x-data="{ menu: false }" @keydown.window.prevent.ctrl.k="$dispatch('palette-open')" @keydown.window.prevent.meta.k="$dispatch('palette-open')">
    {{-- Bilah atas (HP) --}}
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-white/[0.06] bg-pit/90 px-4 py-3 backdrop-blur-xl lg:hidden">
        <button type="button" @click="menu = true" class="icon-btn -ml-1" aria-label="Buka menu"><i class="ri-menu-2-line text-2xl"></i></button>
        <span class="grow font-display text-lg font-semibold">{{ $heading ?? $title }}</span>
        <a href="{{ route('home') }}" class="icon-btn" aria-label="Ke portal"><i class="ri-home-5-line text-xl"></i></a>
    </header>

    <div x-show="menu" x-cloak x-transition.opacity @click="menu = false" class="fixed inset-0 z-40 bg-black/70 lg:hidden"></div>

    {{-- Menu samping --}}
    <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 flex w-[264px] flex-col border-r border-white/[0.06] bg-bench transition-transform lg:translate-x-0">
        <div class="relative overflow-hidden px-5 pt-6 pb-6">
            <img src="{{ $hero['src'] }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-45">
            <div class="absolute inset-0 bg-gradient-to-b from-bench/40 via-bench/70 to-bench"></div>
            <x-contour class="absolute -top-10 -right-24 h-40 w-80 opacity-40 mix-blend-screen" />
            <a href="{{ $user->canManageContent() ? route('admin.dashboard') : route('admin.attendance.index') }}" class="relative flex items-center gap-3">
                <img src="/images/plant-asto.png" alt="" class="h-10 w-auto">
                <span class="leading-tight">
                    <span class="block font-display text-lg font-bold">{{ $siteName }}</span>
                    <span class="block text-xs text-mist">{{ $user->canManageContent() ? 'Panel admin' : 'Panel absensi' }}</span>
                </span>
            </a>
        </div>
        <nav class="grow overflow-y-auto px-3 pb-3 lg:[&_.side-link]:py-2" aria-label="Menu admin">
            @foreach ($groups as $group => $items)
                @php $visible = collect($items)->filter(fn ($i) => $user->can($i[3])); @endphp
                @if ($visible->isNotEmpty())
                    <p class="px-3 pt-3.5 pb-1 text-[11px] font-semibold tracking-wide text-mist/70">{{ $group }}</p>
                    <div class="space-y-0.5">
                        @foreach ($visible as [$route, $icon, $label, $ability, $pattern])
                            <a href="{{ route($route) }}" class="side-link" @if (request()->routeIs(...(array) $pattern)) aria-current="page" @endif>
                                <i class="{{ $icon }}"></i><span class="grow">{{ $label }}</span>
                                @if ($route === 'admin.points.redemptions.index' && $pendingCoupons)
                                    <span class="rounded-full bg-hivis/15 px-2 py-0.5 text-[11px] font-semibold text-hivis">{{ $pendingCoupons }}</span>
                                @endif
                                @if ($route === 'admin.points.grants.index' && $pendingGrants)
                                    <span class="rounded-full bg-hivis/15 px-2 py-0.5 text-[11px] font-semibold text-hivis">{{ $pendingGrants }}</span>
                                @endif
                                @if ($route === 'admin.exams.index' && $liveExams)
                                    <span class="flex items-center gap-1 rounded-full bg-hivis/15 px-2 py-0.5 text-[11px] font-semibold text-hivis"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-hivis"></span>{{ $liveExams }}</span>
                                @endif
                                @if ($route === 'admin.attendance.index' && $liveSessions)
                                    <span class="flex items-center gap-1 rounded-full bg-neon/15 px-2 py-0.5 text-[11px] font-semibold text-neon"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-neon"></span>{{ $liveSessions }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
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

    <main class="lg:pl-[264px]">
        {{-- Bilah atas (desktop) --}}
        <div class="sticky top-0 z-30 hidden h-16 items-center gap-4 border-b border-white/[0.06] bg-pit/80 px-8 backdrop-blur-xl lg:flex">
            <nav class="flex min-w-0 items-center gap-1.5 text-sm text-mist" aria-label="Jejak navigasi">
                <a href="{{ $user->canManageContent() ? route('admin.dashboard') : route('admin.attendance.index') }}" class="hover:text-ink">Panel</a>
                <i class="ri-arrow-right-s-line"></i><span class="truncate text-fog">{{ $heading ?? $title }}</span>
            </nav>
            <button type="button" @click="$dispatch('palette-open')" class="mx-auto flex h-10 w-full max-w-[420px] items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] px-3.5 text-sm text-mist transition hover:border-neon/40 hover:text-fog">
                <i class="ri-search-line text-lg"></i><span class="grow text-left">Cari menu, pengguna, aplikasi…</span>
                <kbd class="rounded-md border border-white/15 px-1.5 py-0.5 font-display text-[11px]">Ctrl K</kbd>
            </button>
            <div class="flex shrink-0 items-center gap-2">
                @if ($liveSessions)
                    <a href="{{ route('admin.attendance.index') }}" class="flex items-center gap-2 rounded-full bg-neon/12 px-3 py-1.5 text-sm font-semibold text-neon ring-1 ring-neon/30 transition hover:bg-neon/20">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-neon shadow-[0_0_8px_var(--color-neon)]"></span>{{ $liveSessions }} absen dibuka
                    </a>
                @endif
                <a href="{{ route('home') }}" class="btn-ghost !min-h-10 !px-3.5 text-sm"><i class="ri-external-link-line"></i> Portal</a>
                <span class="grid h-9 w-9 place-items-center rounded-full bg-neon font-display text-sm font-bold text-[#04170d]" title="{{ $user->displayName() }} · {{ $user->roleLabel() }}">{{ $user->initials() }}</span>
            </div>
        </div>

        <div class="mx-auto max-w-[1360px] px-4 py-6 lg:px-8 lg:py-8">
            <div class="mb-6 hidden items-end gap-4 {{ $hideHeading ? '' : 'lg:flex' }}">
                <div class="grow">
                    <h1 class="font-display text-[2rem] leading-tight font-bold">{{ $heading ?? $title }}</h1>
                    @if ($subtitle) <p class="mt-1 text-fog">{{ $subtitle }}</p> @endif
                </div>
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

    {{-- Palet perintah --}}
    <template x-teleport="body">
        <div x-data="adminPalette(@js($palette), @js(array_values($search)))" @palette-open.window="show()" x-show="open" x-cloak @keydown.escape.window="open = false"
             class="fixed inset-0 z-[60] flex items-start justify-center bg-black/60 px-4 pt-[12vh] backdrop-blur-sm" @click.self="open = false">
            <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-white/10 bg-bench shadow-2xl shadow-black/60" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()">
                <div class="flex items-center gap-3 border-b border-white/[0.07] px-4">
                    <i class="ri-search-line text-xl text-mist"></i>
                    <input x-ref="q" x-model="q" @input="cursor = 0" type="text" placeholder="Ketik menu, aksi, NRP, atau nama aplikasi…" class="h-14 grow bg-transparent text-[15px] outline-none placeholder:text-mist">
                    <kbd class="rounded-md border border-white/15 px-1.5 py-0.5 font-display text-[11px] text-mist">Esc</kbd>
                </div>
                <ul class="max-h-[50vh] overflow-y-auto p-2">
                    <template x-for="(item, i) in items" :key="item.href + item.label">
                        <li>
                            <p x-show="i === 0 || items[i - 1].group !== item.group" class="px-3 pt-2 pb-1 text-[11px] font-semibold text-mist/70" x-text="item.group"></p>
                            <a :href="item.href" @mouseenter="cursor = i" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm" :class="cursor === i ? 'bg-neon/12 text-neon' : 'text-fog'">
                                <i :class="item.icon" class="text-lg"></i><span class="grow" x-html="item.html || item.label"></span>
                                <i x-show="cursor === i" class="ri-corner-down-left-line text-mist"></i>
                            </a>
                        </li>
                    </template>
                    <li x-show="!items.length" class="px-3 py-6 text-center text-sm text-mist">Tidak ada yang cocok.</li>
                </ul>
                <div class="flex items-center gap-4 border-t border-white/[0.07] px-4 py-2.5 text-[11px] text-mist">
                    <span><kbd class="font-display">↑↓</kbd> pilih</span><span><kbd class="font-display">Enter</kbd> buka</span><span class="ml-auto">{{ $siteName }} · Panel</span>
                </div>
            </div>
        </div>
    </template>

    @include('partials.toast')
</body>
</html>
