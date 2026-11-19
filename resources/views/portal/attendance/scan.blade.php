<x-layouts.portal title="Absen">
    @include('portal._topbar', ['heading' => 'Absen', 'sub' => 'P5M, safety talk, dan kegiatan lainnya', 'back' => route('home')])

    <div class="space-y-5 px-4" x-data="attendancePicker(@js(route('attendance.locate')), @js($mapped))">
        {{-- Radar lokasi --}}
        <section class="relative overflow-hidden rounded-[26px] border bg-gradient-to-b to-pit px-5 pt-6 pb-5 transition-colors"
                 :class="{ neon: 'border-neon/30 from-neon/[0.10]', hivis: 'border-hivis/40 from-hivis/[0.10]', alarm: 'border-alarm/50 from-alarm/[0.12]' }[tone]">
            <x-contour class="absolute inset-0 h-full w-full opacity-30" />
            <div class="relative flex items-center gap-5">
                <div class="relative grid h-24 w-24 shrink-0 place-items-center" :class="{ neon: 'text-neon', hivis: 'text-hivis', alarm: 'text-alarm' }[tone]">
                    <template x-if="phase === 'locating'"><span class="contents"><span class="radar-ring"></span><span class="radar-ring"></span><span class="radar-ring"></span></span></template>
                    <span class="grid h-16 w-16 place-items-center rounded-full ring-1 ring-current/40" :class="{ neon: 'bg-neon/15', hivis: 'bg-hivis/15', alarm: 'bg-alarm/15' }[tone]">
                        <i class="text-3xl" :class="phase === 'locating' ? 'ri-focus-3-line animate-pulse' : ({ inside: 'ri-map-pin-2-fill', outside: 'ri-map-pin-range-line', none: 'ri-map-pin-2-fill' }[place.status] ?? 'ri-map-pin-line')"></i>
                    </span>
                </div>
                <div class="min-w-0" aria-live="polite">
                    <p class="font-display text-xl leading-tight font-bold" x-text="headline">Mencari posisi Anda…</p>
                    <p class="mt-1 text-sm leading-relaxed text-fog" x-text="detail"></p>
                    <div class="mt-2.5 flex flex-wrap items-center gap-2">
                        <span x-show="pos" x-cloak class="chip bg-white/[0.06] text-fog"><i class="ri-crosshair-2-line"></i> GPS ±<span x-text="pos?.acc"></span> m</span>
                        <button type="button" x-show="phase !== 'locating'" x-cloak @click="locate()" class="chip bg-white/[0.06] text-neon"><i class="ri-refresh-line"></i> Cek ulang</button>
                    </div>
                </div>
            </div>
        </section>

        {{-- Kegiatan yang sedang dibuka --}}
        <section>
            <h2 class="section-title">Pilih kegiatan</h2>
            <div class="mt-3 space-y-2.5">
                @forelse ($sessions as $s)
                    @php $needsScan = $s->checkin_mode === 'qr'; @endphp
                    <div class="relative overflow-hidden rounded-2xl border border-white/[0.07] bg-bench transition" style="box-shadow: inset 3px 0 0 {{ $s->type->color }}"
                         :class="verdict('{{ $s->code }}') === 'outside' && {{ $s->already ? 'false' : 'true' }} ? 'opacity-60' : ''">
                        <div class="p-4">
                            @include('portal.attendance._session-head', ['session' => $s])
                        </div>
                        @if ($s->already)
                            <a href="{{ route('attendance.checkin', $s->code) }}" class="flex items-center gap-2 border-t border-white/[0.06] px-4 py-3 text-sm font-semibold text-neon"><i class="ri-checkbox-circle-fill text-lg"></i> Sudah absen <i class="ri-arrow-right-s-line ml-auto text-xl text-mist"></i></a>
                        @elseif ($needsScan)
                            <button type="button" @click="$dispatch('open-scanner')" class="flex w-full items-center gap-2 border-t border-white/[0.06] px-4 py-3 text-left text-sm font-semibold text-hivis"><i class="ri-qr-scan-2-line text-lg"></i> Scan QR di layar sesi <i class="ri-arrow-right-s-line ml-auto text-xl text-mist"></i></button>
                        @else
                            <div class="border-t border-white/[0.06]">
                                <template x-if="verdict('{{ $s->code }}') === 'outside'">
                                    <p class="flex items-center gap-2 px-4 py-3 text-sm text-alarm"><i class="ri-lock-2-line text-lg"></i> <span x-text="verdicts['{{ $s->code }}']?.message"></span></p>
                                </template>
                                <template x-if="verdict('{{ $s->code }}') !== 'outside'">
                                    <a href="{{ route('attendance.checkin', $s->code) }}" class="flex items-center gap-3 px-4 py-3">
                                        <span class="text-sm" :class="verdict('{{ $s->code }}') === 'unverified' ? 'text-hivis' : 'text-mist'">
                                            <span x-show="verdict('{{ $s->code }}') === 'locating'"><i class="ri-loader-4-line inline-block animate-spin"></i> Mengecek lokasi</span>
                                            <span x-show="verdict('{{ $s->code }}') === 'inside'" x-cloak class="text-neon"><i class="ri-map-pin-2-fill"></i> <span x-text="verdicts['{{ $s->code }}']?.location"></span></span>
                                            <span x-show="verdict('{{ $s->code }}') === 'unverified'" x-cloak><i class="ri-error-warning-line"></i> Lokasi belum pasti</span>
                                            <span x-show="verdict('{{ $s->code }}') === 'none'" x-cloak><i class="ri-smartphone-line"></i> Absen dari aplikasi</span>
                                        </span>
                                        <span class="ml-auto rounded-xl bg-neon px-4 py-2 text-sm font-semibold text-[#04170d]">Absen</span>
                                    </a>
                                </template>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-white/[0.07] bg-bench px-5 py-8 text-center">
                        <i class="ri-calendar-close-line text-4xl text-mist"></i>
                        <p class="mt-2 font-semibold">Belum ada kegiatan yang dibuka</p>
                        @if ($next)
                            <p class="mt-1 text-sm text-mist">Berikutnya: <b class="text-ink">{{ $next->type->name }}{{ $next->title ? ' '.$next->title : '' }}</b> dibuka {{ $next->opens_at->isToday() ? 'pukul' : 'besok' }} {{ $next->opens_at->format('H:i') }}.</p>
                        @else
                            <p class="mt-1 text-sm text-mist">P5M dibuka otomatis sesuai jadwal shift.</p>
                        @endif
                    </div>
                @endforelse
                @if ($next && $sessions->isNotEmpty())
                    <p class="flex items-center gap-2 px-1 pt-1 text-sm text-mist"><i class="ri-time-line"></i> Berikutnya: {{ $next->type->name }}{{ $next->title ? ' '.$next->title : '' }} dibuka {{ $next->opens_at->isToday() ? 'pukul' : 'besok' }} {{ $next->opens_at->format('H:i') }}</p>
                @endif
            </div>
        </section>

        {{-- Scan QR layar event (mode paling ketat) --}}
        <section x-data="{ open: {{ $errors->has('token') ? 'true' : 'false' }} }" @open-scanner.window="open = true; $nextTick(() => $refs.scanner.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
                 class="rounded-2xl border border-white/[0.07] bg-bench">
            <button type="button" @click="open = !open" class="flex w-full items-center gap-3 p-4 text-left">
                <i class="ri-qr-scan-2-line text-2xl text-neon"></i>
                <span class="grow leading-tight"><span class="block font-semibold">Scan QR di layar event</span><span class="text-xs text-mist">Untuk sesi yang menampilkan QR berganti di TV</span></span>
                <i class="ri-arrow-down-s-line text-xl text-mist transition" :class="open && 'rotate-180'"></i>
            </button>
            <div x-show="open" x-cloak x-ref="scanner" class="space-y-3 border-t border-white/[0.06] p-4">
                <template x-if="open">
                    <div x-data="qrScanner(true)" class="relative overflow-hidden rounded-2xl bg-black">
                        <div class="relative aspect-square w-full">
                            <video x-ref="video" playsinline muted class="absolute inset-0 h-full w-full object-cover" x-show="state === 'scanning'"></video>
                            <canvas x-ref="canvas" class="hidden"></canvas>
                            <div x-show="state === 'scanning'" class="pointer-events-none absolute inset-0 grid place-items-center">
                                <div class="relative h-[62%] w-[62%]">
                                    <span class="absolute top-0 left-0 h-10 w-10 rounded-tl-2xl border-t-4 border-l-4 border-neon"></span>
                                    <span class="absolute top-0 right-0 h-10 w-10 rounded-tr-2xl border-t-4 border-r-4 border-neon"></span>
                                    <span class="absolute bottom-0 left-0 h-10 w-10 rounded-bl-2xl border-b-4 border-l-4 border-neon"></span>
                                    <span class="absolute right-0 bottom-0 h-10 w-10 rounded-br-2xl border-r-4 border-b-4 border-neon"></span>
                                    <span class="scan-line absolute inset-x-3 h-0.5 bg-neon shadow-[0_0_14px_var(--color-neon)]"></span>
                                </div>
                            </div>
                            <div x-show="state !== 'scanning'" class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-gradient-to-b from-ridge to-pit p-6 text-center">
                                <p class="max-w-[30ch] text-[15px] text-fog" x-text="message"></p>
                                <button x-show="state === 'idle' || state === 'error'" type="button" @click="start()" class="btn-neon"><i class="ri-camera-line text-lg"></i> Buka kamera</button>
                            </div>
                        </div>
                    </div>
                </template>
                <form method="POST" action="{{ route('attendance.code') }}">
                    @csrf
                    <label for="token" class="label">Atau ketik 6 angka di layar</label>
                    <div class="flex gap-2">
                        <input id="token" name="token" value="{{ old('token') }}" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required
                               placeholder="••••••" class="field h-14 text-center font-display text-2xl tracking-[.5em]">
                        <button class="btn-neon !min-h-14 shrink-0 !px-5" aria-label="Kirim kode"><i class="ri-arrow-right-line text-xl"></i></button>
                    </div>
                    @error('token') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
                </form>
            </div>
        </section>

        <a href="{{ route('attendance.history') }}" class="flex items-center gap-3 rounded-2xl border border-white/[0.07] bg-bench p-4">
            <i class="ri-calendar-check-line text-2xl text-neon"></i>
            <span class="grow font-semibold">Riwayat absensi saya</span>
            <i class="ri-arrow-right-s-line text-xl text-mist"></i>
        </a>
    </div>
</x-layouts.portal>
