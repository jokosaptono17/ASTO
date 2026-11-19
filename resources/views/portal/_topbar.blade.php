{{-- Judul halaman portal dengan kontur kecil (desktop: dengan jejak navigasi) --}}
<header class="relative overflow-hidden px-5 pt-[max(1.25rem,env(safe-area-inset-top))] pb-5 lg:px-0 lg:pt-8 lg:pb-7">
    <x-contour class="absolute -top-8 -right-10 h-40 w-80 opacity-50 lg:-top-16 lg:right-0 lg:h-64 lg:w-[560px]" />
    <nav class="relative mb-2 hidden items-center gap-1.5 text-sm text-mist lg:flex" aria-label="Jejak navigasi">
        <a href="{{ route('home') }}" class="hover:text-ink">Beranda</a><i class="ri-arrow-right-s-line"></i><span class="text-fog">{{ $heading }}</span>
    </nav>
    <div class="relative flex items-center gap-3">
        @isset($back)
            <a href="{{ $back }}" class="icon-btn -ml-2 h-10 w-10 lg:hidden" aria-label="Kembali"><i class="ri-arrow-left-line text-2xl"></i></a>
        @endisset
        <h1 class="grow font-display text-[1.6rem] leading-tight font-bold lg:text-[2.4rem]">{{ $heading }}</h1>
        {{ $actions ?? '' }}
    </div>
    @isset($sub) <p class="relative mt-1 text-sm text-mist lg:mt-2 lg:text-base">{{ $sub }}</p> @endisset
</header>
