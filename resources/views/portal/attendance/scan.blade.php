<x-layouts.portal title="Absen">
    @include('portal._topbar', ['heading' => 'Absen', 'sub' => 'P5M, safety talk, dan kegiatan lainnya', 'back' => route('home')])

    <div class="space-y-5 px-4" x-data="qrScanner()" x-init="init()">
        {{-- Kamera --}}
        <section class="relative overflow-hidden rounded-[26px] border border-neon/25 bg-black">
            <div class="relative aspect-square w-full">
                <video x-ref="video" playsinline muted class="absolute inset-0 h-full w-full object-cover" x-show="state === 'scanning'"></video>
                <canvas x-ref="canvas" class="hidden"></canvas>
                {{-- Bingkai bidik --}}
                <div x-show="state === 'scanning'" class="pointer-events-none absolute inset-0 grid place-items-center">
                    <div class="relative h-[62%] w-[62%]">
                        <span class="absolute top-0 left-0 h-10 w-10 rounded-tl-2xl border-t-4 border-l-4 border-neon"></span>
                        <span class="absolute top-0 right-0 h-10 w-10 rounded-tr-2xl border-t-4 border-r-4 border-neon"></span>
                        <span class="absolute bottom-0 left-0 h-10 w-10 rounded-bl-2xl border-b-4 border-l-4 border-neon"></span>
                        <span class="absolute right-0 bottom-0 h-10 w-10 rounded-br-2xl border-r-4 border-b-4 border-neon"></span>
                        <span class="scan-line absolute inset-x-3 h-0.5 bg-neon shadow-[0_0_14px_var(--color-neon)]"></span>
                    </div>
                </div>
                {{-- Belum mulai / tidak ada kamera --}}
                <div x-show="state !== 'scanning'" class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-gradient-to-b from-ridge to-pit p-6 text-center">
                    <x-contour class="absolute inset-0 h-full w-full opacity-30" />
                    <span class="relative grid h-20 w-20 place-items-center rounded-3xl bg-neon/10 ring-1 ring-neon/40"><i class="ri-qr-scan-2-line text-4xl text-neon"></i></span>
                    <p class="relative max-w-[30ch] text-[15px] text-fog" x-text="message"></p>
                    <button x-show="state === 'idle' || state === 'error'" type="button" @click="start()" class="btn-neon relative"><i class="ri-camera-line text-lg"></i> Buka kamera</button>
                </div>
            </div>
            <p x-show="state === 'scanning'" class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent px-4 pt-8 pb-4 text-center text-sm text-fog">Arahkan ke QR di layar sesi</p>
        </section>

        {{-- Ketik kode --}}
        <form method="POST" action="{{ route('attendance.code') }}" class="rounded-2xl border border-white/[0.07] bg-bench p-4">
            @csrf
            <label for="token" class="label">Kamera bermasalah? Ketik 6 angka di layar sesi</label>
            <div class="flex gap-2">
                <input id="token" name="token" value="{{ old('token') }}" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required
                       placeholder="••••••" class="field h-14 text-center font-display text-2xl tracking-[.5em]">
                <button class="btn-neon !min-h-14 shrink-0 !px-5" aria-label="Kirim kode"><i class="ri-arrow-right-line text-xl"></i></button>
            </div>
            @error('token') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
        </form>

        {{-- Sesi yang sedang dibuka --}}
        @if ($sessions->isNotEmpty())
            <section>
                <h2 class="section-title">Sedang berlangsung</h2>
                <div class="mt-3 space-y-2.5">
                    @foreach ($sessions as $s)
                        <div class="rounded-2xl border border-white/[0.07] bg-bench p-4">
                            @include('portal.attendance._session-head', ['session' => $s])
                            <div class="mt-3 flex items-center gap-2">
                                @if ($s->already)
                                    <span class="chip-tayang"><i class="ri-checkbox-circle-fill"></i> Sudah absen</span>
                                @elseif ($s->checkin_mode === 'open')
                                    <a href="{{ route('attendance.checkin', $s->code) }}" class="btn-neon !min-h-10 w-full text-sm">Absen sekarang</a>
                                @else
                                    <span class="text-sm text-mist"><i class="ri-qr-code-line text-neon"></i> Scan QR di lokasi untuk absen</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <a href="{{ route('attendance.history') }}" class="flex items-center gap-3 rounded-2xl border border-white/[0.07] bg-bench p-4">
            <i class="ri-calendar-check-line text-2xl text-neon"></i>
            <span class="grow font-semibold">Riwayat absensi saya</span>
            <i class="ri-arrow-right-s-line text-xl text-mist"></i>
        </a>
    </div>
</x-layouts.portal>
