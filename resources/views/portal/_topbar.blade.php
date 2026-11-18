{{-- Judul halaman portal dengan kontur kecil --}}
<header class="relative overflow-hidden px-5 pt-[max(1.25rem,env(safe-area-inset-top))] pb-5">
    <x-contour class="absolute -top-8 -right-10 h-40 w-80 opacity-50" />
    <div class="relative flex items-center gap-3">
        @isset($back)
            <a href="{{ $back }}" class="icon-btn -ml-2 h-10 w-10" aria-label="Kembali"><i class="ri-arrow-left-line text-2xl"></i></a>
        @endisset
        <h1 class="grow font-display text-[1.6rem] leading-tight font-bold">{{ $heading }}</h1>
        {{ $actions ?? '' }}
    </div>
    @isset($sub) <p class="relative mt-1 text-sm text-mist">{{ $sub }}</p> @endisset
</header>
