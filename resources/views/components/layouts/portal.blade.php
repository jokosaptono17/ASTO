@props(['title' => null, 'mentariTips' => [], 'bleed' => false])
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => $title ?? null])
</head>
<body class="min-h-svh bg-pit">
    {{-- Tablet: kolom ponsel di tengah. Desktop (≥ 1024 px): tata letak penuh dengan navigasi atas. --}}
    <div aria-hidden="true" class="fixed inset-0 -z-10 hidden bg-[radial-gradient(60%_50%_at_50%_0%,rgba(61,255,142,.08),transparent)] md:block lg:hidden"></div>
    @include('partials.desktop-nav', ['overlay' => $bleed])
    <div class="relative mx-auto min-h-svh max-w-[480px] bg-pit pb-[calc(84px+env(safe-area-inset-bottom))] md:border-x md:border-white/[0.05] lg:min-h-[60vh] lg:max-w-none lg:border-0 lg:pb-0">
        @if ($bleed)
            {{ $slot }}
        @else
            <div class="lg:mx-auto lg:max-w-6xl lg:px-8 lg:pt-4">{{ $slot }}</div>
        @endif
    </div>
    @include('partials.desktop-footer')

    @php $route = request()->route()?->getName(); @endphp
    <nav class="tabbar lg:hidden" aria-label="Navigasi utama">
        <div class="flex items-end px-1">
            <a href="{{ route('home') }}" class="tab" @if ($route === 'home') aria-current="page" @endif>
                <i class="{{ $route === 'home' ? 'ri-home-5-fill' : 'ri-home-5-line' }}"></i><span>Beranda</span>
            </a>
            <a href="{{ route('apps.index') }}" class="tab" @if ($route === 'apps.index') aria-current="page" @endif>
                <i class="{{ $route === 'apps.index' ? 'ri-apps-2-fill' : 'ri-apps-2-line' }}"></i><span>Aplikasi</span>
            </a>
            <button type="button" @click="$dispatch('mentari-open')" x-data class="tab -mt-6" aria-label="Tanya Mentari">
                <span class="grid h-[62px] w-[62px] place-items-center rounded-full border border-neon/50 bg-gradient-to-b from-ridge to-pit shadow-[0_0_0_6px_var(--color-pit),0_0_30px_rgba(61,255,142,.35)]">
                    <x-mentari :size="48" label="" class="-mt-1" />
                </span>
                <span class="text-neon">Mentari</span>
            </button>
            <a href="{{ route('info.index') }}" class="tab" @if (in_array($route, ['info.index', 'info.show'])) aria-current="page" @endif>
                <span class="relative">
                    <i class="{{ str_starts_with((string) $route, 'info') ? 'ri-notification-3-fill' : 'ri-notification-3-line' }}"></i>
                    @if ($unreadCount) <span class="badge-count">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span> @endif
                </span>
                <span>Info</span>
            </a>
            <a href="{{ route('profile') }}" class="tab" @if ($route === 'profile') aria-current="page" @endif>
                <i class="{{ $route === 'profile' ? 'ri-user-3-fill' : 'ri-user-3-line' }}"></i><span>Profil</span>
            </a>
        </div>
    </nav>

    {{-- Mentari di desktop: tombol melayang --}}
    <button type="button" x-data @click="$dispatch('mentari-open')" aria-label="Tanya Mentari"
            class="group fixed right-7 bottom-7 z-40 hidden items-center rounded-full border border-neon/40 bg-bench/90 p-1.5 shadow-[0_10px_40px_-8px_rgba(61,255,142,.45)] backdrop-blur-xl transition hover:-translate-y-0.5 hover:border-neon lg:flex">
        <span class="grid h-14 w-14 place-items-center rounded-full bg-gradient-to-b from-ridge to-pit"><x-mentari :size="46" label="" class="-mt-1" /></span>
        <span class="max-w-0 overflow-hidden text-left leading-tight whitespace-nowrap transition-all duration-300 group-hover:max-w-[180px] group-hover:pr-4 group-hover:pl-3 group-focus-visible:max-w-[180px] group-focus-visible:pr-4 group-focus-visible:pl-3"><span class="block font-semibold text-neon">Tanya Mentari</span><span class="block text-xs text-mist">Asisten Plant ASTO</span></span>
    </button>
    <x-mentari-chat :page="$route ?? 'portal'" :tips="$mentariTips" />
    @include('partials.toast')
</body>
</html>
