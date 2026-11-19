{{-- Navigasi atas untuk layar lebar (≥ 1024 px). Di beranda melayang di atas foto hero. --}}
@php
    $route = (string) request()->route()?->getName();
    $me = auth()->user();
    $links = [
        ['home', 'Beranda', ['home']],
        ['apps.index', 'Aplikasi', ['apps.*']],
        ['attendance.scan', 'Absen', ['attendance.*']],
        ['info.index', 'Info & Kampanye', ['info.*', 'campaigns.*']],
    ];
@endphp
<header x-data="{ scrolled: false, menu: false }" @scroll.window.throttle.50ms="scrolled = window.scrollY > 24"
        class="desk-nav hidden lg:block {{ $overlay ? 'fixed' : 'sticky' }} inset-x-0 top-0 z-40 transition-colors duration-300"
        :class="{{ $overlay ? 'scrolled' : 'true' }} ? 'border-b border-white/[0.07] bg-pit/85 backdrop-blur-xl' : 'border-b border-transparent bg-gradient-to-b from-black/45 to-transparent'">
    <div class="mx-auto flex h-[72px] max-w-7xl items-center gap-10 px-8">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3">
            <img src="/images/plant-asto.png" alt="" class="h-10 w-auto drop-shadow-[0_6px_14px_rgba(0,0,0,.5)]">
            <span class="leading-tight">
                <span class="block font-display text-xl font-bold">{{ $siteName }}</span>
                <span class="block text-[11px] text-neon-soft/80">Plant ASTO · KPP Mining</span>
            </span>
        </a>

        <nav class="flex items-center gap-1" aria-label="Navigasi utama">
            @foreach ($links as [$name, $label, $patterns])
                @php $active = request()->routeIs(...$patterns); @endphp
                <a href="{{ route($name) }}" @if ($active) aria-current="page" @endif
                   class="relative rounded-full px-4 py-2 text-[15px] font-semibold transition {{ $active ? 'text-neon' : 'text-fog hover:bg-white/[0.06] hover:text-ink' }}">
                    {{ $label }}
                    @if ($active) <span class="absolute inset-x-4 -bottom-[13px] h-[3px] rounded-t-full bg-neon shadow-[0_0_12px_var(--color-neon)]"></span> @endif
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2">
            @auth
                <a href="{{ route('attendance.scan') }}" class="inline-flex h-10 items-center gap-2 rounded-full bg-neon/15 px-4 text-sm font-semibold text-neon ring-1 ring-neon/40 transition hover:bg-neon/25">
                    <i class="ri-qr-scan-2-line text-lg"></i> Scan absen
                </a>
                <a href="{{ route('info.index') }}" class="relative grid h-10 w-10 place-items-center rounded-full text-fog transition hover:bg-white/[0.08] hover:text-ink" aria-label="Info dan pengumuman">
                    <i class="ri-notification-3-line text-xl"></i>
                    @if ($unreadCount ?? 0) <span class="badge-count !top-0.5 !-right-0.5">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span> @endif
                </a>
                <a href="{{ \App\Models\Setting::whatsappLink() }}" target="_blank" rel="noopener" class="grid h-10 w-10 place-items-center rounded-full text-fog transition hover:bg-white/[0.08] hover:text-ink" aria-label="Bantuan admin lewat WhatsApp" title="Bantuan admin">
                    <i class="ri-customer-service-2-line text-xl"></i>
                </a>

                <div class="relative ml-1" @click.outside="menu = false" @keydown.escape.window="menu = false">
                    <button type="button" @click="menu = !menu" :aria-expanded="menu" class="flex items-center gap-2.5 rounded-full py-1 pr-3 pl-1 transition hover:bg-white/[0.08]">
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-neon font-display text-sm font-bold text-[#04170d]">{{ $me->initials() }}</span>
                        <span class="text-left leading-tight">
                            <span class="block text-sm font-semibold">{{ $me->firstName() }}</span>
                            <span class="block font-display text-[11px] text-mist">{{ $me->nrp }}</span>
                        </span>
                        <i class="ri-arrow-down-s-line text-mist transition" :class="menu && 'rotate-180'"></i>
                    </button>
                    <div x-show="menu" x-cloak x-transition.origin.top.right class="absolute right-0 mt-2 w-72 overflow-hidden rounded-2xl border border-white/10 bg-bench shadow-2xl shadow-black/60">
                        <div class="relative overflow-hidden border-b border-white/[0.06] p-4">
                            <x-contour class="absolute -top-6 -right-10 h-28 w-56 opacity-40" />
                            <p class="relative font-semibold">{{ $me->displayName() }}</p>
                            <p class="relative text-sm text-mist">{{ $me->position ?: $me->roleLabel() }} · {{ $me->department ?: '–' }}</p>
                        </div>
                        <nav class="p-2 text-sm">
                            <a href="{{ route('profile') }}" class="side-link"><i class="ri-user-3-line"></i>Profil & PIN</a>
                            <a href="{{ route('attendance.history') }}" class="side-link"><i class="ri-calendar-check-line"></i>Riwayat absensi</a>
                            <a href="{{ route('apps.index', ['tab' => 'favorit']) }}" class="side-link"><i class="ri-star-line"></i>Aplikasi favorit</a>
                            @can('manage-content')
                                <a href="{{ route('admin.dashboard') }}" class="side-link text-neon"><i class="ri-dashboard-3-line"></i>Panel admin</a>
                            @elsecan('host-attendance')
                                <a href="{{ route('admin.attendance.index') }}" class="side-link text-neon"><i class="ri-qr-code-line"></i>Panel absensi</a>
                            @endcan
                            <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-white/[0.06] pt-1">
                                @csrf
                                <button class="side-link w-full text-red-300"><i class="ri-logout-box-r-line"></i>Keluar</button>
                            </form>
                        </nav>
                    </div>
                </div>
            @endauth
        </div>
    </div>
</header>
