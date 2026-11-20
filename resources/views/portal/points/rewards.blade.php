<x-layouts.portal title="Katalog hadiah">
    @php
        $balance = (int) $user->points_balance;
        $items = $rewards->map(fn ($r) => [
            'id' => $r->id, 'name' => $r->name, 'description' => $r->description, 'cost' => $r->cost, 'stock' => $r->stock,
            'limit' => $r->per_user_limit, 'mine' => (int) $r->mine, 'image' => $r->imageUrl(), 'pickup' => $r->pickup_note,
            'category' => $r->category, 'categoryLabel' => $r->categoryMeta()['label'], 'icon' => $r->categoryMeta()['icon'],
            'url' => route('points.redeem', $r),
        ])->values();
    @endphp
    @include('portal._topbar', ['heading' => 'Katalog hadiah', 'sub' => 'Tukar Poin HEBAT dengan hadiah dari tim admin', 'back' => route('points.index')])

    <div x-data="rewardShop(@js($items), {{ $balance }}, @js(old('reward') ? (int) old('reward') : null))" class="px-4 lg:px-0">
        {{-- Saldo + filter --}}
        <div class="sticky top-0 z-20 -mx-4 space-y-3 bg-pit/90 px-4 pt-1 pb-3 backdrop-blur-xl lg:top-[72px] lg:mx-0 lg:flex lg:items-center lg:gap-4 lg:space-y-0 lg:border-b lg:border-white/[0.06] lg:px-0 lg:py-4">
            <a href="{{ route('points.index') }}" class="flex items-center gap-3 rounded-2xl border border-hivis/25 bg-hivis/[0.06] px-4 py-2.5 lg:w-auto">
                <x-coin :size="30" />
                <span class="leading-tight"><span class="block text-xs text-mist">Saldo Anda</span><span class="font-display text-xl font-bold text-[#ffd76a]">{{ number_format($balance, 0, ',', '.') }}</span></span>
                <i class="ri-arrow-right-s-line ml-auto text-xl text-mist lg:ml-3"></i>
            </a>
            <div class="scroller -mx-4 gap-2 lg:mx-0 lg:ml-auto lg:px-0" role="tablist">
                <button type="button" @click="cat = 'semua'" :class="cat === 'semua' ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog'" class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold">Semua</button>
                <button type="button" @click="cat = 'bisa'" :class="cat === 'bisa' ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog'" class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold"><i class="ri-check-line"></i> Bisa ditukar</button>
                @foreach ($categories as $k => $c)
                    <button type="button" @click="cat = @js($k)" :class="cat === @js($k) ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog'" class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap"><i class="{{ $c['icon'] }}"></i> {{ $c['label'] }}</button>
                @endforeach
            </div>
        </div>

        {{-- Grid hadiah --}}
        <div class="mt-3 grid grid-cols-2 gap-3 lg:mt-6 lg:grid-cols-4 lg:gap-5">
            <template x-for="r in shown" :key="r.id">
                <button type="button" :id="'hadiah-' + r.id" @click="open(r)" class="group flex flex-col overflow-hidden rounded-2xl border border-white/[0.07] bg-bench text-left transition hover:border-hivis/40 lg:hover:-translate-y-0.5 lg:hover:shadow-[0_20px_40px_-24px_rgba(255,194,61,.5)]">
                    <span class="relative block aspect-square overflow-hidden bg-ridge">
                        <template x-if="r.image"><img :src="r.image" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy"></template>
                        <template x-if="!r.image"><span class="grid h-full place-items-center bg-[radial-gradient(circle_at_50%_40%,rgba(255,194,61,.15),transparent_60%)] text-5xl text-hivis/70"><i :class="r.icon"></i></span></template>
                        <span x-show="r.stock !== null && r.stock <= 5 && r.stock > 0" class="absolute top-2 left-2 rounded-full bg-black/60 px-2 py-0.5 text-[11px] font-semibold text-hivis backdrop-blur" x-text="`Sisa ${r.stock}`"></span>
                        <span x-show="r.stock === 0" class="absolute inset-0 grid place-items-center bg-black/60 font-display text-lg font-bold text-fog">Habis</span>
                    </span>
                    <span class="flex grow flex-col p-3">
                        <span class="text-[11px] text-mist" x-text="r.categoryLabel"></span>
                        <span class="line-clamp-2 text-[14.5px] leading-snug font-semibold" x-text="r.name"></span>
                        <span class="mt-auto pt-2">
                            <span class="flex items-center gap-1.5 font-display text-lg font-bold text-hivis"><x-coin :size="18" /><span x-text="fmt(r.cost)"></span></span>
                            <span class="mt-1.5 block h-1 overflow-hidden rounded-full bg-white/[0.07]"><span class="block h-full rounded-full" :class="balance >= r.cost ? 'bg-neon' : 'bg-hivis/70'" :style="`width: ${Math.min(100, balance / r.cost * 100)}%`"></span></span>
                            <span class="mt-1 block text-[11px]" :class="balance >= r.cost ? 'text-neon' : 'text-mist'" x-text="balance >= r.cost ? 'Bisa ditukar' : `Kurang ${fmt(r.cost - balance)} poin`"></span>
                        </span>
                    </span>
                </button>
            </template>
        </div>
        <div x-show="!shown.length" x-cloak class="mt-6 flex flex-col items-center rounded-2xl border border-dashed border-white/10 px-6 py-12 text-center">
            <i class="ri-gift-2-line text-5xl text-mist"></i>
            <p class="mt-2 font-semibold" x-text="items.length ? 'Belum ada hadiah di kategori ini' : 'Katalog hadiah belum diisi'"></p>
            <p class="mt-1 text-sm text-mist">Tim admin akan segera menambahkan hadiah. Terus kumpulkan poin!</p>
        </div>

        {{-- Lembar konfirmasi --}}
        <template x-teleport="body">
            <div x-show="sel" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-6" @keydown.escape.window="sel = null">
                <div x-show="sel" x-transition.opacity @click="sel = null" class="absolute inset-0 bg-black/75 backdrop-blur-sm"></div>
                <div x-show="sel" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full sm:translate-y-6 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                     class="relative max-h-[92svh] w-full max-w-[480px] overflow-y-auto rounded-t-[28px] border border-white/10 bg-bench pb-[env(safe-area-inset-bottom)] sm:rounded-[28px]" role="dialog" aria-modal="true">
                    <template x-if="sel">
                        <div>
                            <div class="relative aspect-[16/10] bg-ridge">
                                <template x-if="sel.image"><img :src="sel.image" alt="" class="h-full w-full object-cover"></template>
                                <template x-if="!sel.image"><span class="grid h-full place-items-center text-7xl text-hivis/60"><i :class="sel.icon"></i></span></template>
                                <div class="absolute inset-0 bg-gradient-to-t from-bench via-transparent"></div>
                                <button type="button" @click="sel = null" class="absolute top-3 right-3 grid h-10 w-10 place-items-center rounded-full bg-black/60 text-white" aria-label="Tutup"><i class="ri-close-line text-2xl"></i></button>
                            </div>
                            <div class="space-y-4 p-5 pt-1">
                                <div>
                                    <p class="text-xs text-mist" x-text="sel.categoryLabel"></p>
                                    <h2 class="font-display text-2xl leading-tight font-bold" x-text="sel.name"></h2>
                                    <p x-show="sel.description" class="mt-1.5 text-sm leading-relaxed whitespace-pre-line text-fog" x-text="sel.description"></p>
                                </div>
                                <ul class="space-y-1.5 text-sm text-fog">
                                    <li x-show="sel.pickup" class="flex gap-2"><i class="ri-map-pin-line text-neon"></i><span x-text="sel.pickup"></span></li>
                                    <li x-show="sel.stock !== null" class="flex gap-2"><i class="ri-archive-line text-neon"></i><span x-text="`Stok tersisa ${sel.stock}`"></span></li>
                                    <li x-show="sel.limit" class="flex gap-2"><i class="ri-user-line text-neon"></i><span x-text="`Maks ${sel.limit}× per karyawan · sudah ${sel.mine}×`"></span></li>
                                </ul>
                                <div class="grid grid-cols-3 items-center rounded-2xl bg-white/[0.04] p-3 text-center">
                                    <div><p class="text-[11px] text-mist">Saldo</p><p class="font-display font-bold" x-text="fmt(balance)"></p></div>
                                    <div><p class="text-[11px] text-mist">Harga</p><p class="font-display font-bold text-hivis" x-text="'−' + fmt(sel.cost)"></p></div>
                                    <div><p class="text-[11px] text-mist">Sisa</p><p class="font-display font-bold" :class="balance - sel.cost < 0 ? 'text-alarm' : 'text-neon'" x-text="fmt(balance - sel.cost)"></p></div>
                                </div>

                                <form method="POST" :action="sel.url" x-show="canRedeem(sel)" @submit="busy = true">
                                    @csrf
                                    <label for="pin" class="label">Konfirmasi dengan PIN Anda</label>
                                    <input id="pin" name="pin" x-ref="pin" type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="off" required
                                           class="field h-14 text-center font-display text-2xl tracking-[.6em]" placeholder="••••••">
                                    @error('pin') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
                                    <button class="mt-3 inline-flex min-h-13 w-full items-center justify-center gap-2 rounded-2xl bg-hivis py-3.5 text-[16px] font-semibold text-[#1a1203] transition hover:brightness-105 disabled:opacity-60" :disabled="busy">
                                        <i class="text-xl" :class="busy ? 'ri-loader-4-line animate-spin' : 'ri-gift-2-line'"></i> <span x-text="`Tukar ${fmt(sel.cost)} poin`"></span>
                                    </button>
                                </form>
                                <p x-show="!canRedeem(sel)" class="rounded-2xl bg-white/[0.04] p-3 text-center text-sm text-fog" x-text="reason(sel)"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</x-layouts.portal>
