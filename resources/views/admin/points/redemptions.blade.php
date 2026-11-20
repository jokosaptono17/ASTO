<x-layouts.admin title="Kupon & penyerahan" subtitle="Scan QR kupon karyawan lalu serahkan hadiahnya">
    @include('admin.points._tabs')

    <div class="grid gap-5 lg:grid-cols-[360px_minmax(0,1fr)]">
        {{-- Scan / ketik kode --}}
        <section class="a-card space-y-4 self-start p-5 lg:sticky lg:top-24" x-data="{ cam: false }">
            <h2 class="font-display text-lg font-semibold">Terima kupon</h2>
            <form method="POST" action="{{ route('admin.points.redemptions.lookup') }}">
                @csrf
                <label for="code" class="label">Kode kupon</label>
                <div class="flex gap-2">
                    <input id="code" name="code" value="{{ old('code') }}" required maxlength="16" autocomplete="off" placeholder="HBXXXXXX" class="field h-12 font-display text-lg tracking-widest uppercase">
                    <button class="btn-neon !min-h-12 shrink-0 !px-4" aria-label="Buka kupon"><i class="ri-arrow-right-line text-xl"></i></button>
                </div>
                @error('code') <p class="error">{{ $message }}</p> @enderror
            </form>
            <button type="button" @click="cam = !cam" class="btn-ghost w-full"><i class="ri-qr-scan-2-line text-lg text-neon"></i> <span x-text="cam ? 'Tutup kamera' : 'Scan QR dengan kamera'"></span></button>
            <template x-if="cam">
                <div x-data="qrScanner(true, '/admin/poin/kupon/', 'Kupon terbaca, membuka…', 'QR ini bukan kupon Poin HEBAT.')" class="relative overflow-hidden rounded-2xl bg-black">
                    <div class="relative aspect-square">
                        <video x-ref="video" playsinline muted class="absolute inset-0 h-full w-full object-cover" x-show="state === 'scanning'"></video>
                        <canvas x-ref="canvas" class="hidden"></canvas>
                        <div x-show="state === 'scanning'" class="pointer-events-none absolute inset-[18%] rounded-2xl border-2 border-hivis/80"></div>
                        <div x-show="state !== 'scanning'" class="absolute inset-0 grid place-items-center p-5 text-center text-sm text-fog"><p x-text="message"></p></div>
                    </div>
                </div>
            </template>
            <p class="text-xs leading-relaxed text-mist"><i class="ri-information-line"></i> Bisa juga langsung scan QR kupon dengan kamera HP Anda — halaman penyerahan akan terbuka bila Anda sudah masuk sebagai admin.</p>
        </section>

        <div class="min-w-0">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                @foreach (\App\Models\Redemption::STATUSES as $k => $s)
                    <a href="{{ route('admin.points.redemptions.index', ['status' => $k]) }}" class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-semibold {{ $status === $k ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog' }}"><i class="{{ $s['icon'] }}"></i>{{ $s['label'] }} <span class="opacity-70">{{ $counts[$k] ?? 0 }}</span></a>
                @endforeach
                <form class="ml-auto"><input type="hidden" name="status" value="{{ $status }}"><input name="q" value="{{ $q }}" placeholder="Cari kode / NRP / nama" class="field !w-60 !py-2 text-sm"></form>
            </div>
            <div class="a-card overflow-hidden">
                <ul class="divide-y divide-white/[0.05]">
                    @forelse ($redemptions as $r)
                        <li><a href="{{ route('admin.points.redemptions.show', $r) }}" class="flex flex-wrap items-center gap-4 px-5 py-3.5 transition hover:bg-white/[0.02]">
                            <span class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-ridge">@if ($r->reward->imageUrl())<img src="{{ $r->reward->imageUrl() }}" alt="" class="h-full w-full object-cover">@else<span class="grid h-full place-items-center text-xl text-hivis"><i class="{{ $r->reward->categoryMeta()['icon'] }}"></i></span>@endif</span>
                            <span class="min-w-0 grow leading-tight">
                                <span class="block truncate font-semibold">{{ $r->reward->name }}</span>
                                <span class="text-sm text-fog">{{ $r->user->displayName() }} <span class="font-display text-xs text-mist">{{ $r->user->nrp }}</span> · <span class="text-mist">{{ $r->user->department }}</span></span>
                            </span>
                            <span class="font-display text-sm tracking-widest text-hivis">{{ $r->code }}</span>
                            <span class="w-36 text-right text-xs text-mist">
                                @if ($r->status === 'pending') ditukar {{ $r->created_at->locale('id')->diffForHumans() }}
                                @elseif ($r->status === 'fulfilled') diserahkan {{ $r->fulfilled_at->locale('id')->translatedFormat('j M H:i') }}<br>{{ $r->handler?->displayName() }}
                                @else dibatalkan {{ $r->cancelled_at?->locale('id')->translatedFormat('j M') }} @endif
                            </span>
                        </a></li>
                    @empty
                        <li class="px-6 py-14 text-center text-sm text-mist"><i class="ri-coupon-3-line text-4xl"></i><p class="mt-2">Tidak ada kupon {{ mb_strtolower(\App\Models\Redemption::STATUSES[$status]['label']) }}.</p></li>
                    @endforelse
                </ul>
            </div>
            <div class="mt-4">{{ $redemptions->links('admin.pagination') }}</div>
        </div>
    </div>
</x-layouts.admin>
