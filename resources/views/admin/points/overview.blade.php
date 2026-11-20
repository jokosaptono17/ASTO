<x-layouts.admin title="Poin HEBAT" subtitle="Poin reward tertutup — tidak bisa dibeli, dicairkan, atau ditransfer">
    <x-slot:actions>
        <a href="{{ route('admin.points.redemptions.index') }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-qr-scan-2-line text-lg"></i> Scan kupon</a>
        <a href="{{ route('admin.points.grants.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-trophy-line text-lg"></i> Beri poin event</a>
    </x-slot:actions>
    @include('admin.points._tabs')
    @php
        $fmt = fn ($n) => number_format($n, 0, ',', '.');
        $max = max(1, $series->max('given'), $series->max('spent'));
    @endphp

    {{-- Peringatan tindakan --}}
    @if ($stats['pendingGrants'] || $stats['pendingCoupons'] || $stats['lowStock'])
        <div class="mb-5 grid gap-3 md:grid-cols-3">
            @if ($stats['pendingGrants'])
                <a href="{{ route('admin.points.grants.index', ['status' => 'pending']) }}" class="flex items-center gap-3 rounded-2xl border border-hivis/40 bg-hivis/[0.08] p-4 transition hover:border-hivis"><i class="ri-shield-check-line text-2xl text-hivis"></i><span class="grow"><b>{{ $stats['pendingGrants'] }}</b> pemberian poin menunggu persetujuan</span><i class="ri-arrow-right-s-line text-xl"></i></a>
            @endif
            @if ($stats['pendingCoupons'])
                <a href="{{ route('admin.points.redemptions.index') }}" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-bench p-4 transition hover:border-neon/40"><i class="ri-coupon-3-line text-2xl text-neon"></i><span class="grow"><b>{{ $stats['pendingCoupons'] }}</b> kupon belum diambil</span><i class="ri-arrow-right-s-line text-xl"></i></a>
            @endif
            @if ($stats['lowStock'])
                <a href="{{ route('admin.points.rewards.index') }}" class="flex items-center gap-3 rounded-2xl border border-alarm/40 bg-alarm/[0.08] p-4 transition hover:border-alarm"><i class="ri-archive-line text-2xl text-alarm"></i><span class="grow"><b>{{ $stats['lowStock'] }}</b> hadiah stoknya hampir habis</span><i class="ri-arrow-right-s-line text-xl"></i></a>
            @endif
        </div>
    @endif

    {{-- Angka utama --}}
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="relative overflow-hidden rounded-2xl border border-hivis/30 bg-[radial-gradient(120%_120%_at_100%_0%,rgba(255,194,61,.16),transparent_60%)] bg-bench p-4">
            <p class="flex items-center gap-2 text-sm text-mist"><x-coin :size="18" /> Poin beredar</p>
            <p class="mt-2 font-display text-3xl font-bold text-[#ffd76a]">{{ $fmt($stats['circulating']) }}</p>
            <p class="mt-1 text-xs text-mist">dipegang {{ $fmt($stats['holders']) }} karyawan</p>
        </div>
        <div class="a-card p-4">
            <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-arrow-down-circle-line text-lg text-neon"></i> Diberikan bulan ini</p>
            <p class="mt-2 font-display text-3xl font-bold">{{ $fmt($stats['givenMonth']) }}</p>
            <p class="mt-1 text-xs text-mist">{{ $fmt($stats['autoMonth']) }} otomatis · {{ $fmt($stats['grantMonth']) }} reward event</p>
        </div>
        <div class="a-card p-4">
            <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-gift-2-line text-lg text-hivis"></i> Ditukar bulan ini</p>
            <p class="mt-2 font-display text-3xl font-bold">{{ $fmt($stats['spentMonth']) }}</p>
            <p class="mt-1 text-xs text-mist">poin menjadi hadiah</p>
        </div>
        <div class="a-card p-4">
            <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-coupon-3-line text-lg text-neon"></i> Kupon belum diambil</p>
            <p class="mt-2 font-display text-3xl font-bold {{ $stats['pendingCoupons'] ? 'text-hivis' : '' }}">{{ $fmt($stats['pendingCoupons']) }}</p>
            <a href="{{ route('admin.points.redemptions.index') }}" class="mt-1 inline-block text-xs text-neon">Buka penyerahan →</a>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        {{-- Grafik 30 hari --}}
        <section class="a-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-lg font-semibold">Arus poin 30 hari</h2>
                <div class="flex gap-4 text-xs text-fog"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-neon"></span>Diberikan</span><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-hivis"></span>Ditukar</span></div>
            </div>
            <div class="mt-5 flex h-48 items-end gap-[3px]">
                @foreach ($series as $d)
                    <div class="group relative flex h-full flex-1 items-end justify-center gap-px" title="{{ $d['date']->locale('id')->translatedFormat('j M') }}: +{{ $d['given'] }} / −{{ $d['spent'] }}">
                        <span class="w-1/2 rounded-t bg-neon/80 transition group-hover:bg-neon" style="height: {{ max(1, $d['given'] / $max * 100) }}%"></span>
                        <span class="w-1/2 rounded-t bg-hivis/80 transition group-hover:bg-hivis" style="height: {{ max(1, $d['spent'] / $max * 100) }}%"></span>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[11px] text-mist"><span>{{ $series->first()['date']->locale('id')->translatedFormat('j M') }}</span><span>{{ $series->get(15)['date']->locale('id')->translatedFormat('j M') }}</span><span>Hari ini</span></div>
        </section>

        {{-- Peringkat --}}
        <section class="a-card p-5">
            <div class="flex items-center justify-between"><h2 class="font-display text-lg font-semibold">Penerima terbanyak</h2><span class="text-xs text-mist">{{ now()->locale('id')->translatedFormat('F') }}</span></div>
            <ol class="mt-3 space-y-1">
                @forelse ($leaders as $i => $row)
                    <li class="flex items-center gap-3 rounded-xl px-1 py-1.5">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full font-display text-sm font-bold {{ [0 => 'bg-[#ffd76a] text-[#5a3600]', 1 => 'bg-[#d8e2dc] text-[#24302a]', 2 => 'bg-[#e0a46b] text-[#3d220a]'][$i] ?? 'bg-white/[0.06] text-fog' }}">{{ $i + 1 }}</span>
                        <span class="min-w-0 grow leading-tight"><span class="block truncate text-sm font-medium">{{ $row['user']->displayName() }}</span><span class="block truncate text-[11px] text-mist">{{ $row['user']->nrp }} · {{ $row['user']->department }}</span></span>
                        <span class="flex items-center gap-1 font-display text-sm font-bold text-hivis"><x-coin :size="14" />{{ $fmt($row['total']) }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-mist">Belum ada poin bulan ini.</li>
                @endforelse
            </ol>
        </section>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        {{-- Persetujuan --}}
        <section class="a-card p-5">
            <div class="flex items-center justify-between"><h2 class="font-display text-lg font-semibold">Menunggu persetujuan</h2><a href="{{ route('admin.points.grants.index') }}" class="section-link">Semua</a></div>
            <ul class="mt-3 divide-y divide-white/[0.05]">
                @forelse ($grants as $g)
                    <li><a href="{{ route('admin.points.grants.show', $g) }}" class="flex items-center gap-3 py-2.5">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-hivis/12 text-hivis"><i class="ri-trophy-line"></i></span>
                        <span class="min-w-0 grow leading-tight"><span class="block truncate text-sm font-medium">{{ $g->title }}</span><span class="text-xs text-mist">oleh {{ $g->creator?->displayName() }} · {{ $g->submitted_at?->locale('id')->diffForHumans() }}</span></span>
                        <span class="font-display font-bold text-hivis">{{ $fmt($g->total_points) }}</span>
                    </a></li>
                @empty
                    <li class="py-6 text-center text-sm text-mist"><i class="ri-checkbox-circle-line text-2xl text-neon"></i><br>Tidak ada yang menunggu.</li>
                @endforelse
            </ul>
        </section>

        {{-- Hadiah populer --}}
        <section class="a-card p-5">
            <div class="flex items-center justify-between"><h2 class="font-display text-lg font-semibold">Hadiah terpopuler</h2><a href="{{ route('admin.points.rewards.index') }}" class="section-link">Katalog</a></div>
            <ul class="mt-3 space-y-2.5">
                @forelse ($popular as $r)
                    <li class="flex items-center gap-3">
                        <span class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-ridge">@if ($r->imageUrl())<img src="{{ $r->imageUrl() }}" alt="" class="h-full w-full object-cover">@else<span class="grid h-full place-items-center text-hivis"><i class="{{ $r->categoryMeta()['icon'] }}"></i></span>@endif</span>
                        <span class="min-w-0 grow leading-tight"><span class="block truncate text-sm font-medium">{{ $r->name }}</span><span class="text-xs text-mist">{{ $fmt($r->cost) }} poin · stok {{ $r->stock ?? '∞' }}</span></span>
                        <span class="font-display text-sm font-bold">{{ $r->taken }}×</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-mist">Katalog masih kosong. <a href="{{ route('admin.points.rewards.create') }}" class="text-neon">Tambah hadiah</a></li>
                @endforelse
            </ul>
        </section>

        {{-- Transaksi terbaru --}}
        <section class="a-card p-5">
            <div class="flex items-center justify-between"><h2 class="font-display text-lg font-semibold">Transaksi terbaru</h2><a href="{{ route('admin.points.ledger') }}" class="section-link">Riwayat</a></div>
            <ul class="mt-3 divide-y divide-white/[0.05]">
                @forelse ($recent as $t)
                    <li class="flex items-center gap-3 py-2">
                        <i class="{{ $t->icon() }} text-lg {{ $t->amount > 0 ? 'text-neon' : 'text-mist' }}"></i>
                        <span class="min-w-0 grow leading-tight"><span class="block truncate text-sm">{{ $t->user?->displayName() }}</span><span class="block truncate text-[11px] text-mist">{{ $t->description }}</span></span>
                        <span class="font-display text-sm font-bold {{ $t->amount > 0 ? 'text-neon' : 'text-fog' }}">{{ $t->amount > 0 ? '+' : '−' }}{{ $fmt(abs($t->amount)) }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-mist">Belum ada transaksi.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.admin>
