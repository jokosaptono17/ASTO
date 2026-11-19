<x-layouts.admin title="Area GPS">
    <x-slot:actions>
        <a href="{{ route('admin.attendance.locations.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Area baru</a>
    </x-slot:actions>
    @include('admin.attendance._tabs')

    <div class="mb-5 grid max-w-6xl gap-4 lg:grid-cols-[1fr_340px]">
        <div class="a-card flex items-start gap-4 p-5">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-neon/10 text-neon"><i class="ri-walk-line text-2xl"></i></span>
            <div class="text-sm leading-relaxed text-fog">
                <p class="font-display text-base font-semibold text-ink">Petakan area dengan berjalan di lokasi</p>
                <p class="mt-1">Buka halaman ini dari HP di lokasi, berdiri di tiap sudut area lalu tekan <b class="text-ink">Tandai titik</b> — atau nyalakan <b class="text-ink">Rekam sambil berjalan</b> dan susuri batasnya.
                    Hasilnya lebih akurat daripada menggambar di citra peta yang sering sudah lama.</p>
            </div>
        </div>
        <div class="a-card grid grid-cols-2 divide-x divide-white/[0.06] p-0">
            <div class="p-4"><p class="font-display text-2xl font-bold text-neon">{{ $locations->filter->isMapped()->count() }}/{{ $locations->where('is_active', true)->count() }}</p><p class="text-xs text-mist">area sudah dipetakan</p></div>
            <div class="p-4"><p class="font-display text-2xl font-bold {{ $unverified ? 'text-hivis' : '' }}">{{ $unverified }}</p><p class="text-xs text-mist">absen lokasi belum pasti (30 hari)</p></div>
        </div>
    </div>

    <div class="grid max-w-6xl gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($locations as $loc)
            <a href="{{ route('admin.attendance.locations.edit', $loc) }}" class="a-card group overflow-hidden transition hover:border-neon/30 {{ $loc->is_active ? '' : 'opacity-50' }}">
                <div class="relative grid aspect-[16/9] place-items-center overflow-hidden bg-pit">
                    <x-contour class="absolute inset-0 h-full w-full opacity-25" />
                    @if ($path = $loc->svgPath())
                        <svg viewBox="0 0 100 100" class="relative h-[82%] w-[82%] drop-shadow-[0_0_12px_rgba(61,255,142,.35)]">
                            <path d="{{ $path }}" fill="rgb(61 255 142 / .16)" stroke="#3dff8e" stroke-width="1.6" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                        </svg>
                    @else
                        <div class="relative text-center">
                            <i class="ri-map-pin-add-line text-4xl text-hivis"></i>
                            <p class="mt-1 text-sm font-semibold text-hivis">Belum dipetakan</p>
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-3 p-4">
                    <div class="min-w-0 grow">
                        <p class="font-display text-lg font-semibold">{{ $loc->name }}</p>
                        <p class="text-xs text-mist">
                            @if ($loc->isMapped())
                                {{ count($loc->polygon) }} titik · {{ $loc->areaLabel() }} · toleransi {{ $loc->buffer_m }} m
                            @else
                                Ketuk untuk memetakan di lokasi
                            @endif
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-display text-lg font-bold">{{ $usage[$loc->id] ?? 0 }}</p>
                        <p class="text-[11px] text-mist">absen/30 hr</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</x-layouts.admin>
