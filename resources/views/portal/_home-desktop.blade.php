{{-- Beranda desktop (≥ 1024 px): foto hero + panel kaca berisi tab kategori, pencarian, dan aplikasi --}}
<div class="hidden lg:block" x-data="homeHero(@js($searchIndex))">
    <section class="relative isolate h-[700px] overflow-hidden">
        <img src="{{ $hero['src'] }}" @if ($hero['srcset']) srcset="{{ $hero['srcset'] }}" sizes="100vw" @endif alt="" fetchpriority="high"
             class="hero-drift absolute inset-0 -z-20 h-full w-full object-cover">
        <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(6,18,13,.70)_0%,rgba(6,18,13,.22)_28%,rgba(6,18,13,.5)_52%,rgba(6,18,13,.9)_76%,#06120d_94%)]"></div>
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(45%_45%_at_12%_45%,rgba(61,255,142,.12),transparent)]"></div>
        <div aria-hidden="true" class="hero-mist absolute -inset-x-1/4 top-[300px] -z-10 h-56 bg-[radial-gradient(50%_50%_at_50%_50%,rgba(230,242,236,.16),transparent)] blur-2xl"></div>
        <x-contour class="absolute inset-0 -z-10 h-full w-full opacity-[.22] mix-blend-screen" />

        <div class="mx-auto max-w-7xl px-8 pt-[132px]">
            <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/30 px-3.5 py-1.5 text-sm text-fog backdrop-blur-md">
                <span class="h-2 w-2 rounded-full bg-neon shadow-[0_0_10px_var(--color-neon)]"></span>
                {{ now()->locale('id')->translatedFormat('l, j F Y') }} · <span class="font-display font-semibold text-ink" x-text="time"></span>
            </p>
            <h1 class="mt-5 max-w-3xl font-display text-[3.6rem] leading-[1.02] font-bold tracking-tight text-balance drop-shadow-[0_4px_30px_rgba(0,0,0,.5)]">
                <span x-text="greeting">Halo</span>, {{ $user->firstName() }}.<br>Mau buka apa hari ini?
            </h1>
            <p class="mt-4 flex items-center gap-4 text-[15px] text-fog">
                <span><i class="ri-building-2-line text-neon"></i> {{ $user->department ?: $user->roleLabel() }}</span>
                <span class="h-4 w-px bg-white/20"></span>
                <span class="font-display"><i class="ri-id-card-line text-neon"></i> {{ $user->nrp }}</span>
                <span class="h-4 w-px bg-white/20"></span>
                <span class="font-display font-semibold tracking-wide text-neon-soft">{{ \App\Models\Setting::get('slogan') }}</span>
            </p>
        </div>
    </section>

    {{-- Panel kaca --}}
    <section class="relative z-10 mx-auto -mt-[298px] max-w-7xl px-8" aria-label="Aplikasi">
        <div class="rounded-[30px] border border-white/[0.12] bg-pit/75 shadow-[0_40px_90px_-30px_rgba(0,0,0,.9)] backdrop-blur-2xl">
            {{-- Tab kategori --}}
            <div class="flex items-end gap-1 overflow-x-auto px-5 pt-4" role="tablist">
                @php $tabs = collect([['semua', 'Semua', 'ri-apps-2-line']])->merge($categories->map(fn ($c) => [$c->name, $c->name, $c->icon])); @endphp
                @foreach ($tabs as [$key, $label, $icon])
                    <button type="button" role="tab" @click="pick(@js($key))" :aria-selected="tab === @js($key) && !q"
                            class="group flex min-w-[104px] flex-col items-center gap-1.5 rounded-2xl px-3 pt-2.5 pb-3 text-sm font-semibold transition"
                            :class="tab === @js($key) && !q ? 'bg-neon text-[#04170d] shadow-[0_8px_24px_-8px_rgba(61,255,142,.7)]' : 'text-fog hover:bg-white/[0.06] hover:text-ink'">
                        <i class="{{ $icon }} text-[26px] leading-none"></i>
                        <span class="whitespace-nowrap">{{ $label }}</span>
                    </button>
                @endforeach
                <a href="{{ route('attendance.scan') }}" class="relative flex min-w-[104px] flex-col items-center gap-1.5 rounded-2xl px-3 pt-2.5 pb-3 text-sm font-semibold text-fog transition hover:bg-white/[0.06] hover:text-ink">
                    <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 rounded-full bg-hivis px-2 py-0.5 text-[10px] font-bold whitespace-nowrap text-[#1a1203]">{{ $attendance['pending']->isNotEmpty() ? 'Dibuka!' : 'Baru!' }}</span>
                    <i class="ri-qr-scan-2-line text-[26px] leading-none text-neon"></i><span>Absensi</span>
                </a>
                <a href="{{ route('apps.index') }}" class="ml-auto flex min-w-[96px] flex-col items-center gap-1.5 rounded-2xl px-3 pt-2.5 pb-3 text-sm font-semibold text-fog transition hover:bg-white/[0.06] hover:text-ink">
                    <i class="ri-more-fill text-[26px] leading-none"></i><span>Lainnya</span>
                </a>
            </div>

            <div class="mx-5 border-t border-white/[0.07]"></div>

            {{-- Pencarian --}}
            <form @submit.prevent="submit()" class="flex items-stretch gap-0 px-5 pt-5">
                <label for="desk-q" class="sr-only">Cari aplikasi</label>
                <div class="relative grow">
                    <i class="ri-search-line pointer-events-none absolute top-1/2 left-5 -translate-y-1/2 text-xl text-mist"></i>
                    <input id="desk-q" x-model="q" type="search" autocomplete="off" @keydown.slash.window="if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') { $event.preventDefault(); $el.focus() }"
                           placeholder="Cari aplikasi, dokumen, atau tanya Mentari…" class="h-16 w-full rounded-l-2xl border border-r-0 border-white/10 bg-white/[0.05] pr-16 pl-14 text-[17px] text-ink outline-none placeholder:text-mist focus:border-neon/50 focus:bg-white/[0.07]">
                    <kbd class="absolute top-1/2 right-5 -translate-y-1/2 rounded-md border border-white/15 px-2 py-0.5 font-display text-xs text-mist" x-show="!q">/</kbd>
                </div>
                <button class="grid w-16 place-items-center rounded-r-2xl bg-neon text-[#04170d] transition hover:brightness-110" aria-label="Cari"><i class="ri-search-2-line text-2xl"></i></button>
            </form>

            {{-- Aplikasi --}}
            <div class="min-h-[164px] px-5 pt-4 pb-5">
                <div class="grid grid-cols-5 gap-2">
                    <template x-for="(app, i) in shown" :key="app.id">
                        <a :href="app.href" :target="app.new_tab ? '_blank' : null" rel="noopener"
                           class="group flex items-center gap-3 rounded-2xl border border-transparent p-2.5 transition hover:border-white/10 hover:bg-white/[0.05]" :style="`--i: ${i}`">
                            <span class="app-chip relative h-12 w-12 shrink-0" :style="`--app: ${app.color}`">
                                <template x-if="app.icon"><i :class="app.icon" class="text-[26px]"></i></template>
                                <template x-if="!app.icon && app.image"><img :src="app.image" alt="" class="h-7 w-7 object-contain"></template>
                                <span x-show="app.status === 'down'" class="absolute -top-0.5 -right-0.5 h-3 w-3 rounded-full bg-alarm ring-2 ring-pit" title="Gangguan"></span>
                            </span>
                            <span class="min-w-0 leading-tight">
                                <span class="block truncate text-[14.5px] font-semibold group-hover:text-neon" x-text="app.name"></span>
                                <span class="block truncate text-xs text-mist" x-text="app.tagline || app.category"></span>
                            </span>
                        </a>
                    </template>
                </div>
                <div x-show="q && !results.length" x-cloak class="flex items-center gap-4 rounded-2xl border border-dashed border-white/10 p-5">
                    <x-mentari :size="56" class="m-still shrink-0" />
                    <p class="grow text-fog">Tidak ada aplikasi bernama "<span class="text-ink" x-text="q"></span>". Mentari bisa membantu mencarikan.</p>
                    <button type="button" @click="askMentari()" class="btn-neon !min-h-11 text-sm"><i class="ri-chat-smile-3-line text-lg"></i> Tanya Mentari</button>
                </div>
            </div>

            {{-- Terakhir dibuka --}}
            <div class="flex items-center gap-3 border-t border-white/[0.07] px-6 py-3.5 text-sm">
                @if ($recent->isNotEmpty())
                    <span class="shrink-0 text-mist"><i class="ri-history-line"></i> Terakhir dibuka</span>
                    <div class="flex min-w-0 gap-2 overflow-hidden">
                        @foreach ($recent->take(5) as $app)
                            <a href="{{ route('apps.go', $app) }}" @if ($app->new_tab) target="_blank" rel="noopener" @endif class="flex shrink-0 items-center gap-2 rounded-full border border-white/10 py-1 pr-3 pl-1 text-fog transition hover:border-neon/40 hover:text-ink">
                                <x-app-icon :app="$app" variant="chip" :size="26" /> {{ $app->name }}
                            </a>
                        @endforeach
                    </div>
                @else
                    <span class="text-mist"><i class="ri-lightbulb-line text-hivis"></i> Tekan <kbd class="rounded border border-white/15 px-1.5 font-display text-xs">/</kbd> untuk langsung mencari aplikasi.</span>
                @endif
                <a href="{{ route('apps.index') }}" class="ml-auto shrink-0 font-semibold text-neon hover:underline" x-show="!q">
                    <span x-text="more ? `+${more} aplikasi lain` : 'Semua aplikasi'"></span> <i class="ri-arrow-right-line"></i>
                </a>
            </div>
        </div>
    </section>
</div>
