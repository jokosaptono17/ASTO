<x-layouts.admin title="Rekap absensi">
    <x-slot:actions>
        <a href="{{ route('admin.attendance.recap.export', request()->query()) }}" class="btn-neon !min-h-11 text-sm"><i class="ri-file-excel-2-line text-lg"></i> Ekspor Excel</a>
    </x-slot:actions>
    @include('admin.attendance._tabs')

    {{-- Filter --}}
    <form class="a-card mb-5 grid gap-3 p-4 sm:grid-cols-3 xl:grid-cols-7">
        <div><label class="label text-xs" for="from">Dari</label><input id="from" name="from" type="date" value="{{ $from->format('Y-m-d') }}" class="field !py-2"></div>
        <div><label class="label text-xs" for="to">Sampai</label><input id="to" name="to" type="date" value="{{ $to->format('Y-m-d') }}" class="field !py-2"></div>
        <div><label class="label text-xs" for="type">Kegiatan</label>
            <select id="type" name="type" class="field !py-2"><option value="">Semua</option>@foreach ($types as $t)<option value="{{ $t->id }}" @selected(request('type') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
        <div><label class="label text-xs" for="department">Seksi</label>
            <select id="department" name="department" class="field !py-2"><option value="">Semua</option>@foreach ($departments as $d)<option @selected(request('department') === $d)>{{ $d }}</option>@endforeach</select></div>
        <div><label class="label text-xs" for="fatigue">Fatigue</label>
            <select id="fatigue" name="fatigue" class="field !py-2"><option value="">Semua</option>@foreach (\App\Models\AttendanceRecord::FATIGUE as $k => $f)<option value="{{ $k }}" @selected(request('fatigue') === $k)>{{ $f['label'] }}</option>@endforeach</select></div>
        <div><label class="label text-xs" for="q">Nama / NRP</label><input id="q" name="q" value="{{ request('q') }}" class="field !py-2"></div>
        <div class="flex items-end gap-2">
            <label class="flex h-[42px] items-center gap-2 text-sm text-fog"><input type="checkbox" name="late" value="1" @checked(request('late') === '1') class="h-4 w-4 accent-[var(--color-neon)]"> Terlambat</label>
            <label class="flex h-[42px] items-center gap-2 text-sm text-fog"><input type="checkbox" name="geo" value="unverified" @checked(request('geo') === 'unverified') class="h-4 w-4 accent-[var(--color-neon)]"> Lokasi belum pasti</label>
            <button class="btn-neon !min-h-[42px] grow !px-3 text-sm"><i class="ri-filter-3-line"></i></button>
        </div>
    </form>

    @php $fatigueTotal = max(1, $stats['fit'] + $stats['coaching'] + $stats['risk']); $maxDay = max(1, $days->max('total')); @endphp
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-5">
        @foreach ([['Sesi', $stats['sessions'], ''], ['Kehadiran', $stats['total'], 'text-neon'], ['Orang berbeda', $stats['people'], ''], ['Terlambat', $stats['late'].($stats['total'] ? ' ('.round($stats['late'] / $stats['total'] * 100).'%)' : ''), $stats['late'] ? 'text-hivis' : ''], ['Fatigue risk', $stats['risk'], $stats['risk'] ? 'text-alarm' : '']] as [$label, $value, $tone])
            <div class="a-card p-4"><p class="text-xs text-mist">{{ $label }}</p><p class="mt-1 font-display text-2xl font-bold {{ $tone }}">{{ $value }}</p></div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        <section class="a-card p-5">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <h2 class="grow font-display text-lg font-semibold">Kehadiran per hari</h2>
                <span class="flex items-center gap-1.5 text-xs text-mist"><span class="h-2.5 w-2.5 rounded-sm bg-neon"></span>Hadir</span>
                <span class="flex items-center gap-1.5 text-xs text-mist"><span class="h-2.5 w-2.5 rounded-sm bg-hivis"></span>Terlambat</span>
            </div>
            <div class="mt-5 flex h-44 items-end gap-[3px]" role="img" aria-label="Grafik kehadiran per hari">
                @foreach ($days as $d)
                    <div class="group relative flex h-full min-w-0 flex-1 flex-col justify-end" title="{{ $d['date']->locale('id')->translatedFormat('j M') }}: {{ $d['total'] }} hadir, {{ $d['late'] }} terlambat">
                        <div class="w-full rounded-t-[3px] bg-neon/85" style="height: {{ $d['total'] ? max(3, ($d['total'] - $d['late']) / $maxDay * 100) : 0 }}%"></div>
                        <div class="w-full bg-hivis/90" style="height: {{ $d['late'] / $maxDay * 100 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[11px] text-mist"><span>{{ $from->locale('id')->translatedFormat('j M') }}</span><span>{{ $to->locale('id')->translatedFormat('j M Y') }}</span></div>
        </section>

        <section class="a-card p-5">
            <h2 class="font-display text-lg font-semibold">Status fatigue</h2>
            <div class="mt-4 flex h-4 overflow-hidden rounded-full bg-white/[0.06]">
                <div class="bg-neon" style="width: {{ $stats['fit'] / $fatigueTotal * 100 }}%"></div>
                <div class="bg-hivis" style="width: {{ $stats['coaching'] / $fatigueTotal * 100 }}%"></div>
                <div class="bg-alarm" style="width: {{ $stats['risk'] / $fatigueTotal * 100 }}%"></div>
            </div>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach (['fit' => 'bg-neon', 'coaching' => 'bg-hivis', 'risk' => 'bg-alarm'] as $k => $bg)
                    <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm {{ $bg }}"></span><span class="grow">{{ \App\Models\AttendanceRecord::FATIGUE[$k]['label'] }}</span><span class="font-display">{{ $stats[$k] }}</span><span class="w-12 text-right text-mist">{{ round($stats[$k] / $fatigueTotal * 100) }}%</span></li>
                @endforeach
            </ul>
            @if ($speakers->isNotEmpty())
                <h3 class="mt-6 text-sm font-semibold text-fog">Pemateri teraktif</h3>
                <ul class="mt-2 space-y-1.5 text-sm">
                    @foreach ($speakers as $sp)<li class="flex justify-between"><span class="truncate">⭐ {{ \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($sp['name'])) }}</span><span class="font-display text-mist">{{ $sp['total'] }}×</span></li>@endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="a-card mt-4 overflow-x-auto">
        <h2 class="px-4 pt-4 font-display text-lg font-semibold">Per seksi</h2>
        <table class="mt-2 w-full min-w-[560px]">
            <thead><tr><th class="a-th">Seksi</th><th class="a-th text-right">Kehadiran</th><th class="a-th text-right">Orang</th><th class="a-th text-right">Terlambat</th><th class="a-th text-right">Coaching</th><th class="a-th text-right">Fatigue risk</th></tr></thead>
            <tbody>
                @forelse ($sections as $s)
                    <tr><td class="a-td font-medium">{{ $s['name'] }}</td><td class="a-td text-right font-display">{{ $s['total'] }}</td><td class="a-td text-right font-display">{{ $s['people'] }}</td>
                        <td class="a-td text-right font-display {{ $s['late'] ? 'text-hivis' : 'text-mist' }}">{{ $s['late'] }}</td><td class="a-td text-right font-display {{ $s['coaching'] ? 'text-hivis' : 'text-mist' }}">{{ $s['coaching'] }}</td><td class="a-td text-right font-display {{ $s['risk'] ? 'text-alarm' : 'text-mist' }}">{{ $s['risk'] }}</td></tr>
                @empty
                    <tr><td colspan="6" class="a-td py-8 text-center text-mist">Belum ada data pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="a-card mt-4 overflow-x-auto">
        <h2 class="px-4 pt-4 font-display text-lg font-semibold">Detail kehadiran</h2>
        <table class="mt-2 w-full min-w-[760px]">
            <thead><tr><th class="a-th">Waktu</th><th class="a-th">Kegiatan</th><th class="a-th">Karyawan</th><th class="a-th">Seksi</th><th class="a-th">Tidur</th><th class="a-th">Status</th></tr></thead>
            <tbody>
                @forelse ($records as $r)
                    @php $f = $r->fatigue ? \App\Models\AttendanceRecord::FATIGUE[$r->fatigue] : null; @endphp
                    <tr>
                        <td class="a-td whitespace-nowrap"><span class="font-display">{{ $r->checked_in_at->format('d/m H:i') }}</span>@if ($r->is_late) <span class="chip-penting ml-1 !py-0">Telat</span>@endif</td>
                        <td class="a-td"><a href="{{ route('admin.attendance.show', $r->session) }}" class="hover:text-neon"><span style="color: {{ $r->session->type->color }}">●</span> {{ $r->session->type->name }}</a><div class="max-w-[220px] truncate text-xs text-mist">{{ $r->session->title }}</div></td>
                        <td class="a-td">{{ \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($r->name)) }}{{ $r->role === 'pemateri' ? ' ⭐' : '' }}<div class="font-display text-xs text-mist">{{ $r->nrp }}</div></td>
                        <td class="a-td text-fog">{{ $r->department ?: '–' }}<div class="text-xs {{ $r->geo_status === 'unverified' ? 'text-hivis' : 'text-mist' }}">{{ $r->geo_status === 'none' ? '' : $r->geoLabel() }}</div></td>
                        <td class="a-td text-fog">{{ $r->sleepLabel() ?? '–' }}</td>
                        <td class="a-td">@if ($f)<span class="chip {{ ['neon' => 'bg-neon/15 text-neon', 'hivis' => 'bg-hivis/15 text-hivis', 'alarm' => 'bg-alarm/15 text-red-300'][$f['tone']] }}">{{ $f['label'] }}</span>@else<span class="text-mist">–</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="a-td py-8 text-center text-mist">Tidak ada kehadiran yang cocok dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div class="mt-4">{{ $records->links('admin.pagination') }}</div>
</x-layouts.admin>
