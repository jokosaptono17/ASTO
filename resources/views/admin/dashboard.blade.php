<x-layouts.admin title="Ringkasan" :hide-heading="true">
    @php
        $activation = $stats['users'] ? round($stats['activated'] / $stats['users'] * 100) : 0;
        $max = max(1, $series->max('visits'), $series->max('clicks'));
        $hero = \App\Models\Setting::hero();
        $hour = now()->hour;
        $greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
        $me = auth()->user();
    @endphp

    {{-- Sapaan dengan foto latar --}}
    <section class="relative mb-5 overflow-hidden rounded-3xl border border-white/[0.08]">
        <img src="{{ $hero['src'] }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-[linear-gradient(100deg,rgba(6,18,13,.95)_0%,rgba(6,18,13,.75)_45%,rgba(6,18,13,.35)_100%)]"></div>
        <x-contour class="absolute inset-0 h-full w-full opacity-25 mix-blend-screen" />
        <div class="relative flex flex-wrap items-end gap-6 p-6 lg:p-8">
            <div class="grow">
                <p class="text-sm text-fog">{{ now()->locale('id')->translatedFormat('l, j F Y') }}</p>
                <h1 class="mt-1 font-display text-[1.9rem] leading-tight font-bold lg:text-[2.4rem]">{{ $greet }}, {{ $me->firstName() }}</h1>
                <p class="mt-2 max-w-2xl text-fog">
                    @if ($attendance['open'])
                        <span class="font-semibold text-neon">{{ $attendance['open'] }} absen sedang dibuka</span> ·
                    @endif
                    {{ $attendance['present'] }} kehadiran hari ini
                    @if ($attendance['risk']) · <span class="font-semibold text-red-300">{{ $attendance['risk'] }} fatigue risk</span> @endif
                    · {{ $stats['campaignsLive'] }} kampanye tayang · {{ $stats['apps'] }} aplikasi aktif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.campaigns.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Kampanye baru</a>
                <a href="{{ route('admin.announcements.create') }}" class="btn-ghost !min-h-11 bg-black/30 text-sm backdrop-blur-md"><i class="ri-megaphone-line text-lg"></i> Pengumuman</a>
                <a href="{{ route('admin.attendance.create') }}" class="btn-ghost !min-h-11 bg-black/30 text-sm backdrop-blur-md"><i class="ri-calendar-event-line text-lg"></i> Event absensi</a>
            </div>
        </div>
    </section>

    {{-- Angka utama --}}
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="a-card p-4">
            <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-shield-user-line text-lg text-neon"></i>Akun aktif</p>
            <p class="mt-1 font-display text-3xl font-bold">{{ $stats['activated'] }}<span class="text-lg text-mist">/{{ $stats['users'] }}</span></p>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/[0.06]"><div class="h-full rounded-full bg-neon" style="width: {{ $activation }}%"></div></div>
            <p class="mt-1.5 text-xs text-mist">{{ $activation }}% sudah aktivasi</p>
        </div>
        <div class="a-card p-4">
            <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-user-follow-line text-lg text-neon"></i>Pengguna 7 hari</p>
            <p class="mt-1 font-display text-3xl font-bold">{{ $stats['active7'] }}</p>
            <p class="mt-3 text-xs text-mist">{{ $loginsToday }} masuk hari ini</p>
        </div>
        <div class="a-card p-4">
            <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-cursor-line text-lg text-neon"></i>Aplikasi dibuka</p>
            <p class="mt-1 font-display text-3xl font-bold">{{ number_format($stats['visits7'], 0, ',', '.') }}</p>
            <p class="mt-3 text-xs text-mist">7 hari terakhir · {{ $stats['apps'] }} aplikasi aktif</p>
        </div>
        <div class="a-card p-4">
            <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-image-2-line text-lg text-hivis"></i>Klik kampanye</p>
            <p class="mt-1 font-display text-3xl font-bold">{{ number_format($stats['clicks7'], 0, ',', '.') }}</p>
            <p class="mt-3 text-xs text-mist">7 hari terakhir · {{ $stats['campaignsLive'] }} sedang tayang</p>
        </div>
    </div>

    @if ($downApps->isNotEmpty())
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-alarm/30 bg-alarm/[0.07] p-4 text-sm">
            <i class="ri-signal-wifi-error-line text-xl text-alarm"></i>
            <span class="font-semibold">Tidak merespons saat terakhir dicek:</span>
            @foreach ($downApps as $app) <span class="chip-nonaktif">{{ $app->name }}</span> @endforeach
        </div>
    @endif

    <div class="mt-4 grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        {{-- Grafik 7 hari --}}
        <section class="a-card p-5">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                <h2 class="grow font-display text-lg font-semibold">Aktivitas 7 hari</h2>
                <span class="flex items-center gap-1.5 text-xs text-mist"><span class="h-2.5 w-2.5 rounded-sm bg-neon"></span>Aplikasi dibuka</span>
                <span class="flex items-center gap-1.5 text-xs text-mist"><span class="h-2.5 w-2.5 rounded-sm bg-hivis"></span>Klik kampanye</span>
            </div>
            <div class="mt-5 grid h-48 grid-cols-7 items-end gap-3" role="img" aria-label="Grafik aktivitas 7 hari">
                @foreach ($series as $day)
                    <div class="flex h-full flex-col items-center justify-end gap-2" title="{{ $day['date'] }}: {{ $day['visits'] }} dibuka, {{ $day['clicks'] }} klik">
                        <div class="flex h-full w-full items-end justify-center gap-1">
                            <div class="w-1/3 max-w-5 rounded-t-md bg-neon/85" style="height: {{ max(2, $day['visits'] / $max * 100) }}%"></div>
                            <div class="w-1/3 max-w-5 rounded-t-md bg-hivis/85" style="height: {{ max(2, $day['clicks'] / $max * 100) }}%"></div>
                        </div>
                        <span class="text-xs text-mist">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Absensi hari ini --}}
        <section class="a-card flex flex-col p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold">Absensi hari ini</h2>
                <a href="{{ route('admin.attendance.index') }}" class="section-link">Kelola</a>
            </div>
            <div class="mt-4 grid grid-cols-4 gap-2 text-center">
                <div class="rounded-xl bg-white/[0.04] py-2.5"><p class="font-display text-2xl font-bold">{{ $attendance['present'] }}</p><p class="text-[11px] text-mist">hadir</p></div>
                <div class="rounded-xl bg-white/[0.04] py-2.5"><p class="font-display text-2xl font-bold {{ $attendance['coaching'] ? 'text-hivis' : '' }}">{{ $attendance['coaching'] }}</p><p class="text-[11px] text-mist">coaching</p></div>
                <div class="rounded-xl bg-white/[0.04] py-2.5"><p class="font-display text-2xl font-bold {{ $attendance['risk'] ? 'text-alarm' : '' }}">{{ $attendance['risk'] }}</p><p class="text-[11px] text-mist">fatigue risk</p></div>
                <div class="rounded-xl bg-white/[0.04] py-2.5"><p class="font-display text-2xl font-bold {{ $attendance['unverified'] ? 'text-hivis' : '' }}">{{ $attendance['unverified'] }}</p><p class="text-[11px] text-mist">lokasi ?</p></div>
            </div>
            <ul class="mt-3 grow divide-y divide-white/[0.05]">
                @forelse ($todaySessions as $s)
                    @php $pct = $s->expected_count ? min(100, round($s->records_count / max(1, $s->expected_count) * 100)) : null; @endphp
                    <li>
                        <a href="{{ route('admin.attendance.show', $s) }}" class="flex items-center gap-3 py-2.5">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl" style="background: color-mix(in oklab, {{ $s->type->color }} 16%, transparent); color: {{ $s->type->color }}"><i class="{{ $s->type->icon }}"></i></span>
                            <span class="min-w-0 grow">
                                <span class="flex items-center gap-2 text-sm font-medium"><span class="truncate">{{ $s->displayTitle() }}</span>
                                    @if ($s->isOpen()) <span class="h-1.5 w-1.5 shrink-0 animate-pulse rounded-full bg-neon"></span> @endif</span>
                                <span class="text-xs text-mist">{{ $s->opens_at->format('H:i') }}–{{ $s->closes_at->format('H:i') }} · {{ $s->statusLabel() }}
                                    @if ($s->risk_count) · <span class="text-red-300">{{ $s->risk_count }} risk</span> @endif</span>
                                @if ($pct !== null)
                                    <span class="mt-1 block h-1 overflow-hidden rounded-full bg-white/[0.06]"><span class="block h-full rounded-full bg-neon" style="width: {{ $pct }}%"></span></span>
                                @endif
                            </span>
                            <span class="shrink-0 font-display text-lg font-bold">{{ $s->records_count }}<span class="text-sm text-mist">{{ $s->expected_count ? '/'.$s->expected_count : '' }}</span></span>
                        </a>
                    </li>
                @empty
                    <li class="py-3 text-sm text-mist">Tidak ada sesi absensi hari ini.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        {{-- Aplikasi terpopuler --}}
        <section class="a-card p-5">
            <h2 class="font-display text-lg font-semibold">Terpopuler 30 hari</h2>
            @php $topMax = max(1, $topApps->max('total')); @endphp
            <ul class="mt-4 space-y-3">
                @forelse ($topApps as $row)
                    <li class="flex items-center gap-3">
                        <x-app-icon :app="$row->app" variant="chip" :size="34" />
                        <div class="min-w-0 grow">
                            <div class="flex justify-between gap-2 text-sm"><span class="truncate font-medium">{{ $row->app->name }}</span><span class="font-display text-mist">{{ $row->total }}</span></div>
                            <div class="mt-1 h-1.5 rounded-full bg-white/[0.06]"><div class="h-full rounded-full" style="width: {{ $row->total / $topMax * 100 }}%; background: {{ $row->app->color }}"></div></div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-mist">Belum ada aplikasi yang dibuka lewat portal.</li>
                @endforelse
            </ul>
        </section>

        {{-- Aktivasi akun per seksi --}}
        <section class="a-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold">Aktivasi per seksi</h2>
                <a href="{{ route('admin.users.index') }}" class="section-link">Pengguna</a>
            </div>
            <ul class="mt-4 space-y-3">
                @forelse ($byDept as $d)
                    @php $p = $d->total ? round($d->activated / $d->total * 100) : 0; @endphp
                    <li>
                        <div class="flex justify-between gap-2 text-sm"><span class="truncate">{{ $d->department }}</span><span class="shrink-0 font-display text-mist">{{ (int) $d->activated }}/{{ $d->total }}</span></div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/[0.06]"><div class="h-full rounded-full {{ $p >= 80 ? 'bg-neon' : ($p >= 40 ? 'bg-hivis' : 'bg-alarm') }}" style="width: {{ max(2, $p) }}%"></div></div>
                    </li>
                @empty
                    <li class="text-sm text-mist">Belum ada data seksi.</li>
                @endforelse
            </ul>
        </section>

        {{-- Kesehatan aplikasi --}}
        <section class="a-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold">Status aplikasi</h2>
                <a href="{{ route('admin.apps.index') }}" class="section-link">Kelola</a>
            </div>
            <ul class="mt-3 divide-y divide-white/[0.05]">
                @forelse ($health->take(8) as $app)
                    <li class="flex items-center gap-3 py-2">
                        <x-app-icon :app="$app" variant="chip" :size="30" />
                        <span class="min-w-0 grow truncate text-sm">{{ $app->name }}</span>
                        @if ($app->health_status === 'up')
                            <span class="flex items-center gap-1.5 text-xs text-neon"><span class="h-1.5 w-1.5 rounded-full bg-neon"></span>{{ $app->health_ms ? $app->health_ms.' ms' : 'Normal' }}</span>
                        @elseif ($app->health_status === 'down')
                            <span class="chip-nonaktif">Gangguan</span>
                        @else
                            <span class="text-xs text-mist">Belum dicek</span>
                        @endif
                    </li>
                @empty
                    <li class="py-3 text-sm text-mist">Tidak ada aplikasi yang dipantau.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <section class="a-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold">Jadwal kampanye</h2>
                <a href="{{ route('admin.campaigns.index') }}" class="section-link">Kelola</a>
            </div>
            <ul class="mt-3 divide-y divide-white/[0.05]">
                @forelse ($endingSoon->concat($scheduled) as $c)
                    <li class="flex items-center gap-3 py-2.5">
                        <img src="{{ $c->imageUrl() }}" alt="" class="h-12 w-10 shrink-0 rounded-lg object-cover">
                        <a href="{{ route('admin.campaigns.edit', $c) }}" class="min-w-0 grow">
                            <span class="block truncate text-sm font-medium">{{ $c->title }}</span>
                            <span class="text-xs text-mist">
                                @if ($c->status() === 'terjadwal') Mulai {{ $c->starts_at->locale('id')->translatedFormat('j M, H:i') }}
                                @else Berakhir {{ $c->ends_at->locale('id')->diffForHumans() }} @endif
                            </span>
                        </a>
                        <span class="chip-{{ $c->status() }}">{{ ucfirst($c->status()) }}</span>
                    </li>
                @empty
                    <li class="py-3 text-sm text-mist">Tidak ada kampanye yang akan berakhir minggu ini atau yang terjadwal.</li>
                @endforelse
            </ul>
        </section>

        <section class="a-card p-5">
            <h2 class="font-display text-lg font-semibold">Aktivitas admin</h2>
            <ul class="mt-3 space-y-3">
                @forelse ($activities as $log)
                    <li class="flex gap-3 text-sm">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-neon/70"></span>
                        <span class="min-w-0 grow">
                            <span class="text-fog">{{ $log->description }}</span>
                            <span class="block text-xs text-mist">{{ $log->user?->displayName() ?? 'Sistem' }} · {{ $log->created_at->locale('id')->diffForHumans() }}</span>
                        </span>
                    </li>
                @empty
                    <li class="text-sm text-mist">Belum ada aktivitas.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.admin>
