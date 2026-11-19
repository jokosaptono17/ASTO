<nav class="mb-5 flex flex-wrap gap-2" aria-label="Menu absensi">
    @foreach ([['admin.attendance.index', 'Sesi', 'ri-calendar-event-line', 'host-attendance'], ['admin.attendance.recap', 'Rekap', 'ri-bar-chart-2-line', 'host-attendance'], ['admin.attendance.schedules', 'Jadwal rutin', 'ri-repeat-line', 'manage-content'], ['admin.attendance.locations', 'Area GPS', 'ri-map-pin-range-line', 'manage-content'], ['admin.attendance.types', 'Jenis kegiatan', 'ri-price-tag-3-line', 'admin']] as [$route, $label, $icon, $ability])
        @can($ability)
            <a href="{{ route($route) }}" class="inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold {{ request()->routeIs($route, $route.'.*') ? 'bg-neon text-[#04170d]' : 'bg-white/[0.05] text-fog hover:bg-white/10' }}">
                <i class="{{ $icon }}"></i>{{ $label }}
            </a>
        @endcan
    @endforeach
</nav>
