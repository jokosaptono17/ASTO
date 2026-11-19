<x-layouts.admin title="Jadwal rutin">
    @include('admin.attendance._tabs')

    <p class="mb-5 max-w-3xl text-sm leading-relaxed text-fog">Absen untuk kegiatan rutin dibuka <b class="text-ink">otomatis</b> sesuai jadwal — GL tidak perlu membuat sesi setiap hari.
        Karyawan cukup scan QR absensi, AstoOne mengecek posisinya, lalu menampilkan kegiatan yang sedang dibuka.</p>

    <div class="grid max-w-6xl gap-5 lg:grid-cols-[1fr_380px]">
        <div class="space-y-3" x-data="{ edit: null }">
            @forelse ($schedules as $sc)
                @php
                    $today = $sc->sessions()->whereDate('date', today())->withCount('records')->first();
                    $days = array_map('intval', $sc->days ?? []);
                @endphp
                <article class="a-card overflow-hidden {{ $sc->is_active ? '' : 'opacity-55' }}" style="box-shadow: inset 3px 0 0 {{ $sc->type->color }}">
                    <div x-show="edit !== {{ $sc->id }}" class="flex flex-wrap items-center gap-4 p-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" style="background: color-mix(in oklab, {{ $sc->type->color }} 16%, transparent); color: {{ $sc->type->color }}"><i class="{{ $sc->type->icon }} text-2xl"></i></span>
                        <div class="min-w-0 grow">
                            <p class="font-semibold">{{ $sc->type->name }} <span class="text-fog">{{ $sc->title }}</span> @unless ($sc->is_active) <span class="chip-berakhir ml-1">Nonaktif</span> @endunless</p>
                            <div class="mt-1.5 flex gap-1">
                                @foreach (\App\Models\AttendanceSchedule::DAYS as $d => $label)
                                    <span class="grid h-6 w-8 place-items-center rounded-md text-[11px] font-semibold {{ in_array($d, $days, true) ? 'bg-neon/15 text-neon' : 'bg-white/[0.04] text-mist/60' }}">{{ $label }}</span>
                                @endforeach
                            </div>
                            <p class="mt-1.5 text-xs text-mist">
                                <i class="ri-map-pin-2-line"></i> {{ $sc->location_ids ? $areas->whereIn('id', $sc->location_ids)->pluck('name')->join(', ') : 'Semua area' }}
                                · {{ $sc->late_after ? 'terlambat setelah '.$sc->late_after.' mnt' : 'tanpa hitung terlambat' }}
                                @if ($sc->target_departments) · {{ count($sc->target_departments) }} seksi @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-display text-2xl leading-none font-bold">{{ $sc->window() }}</p>
                            @if ($today)
                                <a href="{{ route('admin.attendance.show', $today) }}" class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold {{ $today->isOpen() ? 'text-neon' : 'text-mist' }}">
                                    @if ($today->isOpen()) <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-neon"></span> @endif
                                    Hari ini: {{ $today->statusLabel() }} · {{ $today->records_count }} hadir <i class="ri-arrow-right-s-line"></i>
                                </a>
                            @elseif ($sc->is_active && $sc->runsOn(today()))
                                <p class="mt-1.5 text-xs text-mist">Hari ini sudah lewat</p>
                            @else
                                <p class="mt-1.5 text-xs text-mist">Tidak ada hari ini</p>
                            @endif
                        </div>
                        <button type="button" @click="edit = {{ $sc->id }}" class="icon-btn" aria-label="Ubah jadwal"><i class="ri-pencil-line text-lg"></i></button>
                    </div>
                    <div x-show="edit === {{ $sc->id }}" x-cloak class="p-4">
                        @component('admin.attendance._schedule-form', ['schedule' => $sc, 'types' => $types, 'areas' => $areas, 'departments' => $departments])
                            <button type="button" @click="edit = null" class="btn-ghost !min-h-10 text-sm">Batal</button>
                            <button type="submit" form="del-sc-{{ $sc->id }}" class="btn-ghost !min-h-10 text-sm text-red-300"><i class="ri-delete-bin-line"></i> Hapus</button>
                        @endcomponent
                        <form id="del-sc-{{ $sc->id }}" method="POST" action="{{ route('admin.attendance.schedules.destroy', $sc) }}" onsubmit="return confirm('Hapus jadwal ini? Riwayat absensinya tetap tersimpan.')">@csrf @method('DELETE')</form>
                    </div>
                </article>
            @empty
                <div class="a-card px-6 py-12 text-center text-sm text-mist">Belum ada jadwal rutin.</div>
            @endforelse
        </div>

        <aside class="lg:sticky lg:top-8 lg:self-start">
            <section class="a-card p-5">
                <h2 class="mb-4 font-display text-lg font-semibold">Tambah jadwal</h2>
                @include('admin.attendance._schedule-form', ['schedule' => new \App\Models\AttendanceSchedule(['open_time' => '06:00', 'close_time' => '07:00', 'is_active' => true]), 'slot' => ''])
            </section>
        </aside>
    </div>
</x-layouts.admin>
