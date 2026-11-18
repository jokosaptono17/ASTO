<x-layouts.admin title="Ringkasan">
    <x-slot:actions>
        <a href="{{ route('admin.campaigns.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Kampanye baru</a>
        <a href="{{ route('admin.announcements.create') }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-megaphone-line text-lg"></i> Pengumuman</a>
    </x-slot:actions>

    @php
        $activation = $stats['users'] ? round($stats['activated'] / $stats['users'] * 100) : 0;
        $max = max(1, $series->max('visits'), $series->max('clicks'));
    @endphp

    {{-- Angka utama --}}
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="a-card p-4">
            <p class="text-sm text-mist">Akun aktif</p>
            <p class="mt-1 font-display text-3xl font-bold">{{ $stats['activated'] }}<span class="text-lg text-mist">/{{ $stats['users'] }}</span></p>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/[0.06]"><div class="h-full rounded-full bg-neon" style="width: {{ $activation }}%"></div></div>
            <p class="mt-1.5 text-xs text-mist">{{ $activation }}% sudah aktivasi</p>
        </div>
        <div class="a-card p-4">
            <p class="text-sm text-mist">Pengguna 7 hari</p>
            <p class="mt-1 font-display text-3xl font-bold">{{ $stats['active7'] }}</p>
            <p class="mt-3 text-xs text-mist">{{ $loginsToday }} masuk hari ini</p>
        </div>
        <div class="a-card p-4">
            <p class="text-sm text-mist">Aplikasi dibuka</p>
            <p class="mt-1 font-display text-3xl font-bold">{{ number_format($stats['visits7'], 0, ',', '.') }}</p>
            <p class="mt-3 text-xs text-mist">7 hari terakhir · {{ $stats['apps'] }} aplikasi aktif</p>
        </div>
        <div class="a-card p-4">
            <p class="text-sm text-mist">Klik kampanye</p>
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
