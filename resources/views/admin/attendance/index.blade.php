<x-layouts.admin title="Absensi">
    <x-slot:actions>
        <a href="{{ route('admin.attendance.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Sesi baru</a>
    </x-slot:actions>
    @include('admin.attendance._tabs')

    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([['Sesi hari ini', $today['sessions'], 'ri-calendar-event-line', 'text-neon'], ['Hadir hari ini', $today['present'], 'ri-user-follow-line', 'text-neon'], ['Terlambat', $today['late'], 'ri-time-line', 'text-hivis'], ['Fatigue risk', $today['risk'], 'ri-alarm-warning-line', 'text-alarm']] as [$label, $value, $icon, $tone])
            <div class="a-card flex items-center gap-3 p-4">
                <i class="{{ $icon }} text-2xl {{ $tone }}"></i>
                <div><p class="font-display text-2xl leading-none font-bold">{{ $value }}</p><p class="mt-1 text-xs text-mist">{{ $label }}</p></div>
            </div>
        @endforeach
    </div>

    @if ($live->isNotEmpty())
        <section class="mt-5">
            <h2 class="mb-2 flex items-center gap-2 font-display text-lg font-semibold"><span class="h-2 w-2 animate-pulse rounded-full bg-neon shadow-[0_0_10px_var(--color-neon)]"></span> Sedang berlangsung</h2>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($live as $s)
                    <div class="a-card overflow-hidden" style="box-shadow: inset 3px 0 0 {{ $s->type->color }}">
                        <div class="flex items-start gap-3 p-4">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" style="background: color-mix(in oklab, {{ $s->type->color }} 16%, transparent); color: {{ $s->type->color }}"><i class="{{ $s->type->icon }} text-2xl"></i></span>
                            <div class="min-w-0 grow">
                                <p class="truncate font-semibold">{{ $s->type->name }}{{ $s->title ? ' — '.$s->title : '' }}</p>
                                <p class="text-xs text-mist">{{ $s->starts_at->format('H:i') }} · {{ $s->location ?: '–' }} · tutup {{ $s->closes_at->format('H:i') }}</p>
                            </div>
                            <p class="text-right"><span class="block font-display text-2xl leading-none font-bold text-neon">{{ $s->records_count }}</span><span class="text-[11px] text-mist">hadir</span></p>
                        </div>
                        <div class="grid grid-cols-2 border-t border-white/[0.06]">
                            <a href="{{ route('admin.attendance.show', $s) }}" class="flex items-center justify-center gap-2 py-2.5 text-sm font-semibold hover:bg-white/[0.04]"><i class="ri-list-check-3"></i> Daftar hadir</a>
                            <a href="{{ route('admin.attendance.screen', $s) }}" target="_blank" class="flex items-center justify-center gap-2 border-l border-white/[0.06] py-2.5 text-sm font-semibold text-neon hover:bg-white/[0.04]"><i class="ri-tv-2-line"></i> Layar QR</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($upcoming->isNotEmpty())
        <section class="a-card mt-5 overflow-hidden">
            <h2 class="border-b border-white/[0.06] px-4 py-3 font-display font-semibold">Terjadwal</h2>
            @foreach ($upcoming as $s) @include('admin.attendance._session-row') @endforeach
        </section>
    @endif

    <section class="a-card mt-5 overflow-hidden">
        <h2 class="border-b border-white/[0.06] px-4 py-3 font-display font-semibold">Riwayat sesi</h2>
        @forelse ($recent as $s)
            @include('admin.attendance._session-row')
        @empty
            <div class="flex flex-col items-center px-6 py-12 text-center">
                <x-mentari :size="100" />
                <p class="mt-3 font-semibold">Belum ada sesi absensi</p>
                <p class="mt-1 max-w-md text-sm text-mist">Buat sesi P5M atau safety talk, tampilkan QR di TV/HP, dan karyawan cukup scan untuk absen — identitasnya otomatis dari akun.</p>
                <a href="{{ route('admin.attendance.create') }}" class="btn-neon mt-5 text-sm"><i class="ri-add-line text-lg"></i> Buat sesi pertama</a>
            </div>
        @endforelse
    </section>
    <div class="mt-4">{{ $recent->links('admin.pagination') }}</div>
</x-layouts.admin>
