<nav class="mb-5 flex flex-wrap gap-2 lg:hidden" aria-label="Menu poin">
    @foreach ([['admin.points.index', 'Ringkasan', 'ri-coin-line'], ['admin.points.grants.index', 'Pemberian', 'ri-trophy-line'], ['admin.points.redemptions.index', 'Kupon', 'ri-coupon-3-line'], ['admin.points.rewards.index', 'Hadiah', 'ri-gift-2-line'], ['admin.points.ledger', 'Riwayat', 'ri-file-list-3-line'], ['admin.points.rules', 'Aturan', 'ri-settings-3-line']] as [$route, $label, $icon])
        <a href="{{ route($route) }}" class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-2 text-sm font-semibold {{ request()->routeIs($route, $route.'.*', str_replace('.index', '', $route).'.*') ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog' }}"><i class="{{ $icon }}"></i>{{ $label }}</a>
    @endforeach
</nav>
