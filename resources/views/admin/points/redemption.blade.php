<x-layouts.admin :title="'Kupon '.$r->code">
    @include('admin.points._tabs')
    @php $st = \App\Models\Redemption::STATUSES[$r->status]; $u = $r->user; @endphp
    <div class="mx-auto max-w-2xl space-y-5">
        <section class="relative overflow-hidden rounded-3xl border {{ $r->status === 'pending' ? 'border-hivis/40' : 'border-white/10' }} bg-bench">
            <x-contour class="pointer-events-none absolute -top-16 -right-20 h-64 w-96 opacity-25" />
            <div class="relative flex flex-wrap items-center gap-5 p-6">
                <span class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-ridge">@if ($r->reward->imageUrl())<img src="{{ $r->reward->imageUrl() }}" alt="" class="h-full w-full object-cover">@else<span class="grid h-full place-items-center text-4xl text-hivis"><i class="{{ $r->reward->categoryMeta()['icon'] }}"></i></span>@endif</span>
                <div class="min-w-0 grow">
                    <span class="{{ $st['chip'] }}"><i class="{{ $st['icon'] }}"></i> {{ $st['label'] }}</span>
                    <p class="mt-1.5 font-display text-2xl leading-tight font-bold">{{ $r->reward->name }}</p>
                    <p class="mt-1 flex items-center gap-1.5 text-hivis"><x-coin :size="16" />{{ number_format($r->cost, 0, ',', '.') }} poin · <span class="font-display tracking-widest">{{ $r->code }}</span></p>
                </div>
            </div>
            <div class="relative grid gap-4 border-t border-white/[0.06] bg-white/[0.02] p-6 sm:grid-cols-[auto_1fr] sm:items-center">
                <span class="grid h-16 w-16 place-items-center rounded-full bg-neon font-display text-xl font-bold text-[#04170d]">{{ $u->initials() }}</span>
                <div>
                    <p class="text-xs text-mist">Penerima — cocokkan dengan ID card</p>
                    <p class="font-display text-xl font-bold">{{ $u->displayName() }}</p>
                    <p class="text-sm text-fog"><span class="font-display">{{ $u->nrp }}</span> · {{ $u->position ?: '–' }} · {{ $u->department ?: '–' }}</p>
                </div>
            </div>
            <dl class="relative grid grid-cols-2 gap-4 border-t border-white/[0.06] p-6 text-sm">
                <div><dt class="text-xs text-mist">Ditukar</dt><dd>{{ $r->created_at->locale('id')->translatedFormat('l, j M Y · H:i') }}</dd></div>
                @if ($r->fulfilled_at) <div><dt class="text-xs text-mist">Diserahkan</dt><dd>{{ $r->fulfilled_at->locale('id')->translatedFormat('j M Y · H:i') }} oleh {{ $r->handler?->displayName() }}</dd></div> @endif
                @if ($r->cancelled_at) <div><dt class="text-xs text-mist">Dibatalkan</dt><dd>{{ $r->cancel_reason }} — oleh {{ $r->handler?->displayName() }}</dd></div> @endif
                @if ($r->reward->pickup_note) <div class="col-span-2"><dt class="text-xs text-mist">Cara mengambil</dt><dd>{{ $r->reward->pickup_note }}</dd></div> @endif
            </dl>
        </section>

        @error('action') <p class="error rounded-xl bg-alarm/10 px-4 py-3">{{ $message }}</p> @enderror

        @if ($r->status === 'pending')
            <form method="POST" action="{{ route('admin.points.redemptions.fulfill', $r) }}">
                @csrf
                <button class="btn-neon w-full !min-h-14 text-base"><i class="ri-hand-heart-line text-2xl"></i> Serahkan hadiah ke {{ $u->firstName() }}</button>
            </form>
            <details class="rounded-2xl border border-white/[0.07] bg-bench p-4">
                <summary class="cursor-pointer text-sm font-semibold text-red-300"><i class="ri-close-circle-line"></i> Batalkan kupon & kembalikan poin</summary>
                <form method="POST" action="{{ route('admin.points.redemptions.cancel', $r) }}" class="mt-3 flex gap-2" onsubmit="return confirm('Batalkan kupon dan kembalikan {{ $r->cost }} poin?')">
                    @csrf
                    <input name="reason" required maxlength="200" placeholder="Alasan, mis. stok habis / salah pilih" class="field !py-2">
                    <button class="btn-ghost !min-h-10 shrink-0 text-sm text-red-300">Batalkan</button>
                </form>
                @error('reason') <p class="error">{{ $message }}</p> @enderror
            </details>
        @endif
        <a href="{{ route('admin.points.redemptions.index') }}" class="block text-center text-sm text-mist hover:text-fog">← Semua kupon</a>
    </div>
</x-layouts.admin>
