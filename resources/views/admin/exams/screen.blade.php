<x-layouts.exam :title="'Layar · '.$exam->title">
{{-- Layar proyektor ruang ujian: kode besar + penghitung langsung --}}
<div x-data="examLive(@js(['feed' => $feed, 'feedUrl' => route('admin.exams.feed', $exam), 'actionUrl' => '', 'interval' => 5000]))"
     class="relative flex min-h-svh flex-col overflow-hidden bg-pit px-[5vw] py-[4vh]">
    <div class="absolute inset-0 bg-[radial-gradient(60%_60%_at_20%_10%,rgba(255,194,61,.14),transparent),radial-gradient(50%_60%_at_90%_100%,rgba(61,255,142,.12),transparent)]"></div>
    <x-contour class="pointer-events-none absolute inset-0 h-full w-full opacity-25" />

    <header class="relative flex items-center gap-4">
        <img src="/images/plant-asto.png" alt="" class="h-[6vh] w-auto">
        <div class="leading-tight"><p class="font-display text-[2.4vh] font-bold">{{ $siteName }}</p><p class="text-[1.7vh] text-mist">Ujian Event Plant ASTO</p></div>
        <p class="ml-auto font-display text-[3vh] font-semibold text-fog" x-data="{ t: '' }" x-init="setInterval(() => t = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }), 1000)" x-text="t"></p>
    </header>

    <main class="relative grid grow items-center gap-[4vw] lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div>
            <p class="text-[2.4vh] font-semibold text-hivis"><i class="ri-medal-2-fill"></i> {{ $exam->title }}</p>
            <p class="mt-[3vh] text-[2.2vh] text-fog">Buka portal {{ $siteName }} → <b class="text-ink">Ujian</b>, lalu masukkan kode:</p>
            @php $len = max(4, mb_strlen($exam->code)); @endphp
            <p class="mt-[1.5vh] font-display leading-[.95] font-bold tracking-[.06em] whitespace-nowrap text-ink drop-shadow-[0_0_50px_rgba(255,194,61,.35)]"
               style="font-size: min({{ round(50 / ($len * 0.7), 2) }}vw, {{ round(130 / ($len * 0.7) * 1.0, 2) }}vh, 22vh)">{{ $exam->code }}</p>
            <p class="mt-[3vh] flex flex-wrap gap-x-[3vw] gap-y-2 text-[2.2vh] text-fog">
                <span><i class="ri-timer-line text-hivis"></i> {{ $exam->duration_minutes }} menit</span>
                <span><i class="ri-file-list-3-line text-hivis"></i> {{ $exam->questionCount() }} soal</span>
                @if ($exam->closes_at) <span><i class="ri-lock-line text-hivis"></i> ditutup {{ $exam->closes_at->format('H:i') }}</span> @endif
                <span><i class="ri-smartphone-line text-hivis"></i> Jangan keluar dari halaman ujian</span>
            </p>
        </div>
        <div class="space-y-[2.5vh]">
            <div class="grid grid-cols-3 gap-[1.2vw]">
                <div class="rounded-[2.4vh] border border-white/10 bg-white/[0.04] p-[2.2vh] text-center"><p class="font-display text-[8vh] leading-none font-bold" x-text="stats.waiting"></p><p class="mt-1 text-[1.8vh] text-mist">belum mulai</p></div>
                <div class="rounded-[2.4vh] border border-sky-300/30 bg-sky-400/10 p-[2.2vh] text-center"><p class="font-display text-[8vh] leading-none font-bold text-sky-300" x-text="stats.live"></p><p class="mt-1 text-[1.8vh] text-mist">mengerjakan</p></div>
                <div class="rounded-[2.4vh] border border-neon/30 bg-neon/10 p-[2.2vh] text-center"><p class="font-display text-[8vh] leading-none font-bold text-neon" x-text="stats.done"></p><p class="mt-1 text-[1.8vh] text-mist">selesai</p></div>
            </div>
            <div class="flex items-center gap-[2vw] rounded-[2.4vh] border border-white/10 bg-white/[0.04] p-[2.2vh]">
                <div class="shrink-0 rounded-[1.6vh] bg-white p-[1vh]" x-data="qrImage(@js(route('exams.index')), 360)"><canvas x-ref="canvas" class="!h-[16vh] !w-[16vh]"></canvas></div>
                <p class="text-[2.1vh] leading-relaxed text-fog">Pindai untuk membuka halaman ujian di HP. Masuk dengan NRP & PIN seperti biasa.</p>
            </div>
            <div class="h-[1vh] overflow-hidden rounded-full bg-white/[0.06]"><div class="h-full rounded-full bg-gradient-to-r from-neon to-hivis transition-[width] duration-700" :style="`width: ${stats.participants ? (stats.done + stats.dq) / stats.participants * 100 : 0}%`"></div></div>
        </div>
    </main>
</div>
</x-layouts.exam>
