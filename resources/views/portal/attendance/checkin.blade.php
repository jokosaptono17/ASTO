<x-layouts.portal :title="'Absen '.$session->type->name">
    @include('portal._topbar', ['heading' => 'Absen', 'back' => route('attendance.scan')])

    <div class="space-y-5 px-4 lg:mx-auto lg:max-w-2xl lg:px-0">
        <section class="rounded-[22px] border border-white/[0.07] bg-bench p-4" style="box-shadow: inset 3px 0 0 {{ $session->type->color }}">
            @include('portal.attendance._session-head', ['session' => $session])
        </section>

        @if ($state === 'done')
            @php
                $fresh = session('justCheckedIn') === $record->id;
                $f = $record->fatigue ? \App\Models\AttendanceRecord::FATIGUE[$record->fatigue] : null;
            @endphp
            <section class="relative overflow-hidden rounded-[26px] border border-neon/30 bg-gradient-to-b from-neon/[0.12] to-transparent px-5 pt-7 pb-6 text-center">
                <x-contour class="absolute -top-10 left-0 h-48 w-full opacity-40" />
                <div class="relative mx-auto grid h-20 w-20 place-items-center rounded-full bg-neon text-[#04170d] {{ $fresh ? 'checkin-pop' : '' }}">
                    <i class="ri-check-line text-5xl"></i>
                </div>
                <h2 class="relative mt-4 font-display text-2xl font-bold">{{ $fresh ? 'Absen tercatat!' : 'Anda sudah absen' }}</h2>
                <p class="relative mt-1 text-fog">{{ $record->checked_in_at->locale('id')->translatedFormat('l, j F Y · H:i') }}</p>
                <div class="relative mt-4 flex flex-wrap justify-center gap-2">
                    <span class="chip bg-white/[0.06] text-fog">{{ $record->role === 'pemateri' ? '⭐ Pemateri' : 'Audience' }}</span>
                    @if ($record->is_late) <span class="chip-penting"><i class="ri-time-line"></i> Terlambat</span> @elseif ($session->late_after !== null) <span class="chip-tayang"><i class="ri-time-line"></i> Tepat waktu</span> @endif
                    @if ($record->geo_status === 'inside') <span class="chip-tayang"><i class="ri-map-pin-2-fill"></i> {{ $record->location?->name }}</span>
                    @elseif ($record->geo_status === 'unverified') <span class="chip bg-hivis/15 text-hivis"><i class="ri-map-pin-line"></i> Lokasi diperiksa GL</span> @endif
                </div>
            </section>

            @if ($f)
                <section class="rounded-2xl border p-4 {{ ['neon' => 'border-neon/30 bg-neon/[0.06]', 'hivis' => 'border-hivis/40 bg-hivis/[0.08]', 'alarm' => 'border-alarm/50 bg-alarm/10'][$f['tone']] }}">
                    <div class="flex items-center gap-3">
                        <i class="{{ $f['icon'] }} text-3xl {{ ['neon' => 'text-neon', 'hivis' => 'text-hivis', 'alarm' => 'text-alarm'][$f['tone']] }}"></i>
                        <div>
                            <p class="font-display text-lg font-semibold">{{ $f['label'] }}</p>
                            <p class="text-sm text-fog">Tidur {{ $record->sleepLabel() }} ({{ substr($record->sleep_at, 0, 5) }}–{{ substr($record->wake_at, 0, 5) }})</p>
                        </div>
                    </div>
                    @if ($record->fatigue === 'risk')
                        <p class="mt-3 text-sm leading-relaxed text-ink">Waktu tidur Anda kurang dari 5 jam. <b>Laporkan ke atasan/GL sebelum mulai bekerja</b> dan jangan mengoperasikan unit atau pekerjaan berisiko sebelum ada penilaian.</p>
                    @elseif ($record->fatigue === 'coaching')
                        <p class="mt-3 text-sm leading-relaxed text-ink">Tidur 5–6 jam. Sampaikan ke atasan untuk coaching singkat dan tetap waspada selama bekerja.</p>
                    @endif
                </section>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('home') }}" class="btn-ghost">Ke beranda</a>
                <a href="{{ route('attendance.history') }}" class="btn-ghost">Riwayat</a>
            </div>

        @elseif ($state === 'terjadwal')
            <div class="rounded-2xl border border-white/[0.07] bg-bench p-6 text-center">
                <i class="ri-hourglass-2-line text-4xl text-hivis"></i>
                <p class="mt-3 font-semibold">Absen belum dibuka</p>
                <p class="mt-1 text-sm text-mist">Dibuka {{ $session->opens_at->locale('id')->translatedFormat('l, j M · H:i') }}.</p>
            </div>

        @elseif ($state === 'selesai')
            <div class="rounded-2xl border border-white/[0.07] bg-bench p-6 text-center">
                <i class="ri-lock-line text-4xl text-mist"></i>
                <p class="mt-3 font-semibold">Sesi ini sudah ditutup</p>
                <p class="mt-1 text-sm text-mist">Kalau Anda hadir tapi belum tercatat, sampaikan ke host{{ $session->host ? ' ('.$session->host->displayName().')' : '' }}.</p>
            </div>

        @elseif ($state === 'proof')
            <div class="rounded-2xl border border-hivis/30 bg-hivis/[0.06] p-5">
                <p class="flex items-center gap-2 font-semibold"><i class="ri-qr-scan-2-line text-2xl text-hivis"></i> Scan QR di lokasi</p>
                <p class="mt-1.5 text-sm leading-relaxed text-fog">QR atau kode di layar sesi berganti tiap 30 detik. Scan ulang, atau ketik 6 angka yang sedang tampil.</p>
                <form method="POST" action="{{ route('attendance.code') }}" class="mt-4 flex gap-2">
                    @csrf
                    <input name="token" inputmode="numeric" pattern="\d{6}" maxlength="6" required placeholder="••••••" class="field h-14 text-center font-display text-2xl tracking-[.5em]">
                    <button class="btn-neon !min-h-14 shrink-0 !px-5" aria-label="Kirim"><i class="ri-arrow-right-line text-xl"></i></button>
                </form>
                @error('token') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
                <a href="{{ route('attendance.scan') }}" class="btn-ghost mt-3 w-full"><i class="ri-camera-line text-lg"></i> Buka pemindai</a>
            </div>

        @else
            {{-- Form check-in --}}
            <form method="POST" action="{{ route('attendance.store', $session->code) }}" class="space-y-5"
                  x-data="sleepForm(@js(old('sleep_at', $lastSleep[0] ?? '')), @js(old('wake_at', $lastSleep[1] ?? '')))">
                @csrf
                {{-- Cek lokasi GPS --}}
                <div x-data="geoCheck(@js(route('attendance.locate')), @js($session->code), @js($checkGps))" x-effect="$dispatch('geo-state', { blocked })">
                    <input type="hidden" name="lat" :value="lat"><input type="hidden" name="lng" :value="lng"><input type="hidden" name="acc" :value="acc">
                    @if ($checkGps)
                        <section class="flex items-start gap-3 rounded-2xl border p-3.5 transition-colors" aria-live="polite"
                                 :class="{ locating: 'border-white/10 bg-bench', inside: 'border-neon/40 bg-neon/[0.07]', outside: 'border-alarm/50 bg-alarm/10', unverified: 'border-hivis/40 bg-hivis/[0.08]', none: 'border-white/10 bg-bench' }[status]">
                            <span class="relative grid h-11 w-11 shrink-0 place-items-center rounded-xl"
                                  :class="{ locating: 'bg-white/[0.06] text-neon', inside: 'bg-neon/15 text-neon', outside: 'bg-alarm/15 text-alarm', unverified: 'bg-hivis/15 text-hivis', none: 'bg-white/[0.06] text-mist' }[status]">
                                <i class="text-2xl" :class="{ locating: 'ri-focus-3-line animate-pulse', inside: 'ri-map-pin-2-fill', outside: 'ri-map-pin-range-line', unverified: 'ri-map-pin-line', none: 'ri-map-pin-line' }[status]"></i>
                            </span>
                            <div class="min-w-0 grow leading-snug">
                                <p class="font-semibold" x-text="{ locating: 'Mengecek lokasi…', inside: 'Lokasi terverifikasi · ' + location, outside: 'Di luar area absensi', unverified: 'Lokasi belum terverifikasi', none: 'Lokasi' }[status]">Mengecek lokasi…</p>
                                <p class="mt-0.5 text-sm text-fog" x-text="message"></p>
                                <button type="button" x-show="status !== 'locating' && status !== 'inside'" x-cloak @click="check()" class="mt-1.5 inline-flex items-center gap-1 text-sm font-semibold text-neon"><i class="ri-refresh-line"></i> Cek ulang</button>
                            </div>
                        </section>
                    @endif
                </div>
                @error('geo') <p class="error !mt-0 rounded-xl bg-alarm/10 px-3 py-2"><i class="ri-map-pin-range-line mt-0.5"></i>{{ $message }}</p> @enderror
                @error('device') <p class="error !mt-0 rounded-xl bg-alarm/10 px-3 py-2"><i class="ri-smartphone-line mt-0.5"></i>{{ $message }}</p> @enderror
                {{-- Identitas dari akun --}}
                <section class="flex items-center gap-3 rounded-2xl border border-white/[0.07] bg-bench p-3.5">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-neon font-display font-bold text-[#04170d]">{{ $user->initials() }}</span>
                    <div class="min-w-0 grow leading-tight">
                        <p class="truncate font-semibold">{{ $user->displayName() }}</p>
                        <p class="truncate text-sm text-mist"><span class="font-display text-neon-soft">{{ $user->nrp }}</span> · {{ $user->department ?: '–' }} · {{ $user->position ?: '–' }}</p>
                    </div>
                    <i class="ri-shield-check-fill text-xl text-neon" title="Data dari akun Anda"></i>
                </section>
                @unless ($targeted)
                    <p class="rounded-xl bg-hivis/[0.07] px-3 py-2 text-sm text-hivis"><i class="ri-information-line"></i> Seksi Anda bukan peserta utama sesi ini, tapi Anda tetap bisa absen.</p>
                @endunless

                @if ($session->type->allow_speaker)
                    <section>
                        <p class="label">Peran Anda</p>
                        <div class="grid grid-cols-2 gap-2" x-data="{ role: @js(old('role', 'audience')) }">
                            @foreach (['audience' => ['Audience', 'ri-group-line', 'Peserta'], 'pemateri' => ['Pemateri', 'ri-mic-line', 'Membawakan materi']] as $key => [$label, $icon, $hint])
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border p-3.5 transition" :class="role === '{{ $key }}' ? 'border-neon/60 bg-neon/[0.08]' : 'border-white/10'">
                                    <input type="radio" name="role" value="{{ $key }}" x-model="role" class="sr-only">
                                    <i class="{{ $icon }} text-2xl" :class="role === '{{ $key }}' ? 'text-neon' : 'text-mist'"></i>
                                    <span class="leading-tight"><span class="block font-semibold">{{ $label }}</span><span class="text-xs text-mist">{{ $hint }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        @error('role') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
                    </section>
                @endif

                @if ($session->type->track_fatigue)
                    <section class="rounded-2xl border border-white/[0.07] bg-bench p-4">
                        <p class="flex items-center gap-2 font-semibold"><i class="ri-moon-clear-line text-xl text-neon"></i> Cek fatigue</p>
                        <p class="mt-0.5 text-sm text-mist">Jam tidur terakhir sebelum masuk kerja.</p>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label class="label" for="sleep_at">Mulai tidur</label>
                                <input id="sleep_at" name="sleep_at" type="time" x-model="sleep" required class="field h-14 text-center font-display text-xl">
                            </div>
                            <div>
                                <label class="label" for="wake_at">Bangun</label>
                                <input id="wake_at" name="wake_at" type="time" x-model="wake" required class="field h-14 text-center font-display text-xl">
                            </div>
                        </div>
                        @error('sleep_at') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
                        @error('wake_at') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror

                        {{-- Meter durasi tidur --}}
                        <div x-show="minutes !== null" x-cloak class="mt-4">
                            <div class="flex items-baseline justify-between">
                                <span class="font-display text-2xl font-bold" :class="toneText" x-text="label"></span>
                                <span class="chip" :class="toneChip" x-text="status"></span>
                            </div>
                            <div class="relative mt-2 h-2.5 overflow-hidden rounded-full bg-white/[0.06]">
                                <div class="absolute inset-y-0 left-[62.5%] w-px bg-white/30"></div>
                                <div class="absolute inset-y-0 left-[75%] w-px bg-white/30"></div>
                                <div class="h-full rounded-full transition-all duration-300" :class="toneBar" :style="`width: ${Math.min(100, minutes / 480 * 100)}%`"></div>
                            </div>
                            <div class="relative mt-1 h-4 text-[11px] text-mist"><span class="absolute left-0">0</span><span class="absolute left-[62.5%] -translate-x-1/2">5 j</span><span class="absolute left-[75%] -translate-x-1/2">6 j</span><span class="absolute right-0">8 j</span></div>
                        </div>
                    </section>
                @endif

                <div x-data="{ geoBlocked: @js($checkGps) }" @geo-state.window="geoBlocked = $event.detail.blocked">
                    <button class="btn-neon w-full !min-h-14 text-base disabled:opacity-40" :disabled="geoBlocked"><i class="ri-fingerprint-line text-xl"></i> Absen sekarang</button>
                    <p class="mt-2 text-center text-xs text-mist">Tercatat atas nama Anda pada jam server{{ $session->late_after !== null ? '. '.$session->late_after.' menit setelah pukul '.$session->starts_at->format('H:i').' dihitung terlambat' : '' }}. Satu HP untuk satu karyawan.</p>
                </div>
            </form>
        @endif
    </div>
</x-layouts.portal>
