{{-- Kartu "Absensi hari ini" di beranda --}}
@php
    $primary = $attendance['pending']->first();
    $done = $attendance['done']->last();
    $next = $attendance['next'];
    $stats = $attendance['stats'];
    $state = $primary ? 'open' : ($done ? 'done' : ($next ? 'next' : 'idle'));
    $avg = $stats['avgSleep'];
    $fatigue = $done?->myRecord?->fatigue ? \App\Models\AttendanceRecord::FATIGUE[$done->myRecord->fatigue] : null;
    $tone = ['neon' => 'text-neon', 'hivis' => 'text-hivis', 'alarm' => 'text-alarm'];
@endphp
<section class="mx-4 mt-4 lg:mx-0 lg:mt-0"
         x-data="homeAttendance(@js(route('attendance.locate')), @js(match ($state) { 'open' => [$primary->opens_at->timestamp, $primary->closes_at->timestamp], 'next' => [null, $next->opens_at->timestamp], 'done' => $next ? [null, $next->opens_at->timestamp] : null, default => null }))">
    <div class="relative overflow-hidden rounded-[24px] border {{ $state === 'open' ? 'border-neon/40 bg-gradient-to-br from-neon/[0.13] via-bench to-bench' : 'border-white/[0.08] bg-bench' }}">
        <x-contour class="pointer-events-none absolute -top-12 -right-16 h-52 w-72 opacity-25" />

        <header class="relative flex items-center gap-2 px-4 pt-3.5">
            <p class="font-display text-[15px] font-semibold">Absensi hari ini</p>
            <span class="text-xs text-mist">{{ now()->locale('id')->translatedFormat('l, j M') }}</span>
            <span x-show="place" x-cloak class="chip ml-auto !py-0.5 text-[11px]" :class="place?.status === 'inside' ? 'bg-neon/15 text-neon' : 'bg-white/[0.07] text-fog'">
                <i :class="place?.status === 'inside' ? 'ri-map-pin-2-fill' : 'ri-map-pin-line'"></i><span x-text="placeText"></span>
            </span>
        </header>

        <div class="relative px-4 pt-3 pb-4">
            @if ($state === 'open')
                <div class="flex items-center gap-3">
                    <span class="relative grid h-14 w-14 shrink-0 place-items-center rounded-2xl" style="background: color-mix(in oklab, {{ $primary->type->color }} 18%, transparent); color: {{ $primary->type->color }}">
                        <i class="{{ $primary->type->icon }} text-[28px]"></i>
                        <span class="absolute -top-1 -right-1 h-3.5 w-3.5 animate-ping rounded-full bg-neon"></span><span class="absolute -top-1 -right-1 h-3.5 w-3.5 rounded-full border-2 border-bench bg-neon"></span>
                    </span>
                    <div class="min-w-0 grow leading-tight">
                        <p class="truncate font-display text-lg font-bold">{{ $primary->type->name }}{{ $primary->title ? ' — '.$primary->title : '' }}</p>
                        <p class="mt-0.5 truncate text-sm text-mist">{{ $primary->opens_at->format('H:i') }}–{{ $primary->closes_at->format('H:i') }}{{ ($place = $primary->locationLabel()) ? ' · '.$place : '' }}</p>
                    </div>
                </div>
                <div class="mt-3.5">
                    <div class="flex items-baseline justify-between text-xs">
                        <span class="font-semibold text-neon">Absen sedang dibuka</span>
                        <span class="text-fog">tutup <span class="font-display font-semibold text-ink" x-text="countdown"></span> lagi</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/[0.07]">
                        <div class="h-full rounded-full bg-gradient-to-r from-neon to-hivis transition-[width] duration-1000" :style="`width: ${progress}%`"></div>
                    </div>
                </div>
                <a href="{{ $primary->checkin_mode === 'qr' ? route('attendance.scan') : route('attendance.checkin', $primary->code) }}" class="btn-neon mt-4 w-full !min-h-12">
                    <i class="{{ $primary->checkin_mode === 'qr' ? 'ri-qr-scan-2-line' : 'ri-fingerprint-line' }} text-xl"></i> {{ $primary->checkin_mode === 'qr' ? 'Scan QR di layar' : 'Absen sekarang' }}
                </a>
                @if ($attendance['pending']->count() > 1)
                    <a href="{{ route('attendance.scan') }}" class="mt-2 block text-center text-sm font-semibold text-neon-soft">+{{ $attendance['pending']->count() - 1 }} kegiatan lain sedang dibuka <i class="ri-arrow-right-s-line"></i></a>
                @endif

            @elseif ($state === 'done')
                <a href="{{ route('attendance.checkin', $done->code) }}" class="flex items-center gap-3">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-neon text-[#04170d]"><i class="ri-check-line text-3xl"></i></span>
                    <div class="min-w-0 grow leading-tight">
                        <p class="truncate font-display text-lg font-bold">Sudah absen {{ $done->type->name }}{{ $done->title && $done->isRoutine() ? ' '.$done->title : '' }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-mist">
                            <span><i class="ri-time-line"></i> {{ $done->myRecord?->checked_in_at->format('H:i') }}</span>
                            @if ($done->myRecord?->geo_status === 'inside') <span class="text-neon-soft"><i class="ri-map-pin-2-fill"></i> {{ $done->myRecord->location?->name }}</span> @endif
                            @if ($fatigue) <span class="{{ $tone[$fatigue['tone']] }}"><i class="{{ $fatigue['icon'] }}"></i> {{ $fatigue['label'] }}</span> @endif
                        </p>
                    </div>
                </a>
                @if ($next)
                    <p class="mt-3 flex items-center gap-2 rounded-xl bg-white/[0.04] px-3 py-2 text-sm text-fog">
                        <i class="ri-hourglass-2-line text-hivis"></i>
                        <span class="min-w-0 grow truncate">Berikutnya {{ $next->type->name }}{{ $next->title ? ' '.$next->title : '' }} · {{ $next->opens_at->format('H:i') }}</span>
                        <span class="font-display font-semibold text-ink" x-text="countdown"></span>
                    </p>
                @endif

            @elseif ($state === 'next')
                <div class="flex items-center gap-4">
                    <div class="grow leading-tight">
                        <p class="text-sm text-mist">Berikutnya</p>
                        <p class="mt-0.5 font-display text-lg font-bold">{{ $next->type->name }}{{ $next->title ? ' '.$next->title : '' }}</p>
                        <p class="mt-0.5 text-sm text-mist">Dibuka {{ $next->opens_at->isToday() ? '' : 'besok ' }}{{ $next->opens_at->format('H:i') }}–{{ $next->closes_at->format('H:i') }}</p>
                    </div>
                    <div class="shrink-0 rounded-2xl border border-hivis/30 bg-hivis/[0.08] px-3.5 py-2 text-center">
                        <p class="font-display text-2xl leading-none font-bold text-hivis" x-text="countdownShort">–</p>
                        <p class="mt-1 text-[11px] text-fog">lagi</p>
                    </div>
                </div>

            @else
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/[0.06] text-mist"><i class="ri-calendar-check-line text-2xl"></i></span>
                    <div class="leading-tight">
                        <p class="font-semibold">Tidak ada kegiatan yang dibuka</p>
                        <p class="mt-0.5 text-sm text-mist">{{ $attendance['last'] ? 'Terakhir absen '.$attendance['last']->session->type->name.' · '.$attendance['last']->checked_in_at->locale('id')->diffForHumans() : 'Absen P5M & safety talk muncul di sini saat dibuka.' }}</p>
                    </div>
                </div>
            @endif

            {{-- Linimasa kegiatan hari ini --}}
            @if ($attendance['timeline']->count() > 1 || ($attendance['timeline']->count() === 1 && $state === 'next'))
                <ol class="mt-4 flex gap-1.5 overflow-x-auto pb-0.5" aria-label="Kegiatan hari ini">
                    @foreach ($attendance['timeline'] as $t)
                        <li class="flex min-w-0 flex-1 basis-0 flex-col rounded-xl px-2.5 py-2 {{ ['done' => 'bg-neon/10', 'open' => 'bg-neon/[0.06] ring-1 ring-neon/40', 'later' => 'bg-white/[0.04]', 'missed' => 'bg-white/[0.03]'][$t['state']] }}">
                            <span class="flex items-center gap-1.5 text-[11px] {{ ['done' => 'text-neon', 'open' => 'text-neon', 'later' => 'text-mist', 'missed' => 'text-mist/70'][$t['state']] }}">
                                <i class="{{ ['done' => 'ri-checkbox-circle-fill', 'open' => 'ri-record-circle-line animate-pulse', 'later' => 'ri-time-line', 'missed' => 'ri-close-circle-line'][$t['state']] }}"></i>
                                {{ $t['state'] === 'done' ? $t['at'] : $t['time'] }}
                            </span>
                            <span class="mt-0.5 truncate text-[13px] font-semibold {{ $t['state'] === 'missed' ? 'text-mist line-through decoration-white/20' : '' }}">{{ $t['short'] }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        {{-- Statistik bulan ini --}}
        <a href="{{ route('attendance.history') }}" class="relative grid grid-cols-3 divide-x divide-white/[0.06] border-t border-white/[0.06] text-center">
            <span class="px-2 py-2.5"><span class="block font-display text-lg leading-none font-bold">{{ $stats['month'] }}</span><span class="mt-1 block text-[11px] text-mist">hadir bulan ini</span></span>
            <span class="px-2 py-2.5"><span class="block font-display text-lg leading-none font-bold {{ $avg && $avg < 300 ? 'text-alarm' : ($avg && $avg < 360 ? 'text-hivis' : '') }}">{{ $avg ? intdiv($avg, 60).'j '.($avg % 60).'m' : '–' }}</span><span class="mt-1 block text-[11px] text-mist">rata-rata tidur</span></span>
            <span class="px-2 py-2.5"><span class="block font-display text-lg leading-none font-bold {{ $stats['streak'] >= 3 ? 'text-hivis' : '' }}">{{ $stats['streak'] >= 3 ? '🔥 ' : '' }}{{ $stats['streak'] }}</span><span class="mt-1 block text-[11px] text-mist">hari beruntun</span></span>
        </a>
    </div>
</section>
