<x-layouts.portal title="Poin HEBAT" :mentari-tips="['Poin didapat dari hadir P5M & safety talk.', 'Tukar poin dengan hadiah di katalog.']">
    @include('portal._topbar', ['heading' => 'Poin HEBAT', 'sub' => 'Kumpulkan poin dari kegiatan, tukar dengan hadiah', 'back' => route('home')])

    <div class="space-y-6 px-4 lg:grid lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start lg:gap-8 lg:space-y-0 lg:px-0">
        <div class="min-w-0 space-y-6">
            @include('portal.points._wallet')

            {{-- Kupon aktif --}}
            @if ($coupons->isNotEmpty())
                <section>
                    <h2 class="section-title">Kupon siap diambil</h2>
                    <div class="mt-3 space-y-2.5">
                        @foreach ($coupons as $c)
                            <a href="{{ route('points.coupon', $c) }}" class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border border-hivis/30 bg-hivis/[0.06] p-3 pr-4 transition hover:border-hivis/60">
                                <span class="absolute top-1/2 -left-2.5 h-5 w-5 -translate-y-1/2 rounded-full bg-pit"></span>
                                <span class="ml-2 grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-hivis/15 text-hivis"><i class="ri-qr-code-line text-2xl"></i></span>
                                <span class="min-w-0 grow leading-tight">
                                    <span class="block truncate font-semibold">{{ $c->reward->name }}</span>
                                    <span class="font-display text-sm tracking-widest text-hivis">{{ $c->code }}</span>
                                </span>
                                <span class="text-xs text-mist">Tunjukkan ke admin <i class="ri-arrow-right-s-line text-lg align-middle"></i></span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Riwayat --}}
            <section>
                <div class="flex items-center justify-between gap-2">
                    <h2 class="section-title">Riwayat poin</h2>
                    <div class="flex gap-1 rounded-full bg-white/[0.05] p-1 text-xs font-semibold">
                        @foreach (['semua' => 'Semua', 'masuk' => 'Masuk', 'keluar' => 'Keluar'] as $k => $l)
                            <a href="{{ route('points.index', $k === 'semua' ? [] : ['riwayat' => $k]) }}#riwayat" class="rounded-full px-3 py-1.5 transition {{ $filter === $k ? 'bg-neon text-[#04170d]' : 'text-fog hover:text-ink' }}">{{ $l }}</a>
                        @endforeach
                    </div>
                </div>
                <div id="riwayat" class="mt-3 overflow-hidden rounded-2xl border border-white/[0.07] bg-bench">
                    @forelse ($transactions->groupBy(fn ($t) => $t->created_at->toDateString()) as $date => $items)
                        <p class="border-b border-white/[0.05] bg-white/[0.02] px-4 py-2 text-xs font-semibold text-mist">{{ $date === today()->toDateString() ? 'Hari ini, '.\Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('j F') : ($date === today()->subDay()->toDateString() ? 'Kemarin' : \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('l, j F Y')) }}</p>
                        @foreach ($items as $t)
                            <div class="flex items-center gap-3 border-b border-white/[0.04] px-4 py-3 last:border-0">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $t->amount > 0 ? 'bg-neon/10 text-neon' : 'bg-white/[0.06] text-fog' }}"><i class="{{ $t->icon() }} text-lg"></i></span>
                                <span class="min-w-0 grow leading-tight">
                                    <span class="block truncate text-[14.5px] font-medium">{{ $t->description }}</span>
                                    <span class="text-xs text-mist">{{ \App\Models\PointTransaction::KINDS[$t->kind]['label'] }} · {{ $t->created_at->format('H:i') }}</span>
                                </span>
                                <span class="text-right leading-tight">
                                    <span class="block font-display text-[17px] font-bold {{ $t->amount > 0 ? 'text-neon' : 'text-fog' }}">{{ $t->amount > 0 ? '+' : '−' }}{{ number_format(abs($t->amount), 0, ',', '.') }}</span>
                                    <span class="font-display text-[11px] text-mist">saldo {{ number_format($t->balance_after, 0, ',', '.') }}</span>
                                </span>
                            </div>
                        @endforeach
                    @empty
                        <div class="flex flex-col items-center px-6 py-10 text-center">
                            <x-coin :size="56" class="opacity-60" />
                            <p class="mt-3 font-semibold">Belum ada poin</p>
                            <p class="mt-1 max-w-[30ch] text-sm text-mist">Hadiri P5M atau safety talk berikutnya untuk mendapat poin pertama Anda.</p>
                            <a href="{{ route('attendance.scan') }}" class="btn-neon mt-4 !min-h-10 text-sm"><i class="ri-qr-scan-2-line"></i> Absen sekarang</a>
                        </div>
                    @endforelse
                </div>
                <div class="mt-3">{{ $transactions->fragment('riwayat')->links('admin.pagination') }}</div>
            </section>
        </div>

        <aside class="space-y-6 lg:sticky lg:top-24">
            {{-- Hadiah pilihan --}}
            @if ($picks->isNotEmpty())
                <section>
                    <div class="flex items-end justify-between"><h2 class="section-title">Hadiah untuk Anda</h2><a href="{{ route('points.rewards') }}" class="section-link">Katalog <i class="ri-arrow-right-s-line"></i></a></div>
                    <div class="scroller mt-3 lg:grid lg:grid-cols-2 lg:gap-3 lg:overflow-visible lg:px-0">
                        @foreach ($picks as $r)
                            @php $short = max(0, $r->cost - $user->points_balance); @endphp
                            <a href="{{ route('points.rewards') }}#hadiah-{{ $r->id }}" class="w-[42%] min-w-[150px] shrink-0 overflow-hidden rounded-2xl border border-white/[0.07] bg-bench lg:w-auto lg:min-w-0">
                                <span class="relative block aspect-square bg-ridge">
                                    @if ($r->imageUrl()) <img src="{{ $r->imageUrl() }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                    @else <span class="grid h-full place-items-center text-4xl text-hivis/70"><i class="{{ $r->categoryMeta()['icon'] }}"></i></span> @endif
                                </span>
                                <span class="block p-2.5">
                                    <span class="block truncate text-sm font-semibold">{{ $r->name }}</span>
                                    <span class="mt-0.5 flex items-center gap-1 font-display text-sm font-bold text-hivis"><x-coin :size="14" />{{ number_format($r->cost, 0, ',', '.') }}</span>
                                    <span class="mt-0.5 block text-[11px] {{ $short ? 'text-mist' : 'text-neon' }}">{{ $short ? 'Kurang '.number_format($short, 0, ',', '.') : 'Bisa ditukar' }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Cara dapat poin --}}
            <section id="cara-dapat" class="scroll-mt-24 rounded-2xl border border-white/[0.07] bg-bench p-4">
                <h2 class="font-display text-lg font-semibold">Cara dapat poin</h2>
                <ul class="mt-3 space-y-2.5">
                    @foreach ($rules as $rule)
                        <li class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-neon/10 text-neon"><i class="{{ ['attend' => 'ri-team-line', 'speaker' => 'ri-mic-line', 'streak7' => 'ri-fire-line'][$rule->key] ?? 'ri-flashlight-line' }}"></i></span>
                            <span class="min-w-0 grow leading-tight"><span class="block text-sm font-medium">{{ $rule->label }}</span>@if ($rule->hint)<span class="text-xs text-mist">{{ $rule->hint }}</span>@endif</span>
                            <span class="font-display font-bold text-hivis">+{{ $rule->points }}</span>
                        </li>
                    @endforeach
                    <li class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-hivis/10 text-hivis"><i class="ri-trophy-line"></i></span>
                        <span class="min-w-0 grow leading-tight"><span class="block text-sm font-medium">Reward event</span><span class="text-xs text-mist">Juara lomba, improvement, temuan hazard, dll. dari admin</span></span>
                        <span class="font-display font-bold text-hivis">Bonus</span>
                    </li>
                </ul>
                <p class="mt-3 rounded-xl bg-white/[0.03] px-3 py-2 text-xs leading-relaxed text-mist"><i class="ri-information-line"></i> Poin tidak bisa dibeli, dicairkan, atau ditransfer. Isi jam tidur dengan jujur — tidak memengaruhi poin.</p>
            </section>

            {{-- Peringkat --}}
            <section class="rounded-2xl border border-white/[0.07] bg-bench p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold">Peringkat bulan ini</h2>
                    <span class="text-xs text-mist">{{ now()->locale('id')->translatedFormat('F Y') }}</span>
                </div>
                <ol class="mt-3 space-y-1.5">
                    @forelse ($leaders as $i => $row)
                        @php $me = $row['user']->id === $user->id; @endphp
                        <li class="flex items-center gap-3 rounded-xl px-2 py-1.5 {{ $me ? 'bg-neon/10 ring-1 ring-neon/30' : '' }}">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full font-display text-sm font-bold {{ [0 => 'bg-[#ffd76a] text-[#5a3600]', 1 => 'bg-[#d8e2dc] text-[#24302a]', 2 => 'bg-[#e0a46b] text-[#3d220a]'][$i] ?? 'bg-white/[0.06] text-fog' }}">{{ $i + 1 }}</span>
                            <span class="min-w-0 grow leading-tight"><span class="block truncate text-sm font-medium">{{ $row['user']->displayName() }}{{ $me ? ' (Anda)' : '' }}</span><span class="block truncate text-[11px] text-mist">{{ $row['user']->department }}</span></span>
                            <span class="flex items-center gap-1 font-display text-sm font-bold text-hivis"><x-coin :size="14" />{{ number_format($row['total'], 0, ',', '.') }}</span>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-mist">Belum ada poin bulan ini. Jadilah yang pertama!</li>
                    @endforelse
                </ol>
                @if ($myRank && $myRank > $leaders->count())
                    <p class="mt-2 border-t border-white/[0.06] pt-2 text-center text-sm text-fog">Posisi Anda: <b class="text-neon">#{{ $myRank }}</b></p>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.portal>
