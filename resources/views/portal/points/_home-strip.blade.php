{{-- Strip Poin HEBAT di beranda --}}
@php $lv = $points['level']; @endphp
<a href="{{ route('points.index') }}" class="group relative mx-4 mt-4 flex items-center gap-3 overflow-hidden rounded-[22px] border border-hivis/25 bg-[radial-gradient(90%_140%_at_0%_0%,rgba(255,194,61,.16),transparent_60%)] bg-bench p-3.5 pr-4 transition hover:border-hivis/50 lg:mx-0 lg:mt-6 lg:p-4 lg:pr-5">
    <x-contour class="pointer-events-none absolute -top-10 -right-16 h-40 w-72 opacity-25" />
    <x-coin :size="46" class="relative drop-shadow-[0_6px_14px_rgba(255,194,61,.45)] transition group-hover:rotate-12" />
    <span class="relative min-w-0 grow leading-tight">
        <span class="flex items-center gap-2 text-xs text-fog">Poin HEBAT
            <span class="rounded-full px-2 py-px text-[10.5px] font-bold" style="background: color-mix(in oklab, {{ $lv['current']['color'] }} 18%, transparent); color: {{ $lv['current']['color'] }}">{{ $lv['current']['name'] }}</span>
        </span>
        <span class="mt-0.5 flex items-baseline gap-2">
            <span class="font-display text-[1.6rem] leading-none font-bold text-[#ffd76a]">{{ number_format($points['balance'], 0, ',', '.') }}</span>
            @if ($points['monthIn']) <span class="text-xs font-semibold text-neon">+{{ number_format($points['monthIn'], 0, ',', '.') }} bulan ini</span> @endif
        </span>
    </span>
    @if ($points['coupons'])
        <span class="relative hidden items-center gap-1.5 rounded-full bg-hivis/15 px-3 py-1 text-xs font-semibold text-hivis sm:inline-flex"><i class="ri-coupon-3-line"></i>{{ $points['coupons'] }} kupon siap diambil</span>
    @endif
    <span class="relative inline-flex items-center gap-1.5 rounded-xl bg-hivis px-3.5 py-2 text-sm font-semibold text-[#1a1203]"><i class="ri-gift-2-line"></i>Tukar</span>
</a>
