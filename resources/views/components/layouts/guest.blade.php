@props(['title' => null])
@php $hero = \App\Models\Setting::hero(); @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => $title ?? 'Masuk'])
</head>
<body class="min-h-svh bg-pit lg:grid lg:grid-cols-[minmax(0,1.25fr)_minmax(460px,1fr)]">
    {{-- Desktop: foto hero di kiri --}}
    <aside class="relative hidden overflow-hidden lg:block" aria-hidden="true">
        <img src="{{ $hero['src'] }}" @if ($hero['srcset']) srcset="{{ $hero['srcset'] }}" sizes="60vw" @endif alt="" fetchpriority="high"
             class="hero-drift absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-[linear-gradient(115deg,rgba(6,18,13,.92)_0%,rgba(6,18,13,.55)_45%,rgba(6,18,13,.25)_75%,rgba(6,18,13,.85)_100%)]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(60%_60%_at_20%_100%,rgba(61,255,142,.18),transparent)]"></div>
        <x-contour class="absolute inset-0 h-full w-full opacity-30 mix-blend-screen" />
        <div class="relative flex h-full flex-col justify-between p-12 xl:p-16">
            <div class="flex items-center gap-3">
                <img src="/images/plant-asto.png" alt="" class="h-14 w-auto drop-shadow-[0_6px_16px_rgba(0,0,0,.6)]">
                <div class="leading-tight">
                    <div class="font-display text-2xl font-bold tracking-wide">{{ $siteName }}</div>
                    <div class="text-sm text-fog">{{ \App\Models\Setting::get('site_tagline') }}</div>
                </div>
            </div>
            <div class="max-w-xl">
                <p class="font-display text-[3.4rem] leading-[1.02] font-bold text-balance xl:text-[4rem]">Satu pintu untuk semua aplikasi Plant ASTO</p>
                <ul class="mt-8 grid max-w-lg grid-cols-3 gap-3 text-sm">
                    <li class="rounded-2xl border border-white/10 bg-black/30 p-4 backdrop-blur-md"><i class="ri-apps-2-line text-2xl text-neon"></i><p class="mt-2 font-semibold">Semua aplikasi</p><p class="text-xs text-fog">LMS, workshop, induksi, dan lainnya</p></li>
                    <li class="rounded-2xl border border-white/10 bg-black/30 p-4 backdrop-blur-md"><i class="ri-map-pin-2-line text-2xl text-neon"></i><p class="mt-2 font-semibold">Absensi GPS</p><p class="text-xs text-fog">P5M & safety talk dengan cek fatigue</p></li>
                    <li class="rounded-2xl border border-white/10 bg-black/30 p-4 backdrop-blur-md"><i class="ri-robot-2-line text-2xl text-neon"></i><p class="mt-2 font-semibold">Mentari</p><p class="text-xs text-fog">Asisten yang tahu data Plant ASTO</p></li>
                </ul>
            </div>
            <div class="flex items-center justify-between text-sm text-fog">
                <span class="font-display font-bold tracking-wide text-neon">{{ \App\Models\Setting::get('slogan') }}</span>
                <span class="rounded-xl bg-white/90 px-3 py-1.5"><img src="/images/kpp-mining.png" alt="KPP Mining" class="h-7 w-auto"></span>
            </div>
        </div>
    </aside>

    <main class="relative mx-auto flex min-h-svh w-full max-w-[480px] flex-col overflow-hidden px-6 pt-[max(1.5rem,env(safe-area-inset-top))] pb-[calc(6rem+env(safe-area-inset-bottom))] lg:max-w-none lg:justify-center lg:px-14 lg:py-12">
        <x-contour class="absolute -top-10 -right-24 h-[420px] w-[620px] opacity-70" />
        <div aria-hidden="true" class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-neon/20 blur-[100px]"></div>
        <div class="relative flex grow flex-col lg:mx-auto lg:w-full lg:max-w-[400px] lg:grow-0">
            {{ $slot }}
        </div>
    </main>
    <x-mentari-chat page="login" :launcher="true" :tips="['Halo! Saya Mentari 👋', 'Masuk cukup pakai NRP dan PIN 6 angka.', 'Lupa PIN? Saya sambungkan ke admin.']" />
    @include('partials.toast')
</body>
</html>
