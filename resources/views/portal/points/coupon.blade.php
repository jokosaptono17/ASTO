<x-layouts.portal title="Kupon {{ $redemption->code }}">
    @php $r = $redemption->reward; $st = \App\Models\Redemption::STATUSES[$redemption->status]; $pending = $redemption->status === 'pending'; @endphp
    @include('portal._topbar', ['heading' => 'Kupon hadiah', 'sub' => $pending ? 'Tunjukkan kupon ini ke tim admin saat mengambil hadiah' : $st['label'], 'back' => route('points.index')])

    <div class="px-4 lg:mx-auto lg:max-w-md lg:px-0">
        @if ($fresh)
            <div class="pop mb-4 flex items-center gap-3 rounded-2xl border border-neon/40 bg-neon/10 p-4">
                <span class="grid h-11 w-11 place-items-center rounded-full bg-neon text-[#04170d]"><i class="ri-check-line text-2xl"></i></span>
                <div class="leading-tight"><p class="font-semibold">Berhasil ditukar!</p><p class="text-sm text-fog">{{ number_format($redemption->cost, 0, ',', '.') }} poin dipotong dari saldo Anda.</p></div>
            </div>
            <div aria-hidden="true" class="confetti pointer-events-none fixed inset-0 z-50 overflow-hidden">
                @for ($i = 0; $i < 28; $i++)
                    <span style="left: {{ ($i * 37) % 100 }}%; --d: {{ 0.6 + ($i % 7) * 0.18 }}s; --x: {{ (($i % 5) - 2) * 30 }}px; background: {{ ['#3dff8e', '#ffc23d', '#ffffff', '#ff7ad9', '#5ab8ff'][$i % 5] }}"></span>
                @endfor
            </div>
        @endif

        {{-- Tiket --}}
        <article class="relative overflow-hidden rounded-[28px] border {{ $pending ? 'border-hivis/40' : 'border-white/10' }} bg-bench shadow-[0_30px_60px_-30px_rgba(255,194,61,.45)]">
            <div class="flex items-center gap-4 p-5">
                <span class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-ridge">
                    @if ($r->imageUrl()) <img src="{{ $r->imageUrl() }}" alt="" class="h-full w-full object-cover">
                    @else <span class="grid h-full place-items-center text-3xl text-hivis/70"><i class="{{ $r->categoryMeta()['icon'] }}"></i></span> @endif
                </span>
                <div class="min-w-0 leading-tight">
                    <p class="text-xs text-mist">{{ $r->categoryMeta()['label'] }}</p>
                    <p class="font-display text-xl font-bold">{{ $r->name }}</p>
                    <p class="mt-1 flex items-center gap-1 text-sm text-hivis"><x-coin :size="15" />{{ number_format($redemption->cost, 0, ',', '.') }} poin</p>
                </div>
            </div>

            {{-- Sobekan --}}
            <div class="relative h-6">
                <span class="absolute top-1/2 -left-3 h-6 w-6 -translate-y-1/2 rounded-full bg-pit"></span>
                <span class="absolute top-1/2 -right-3 h-6 w-6 -translate-y-1/2 rounded-full bg-pit"></span>
                <span class="absolute inset-x-5 top-1/2 border-t-2 border-dashed border-white/10"></span>
            </div>

            <div class="flex flex-col items-center px-5 pt-2 pb-6 text-center">
                <div class="relative">
                    <div class="rounded-2xl bg-white p-3 {{ $pending ? '' : 'opacity-25 grayscale' }}" x-data="qrImage(@js(route('admin.points.redemptions.show', $redemption)), 520)">
                        <canvas x-ref="canvas" class="block !h-52 !w-52"></canvas>
                    </div>
                    @unless ($pending)
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 -rotate-[8deg] rounded-xl border-2 bg-bench/85 px-4 py-1.5 font-display text-xl font-bold whitespace-nowrap backdrop-blur {{ $redemption->status === 'fulfilled' ? 'border-neon text-neon' : 'border-alarm text-alarm' }}">{{ $st['label'] }}</span>
                    @endunless
                </div>
                <p class="mt-4 text-xs text-mist">Kode kupon</p>
                <p class="font-display text-3xl font-bold tracking-[.25em] {{ $pending ? 'text-hivis' : 'text-mist line-through' }}">{{ $redemption->code }}</p>
                <span class="mt-3 {{ $st['chip'] }}"><i class="{{ $st['icon'] }}"></i> {{ $st['label'] }}</span>
            </div>

            <dl class="grid grid-cols-2 gap-3 border-t border-white/[0.06] bg-white/[0.02] p-5 text-sm">
                <div><dt class="text-xs text-mist">Ditukar</dt><dd>{{ $redemption->created_at->locale('id')->translatedFormat('j M Y · H:i') }}</dd></div>
                <div><dt class="text-xs text-mist">Atas nama</dt><dd class="truncate">{{ $redemption->user->displayName() }}</dd></div>
                @if ($redemption->fulfilled_at)
                    <div class="col-span-2"><dt class="text-xs text-mist">Diambil</dt><dd>{{ $redemption->fulfilled_at->locale('id')->translatedFormat('j M Y · H:i') }} · diserahkan {{ $redemption->handler?->displayName() }}</dd></div>
                @endif
                @if ($redemption->cancelled_at)
                    <div class="col-span-2"><dt class="text-xs text-mist">Dibatalkan</dt><dd>{{ $redemption->cancel_reason }} · poin sudah dikembalikan</dd></div>
                @endif
                @if ($pending && $r->pickup_note)
                    <div class="col-span-2"><dt class="text-xs text-mist">Cara mengambil</dt><dd>{{ $r->pickup_note }}</dd></div>
                @endif
            </dl>
        </article>

        <div class="mt-5 grid grid-cols-2 gap-2">
            <a href="{{ route('points.index') }}" class="btn-ghost"><i class="ri-wallet-3-line"></i> Dompet poin</a>
            <a href="{{ route('points.rewards') }}" class="btn-ghost"><i class="ri-gift-2-line"></i> Katalog</a>
        </div>
    </div>
</x-layouts.portal>
