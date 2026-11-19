{{-- Layar penuh untuk TV / proyektor di lokasi P5M --}}
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => 'Layar absen '.$session->type->name])
</head>
<body class="h-svh overflow-hidden bg-pit">
    <main x-data="liveSession(@js(route('admin.attendance.feed', $session)), @js($feed))" class="relative flex h-full flex-col overflow-hidden p-[3vh]">
        <x-contour class="absolute -top-[10vh] -right-[10vw] h-[80vh] w-[70vw] opacity-50" />
        <div aria-hidden="true" class="absolute -top-40 -left-40 h-[60vh] w-[60vh] rounded-full blur-[120px]" style="background: color-mix(in oklab, {{ $session->type->color }} 30%, transparent)"></div>

        <header class="relative flex items-center gap-[2vw]">
            <img src="/images/plant-asto.png" alt="" class="h-[8vh] w-auto">
            <div class="grow">
                <p class="font-display text-[4.2vh] leading-none font-bold" style="color: {{ $session->type->color }}">{{ $session->type->name }}</p>
                <p class="mt-[0.8vh] text-[2.4vh] text-fog">{{ $session->title ?: 'Plant ASTO' }}</p>
            </div>
            <div class="text-right">
                <p class="font-display text-[5vh] leading-none font-bold" x-data="{ now: '' }" x-init="setInterval(() => now = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }), 1000)" x-text="now"></p>
                <p class="mt-[0.6vh] text-[2vh] text-mist">{{ $session->starts_at->locale('id')->translatedFormat('l, j F Y') }}{{ $session->location ? ' · '.$session->location : '' }}</p>
            </div>
        </header>

        <div class="relative mt-[3vh] grid min-h-0 grow grid-cols-[auto_1fr] gap-[3vw]">
            {{-- QR besar --}}
            <section class="flex flex-col items-center justify-center">
                <template x-if="feed.url">
                    <div class="text-center">
                        <div class="rounded-[3vh] bg-white p-[2vh] shadow-[0_0_80px_rgba(61,255,142,.35)]"><canvas data-qr data-size="900" class="block !h-[54vh] !w-[54vh]"></canvas></div>
                        <p class="mt-[2.5vh] text-[2.6vh] text-fog">Scan dengan kamera HP · atau buka <b class="text-ink">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/absen</b></p>
                        @if ($session->checkin_mode === 'qr')
                            <div class="mt-[1.5vh] flex items-center justify-center gap-[1.5vw]">
                                <p class="font-display text-[8vh] leading-none font-bold tracking-[.2em] text-neon" x-text="feed.token"></p>
                                <svg viewBox="0 0 36 36" class="h-[7vh] w-[7vh] -rotate-90"><circle cx="18" cy="18" r="15" fill="none" stroke="rgb(255 255 255 / .12)" stroke-width="3"/><circle cx="18" cy="18" r="15" fill="none" stroke="var(--color-neon)" stroke-width="3" stroke-linecap="round" pathLength="100" :stroke-dasharray="`${ring} 100`" class="transition-all duration-1000 ease-linear"/></svg>
                            </div>
                        @endif
                    </div>
                </template>
                <div x-show="!feed.url" class="text-center">
                    <x-mentari :size="260" />
                    <p class="mt-[2vh] font-display text-[5vh] font-bold" x-text="feed.status === 'terjadwal' ? 'Absen dibuka pukul {{ $session->opens_at->format('H:i') }}' : 'Absen sudah ditutup'"></p>
                </div>
            </section>

            {{-- Hitungan & kedatangan --}}
            <section class="flex min-h-0 flex-col">
                <div class="grid grid-cols-3 gap-[1.5vw]">
                    <div class="rounded-[2.5vh] border border-neon/30 bg-neon/[0.08] p-[2.5vh]">
                        <p class="text-[2.2vh] text-fog">Hadir</p>
                        <p class="font-display text-[10vh] leading-none font-bold text-neon"><span x-text="feed.counts.present"></span><span x-show="feed.counts.expected" class="text-[5vh] text-mist" x-text="'/' + feed.counts.expected"></span></p>
                    </div>
                    <div class="rounded-[2.5vh] border border-white/10 bg-white/[0.04] p-[2.5vh]">
                        <p class="text-[2.2vh] text-fog">Terlambat</p>
                        <p class="font-display text-[10vh] leading-none font-bold" :class="feed.counts.late && 'text-hivis'" x-text="feed.counts.late"></p>
                    </div>
                    <div class="rounded-[2.5vh] border border-white/10 bg-white/[0.04] p-[2.5vh]">
                        <p class="text-[2.2vh] text-fog">Belum hadir</p>
                        <p class="font-display text-[10vh] leading-none font-bold" x-text="feed.counts.expected ? feed.missing.length : '–'"></p>
                    </div>
                </div>
                <h2 class="mt-[3vh] text-[2.4vh] font-semibold text-fog">Baru saja absen</h2>
                <ul class="mt-[1.5vh] min-h-0 grow space-y-[1.2vh] overflow-hidden">
                    <template x-for="r in (feed.records || []).slice(0, 9)" :key="r.id">
                        <li class="flex items-center gap-[1.2vw] rounded-[2vh] border border-white/[0.07] bg-bench/80 px-[1.5vw] py-[1.4vh]" :class="fresh.has(r.id) && 'arrive'">
                            <span class="grid h-[6vh] w-[6vh] shrink-0 place-items-center rounded-full bg-neon font-display text-[2.2vh] font-bold text-[#04170d]" x-text="r.initials"></span>
                            <p class="min-w-0 grow truncate text-[3vh] font-semibold"><span x-text="r.name"></span> <span x-show="r.role === 'pemateri'" class="text-hivis">⭐</span></p>
                            <p class="text-[2.2vh] text-mist" x-text="r.department"></p>
                            <p class="w-[8vw] text-right font-display text-[3vh]" :class="r.late ? 'text-hivis' : 'text-neon'" x-text="r.time"></p>
                        </li>
                    </template>
                    <li x-show="!(feed.records || []).length" class="pt-[6vh] text-center text-[3vh] text-mist">Belum ada yang absen. Yuk scan QR-nya!</li>
                </ul>
            </section>
        </div>

        <footer class="relative mt-[2vh] flex items-center justify-between text-[2vh] text-mist">
            <span>Absen ditutup pukul {{ $session->closes_at->format('H:i') }} · terlambat setelah {{ $session->starts_at->copy()->addMinutes($session->late_after)->format('H:i') }}</span>
            <span class="font-display">{{ \App\Models\Setting::get('slogan') }}</span>
        </footer>
    </main>
</body>
</html>
