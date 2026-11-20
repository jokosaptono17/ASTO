{{-- Kartu dompet Poin HEBAT (halaman poin) --}}
@php $lv = $level; @endphp
<section class="relative overflow-hidden rounded-[28px] border border-hivis/25 bg-[radial-gradient(120%_120%_at_100%_0%,rgba(255,194,61,.20),transparent_55%),linear-gradient(160deg,#12291f,#06120d)] p-5 shadow-[0_30px_70px_-35px_rgba(255,194,61,.45)] lg:p-7"
         x-data="{ shown: 0, target: {{ (int) $user->points_balance }}, init() { const t0 = performance.now(), d = 900; const step = (t) => { const k = Math.min(1, (t - t0) / d); this.shown = Math.round(this.target * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); }; requestAnimationFrame(step); } }">
    <x-contour class="pointer-events-none absolute -top-16 -right-24 h-72 w-[30rem] opacity-30" />
    <div aria-hidden="true" class="absolute -top-8 -right-10 opacity-[.07]"><x-coin :size="200" /></div>
    <div class="relative">
        <div class="flex items-center gap-2 text-sm text-fog">
            <x-coin :size="22" /> Saldo Poin HEBAT
            <span class="ml-auto inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold" style="background: color-mix(in oklab, {{ $lv['current']['color'] }} 18%, transparent); color: {{ $lv['current']['color'] }}">
                <i class="ri-vip-crown-2-fill"></i>{{ $lv['current']['name'] }}
            </span>
        </div>
        <p class="mt-2 flex items-baseline gap-2">
            <span class="font-display text-[3.2rem] leading-none font-bold tracking-tight text-[#ffd76a] tabular-nums lg:text-[4rem]" x-text="shown.toLocaleString('id-ID')">{{ number_format($user->points_balance, 0, ',', '.') }}</span>
            <span class="text-lg font-semibold text-hivis/80">poin</span>
        </p>

        <div class="mt-4">
            @if ($lv['next'])
                <div class="flex justify-between text-xs text-fog"><span>Menuju <b style="color: {{ $lv['next']['color'] }}">{{ $lv['next']['name'] }}</b></span><span class="font-display">{{ number_format($lv['earned'], 0, ',', '.') }} / {{ number_format($lv['next']['min'], 0, ',', '.') }}</span></div>
            @else
                <div class="text-xs text-fog">Level tertinggi tercapai · {{ number_format($lv['earned'], 0, ',', '.') }} poin seumur hidup</div>
            @endif
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-black/40">
                <div class="h-full rounded-full bg-gradient-to-r from-hivis to-[#ffe08a] shadow-[0_0_12px_rgba(255,194,61,.6)]" style="width: {{ max(3, $lv['progress']) }}%"></div>
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-3 divide-x divide-white/10 rounded-2xl bg-black/25 py-3 text-center backdrop-blur-sm">
            <div><dt class="text-[11px] text-mist">Bulan ini</dt><dd class="mt-0.5 font-display text-lg font-bold text-neon">+{{ number_format($stats['monthIn'], 0, ',', '.') }}</dd></div>
            <div><dt class="text-[11px] text-mist">Kupon aktif</dt><dd class="mt-0.5 font-display text-lg font-bold">{{ $coupons->count() }}</dd></div>
            <div><dt class="text-[11px] text-mist">Peringkat</dt><dd class="mt-0.5 font-display text-lg font-bold">{{ $myRank ? '#'.$myRank : '–' }}</dd></div>
        </dl>

        <div class="mt-4 grid grid-cols-2 gap-2">
            <a href="{{ route('points.rewards') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-hivis font-semibold text-[#1a1203] shadow-[0_10px_24px_-10px_rgba(255,194,61,.8)] transition hover:brightness-105"><i class="ri-gift-2-line text-xl"></i> Tukar hadiah</a>
            <a href="#cara-dapat" class="btn-ghost !min-h-12 text-[15px] whitespace-nowrap"><i class="ri-lightbulb-flash-line text-xl text-hivis"></i> Cara dapat poin</a>
        </div>
    </div>
</section>
