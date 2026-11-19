<x-layouts.portal title="Beranda" :mentari-tips="['Halo, '.$user->firstName().'! 👋', 'Bingung cari aplikasi? Tanya saya saja.']">
    <div x-data="appSearch(@js($searchIndex))">
        {{-- Header: kontur pit + identitas --}}
        <header class="pit-header relative overflow-hidden rounded-b-[34px] px-5 pt-[max(1rem,env(safe-area-inset-top))] pb-24">
            <x-contour class="absolute inset-0 h-full w-full opacity-60" />
            <div class="relative flex items-center gap-3 pt-2">
                <img src="/images/plant-asto.png" alt="Plant ASTO" class="h-11 w-auto drop-shadow-[0_6px_14px_rgba(0,0,0,.55)]">
                <div class="min-w-0 grow leading-tight">
                    <div class="font-display text-[1.45rem] font-bold">{{ $siteName }}</div>
                    <div class="truncate text-xs text-neon-soft/80">{{ \App\Models\Setting::get('slogan') }}</div>
                </div>
                <a href="{{ route('attendance.scan') }}" class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-neon/15 text-neon ring-1 ring-neon/40" aria-label="Scan absen">
                    <i class="ri-qr-scan-2-line text-[22px]"></i>
                </a>
                <a href="{{ route('info.index') }}" class="relative grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-white/[0.07] ring-1 ring-white/10" aria-label="Info dan pengumuman">
                    <i class="ri-notification-3-line text-[22px]"></i>
                    @if ($unread) <span class="badge-count !top-1.5 !right-1.5">{{ $unread > 9 ? '9+' : $unread }}</span> @endif
                </a>
                <a href="{{ \App\Models\Setting::whatsappLink() }}" target="_blank" rel="noopener" class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-white/[0.07] ring-1 ring-white/10" aria-label="Bantuan admin">
                    <i class="ri-customer-service-2-line text-[22px]"></i>
                </a>
            </div>

            {{-- Strip identitas: sapaan | departemen | NRP --}}
            <div class="relative mt-5 flex items-center divide-x divide-white/15 text-sm">
                <div class="flex max-w-[48%] shrink-0 items-center gap-2 pr-3">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-neon font-display text-xs font-bold text-[#04170d]">{{ $user->initials() }}</span>
                    <span class="truncate font-semibold">Halo, {{ $user->firstName() }}</span>
                </div>
                <div class="flex min-w-0 flex-1 items-center gap-1.5 px-3 text-fog">
                    <i class="ri-building-2-line text-neon"></i><span class="truncate">{{ $user->department ?: $user->roleLabel() }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-1.5 pl-3 font-display text-fog">
                    <i class="ri-id-card-line text-neon"></i>{{ $user->nrp }}
                </div>
            </div>
        </header>

        {{-- Panel aplikasi --}}
        <section class="panel relative z-10 mx-4 -mt-16 px-3 pt-5 pb-4" aria-label="Aplikasi">
            <div class="grid grid-cols-5 gap-y-4">
                @foreach ($featured as $i => $app)
                    <a href="{{ route('apps.go', $app) }}" @if ($app->new_tab) target="_blank" rel="noopener" @endif class="rise group flex flex-col items-center gap-2 text-center" style="--i: {{ $i }}">
                        <x-app-icon :app="$app" variant="orb" />
                        <span class="line-clamp-2 px-0.5 text-[11.5px] leading-tight font-medium text-fog [overflow-wrap:anywhere] group-hover:text-ink">{{ $app->name }}</span>
                    </a>
                @endforeach
            </div>
            @if ($shortcuts->isNotEmpty() || $moreCount)
                <div class="mt-5 grid grid-cols-5 gap-y-3 border-t border-white/[0.06] pt-4">
                    @foreach ($shortcuts as $i => $app)
                        <a href="{{ route('apps.go', $app) }}" @if ($app->new_tab) target="_blank" rel="noopener" @endif class="rise group flex flex-col items-center gap-1.5 text-center" style="--i: {{ $i + 5 }}">
                            <x-app-icon :app="$app" variant="chip" />
                            <span class="line-clamp-2 px-0.5 text-[11.5px] leading-tight text-fog group-hover:text-ink">{{ $app->name }}</span>
                        </a>
                    @endforeach
                    <a href="{{ route('apps.index') }}" class="rise flex flex-col items-center gap-1.5 text-center" style="--i: 9">
                        <span class="grid h-[46px] w-[46px] place-items-center rounded-2xl border border-white/10 bg-white/[0.05] text-mist"><i class="ri-arrow-right-s-line text-2xl"></i></span>
                        <span class="text-[11.5px] leading-tight text-fog">{{ $moreCount ? '+'.$moreCount.' lainnya' : 'Semua' }}</span>
                    </a>
                </div>
            @endif
        </section>

        {{-- Pencarian --}}
        <div class="px-4 pt-4">
            <button type="button" @click="show()" class="flex h-14 w-full items-center gap-3 rounded-full border border-white/10 bg-white/[0.04] px-5 text-left text-mist transition hover:border-neon/40">
                <i class="ri-search-line text-xl"></i>
                <span class="grow">Cari aplikasi atau dokumen</span>
                <i class="ri-sparkling-2-line text-xl text-neon" aria-hidden="true"></i>
            </button>
        </div>

        {{-- Sesi absensi yang sedang berlangsung --}}
        @foreach ($attendance as $s)
            <a href="{{ $s->already ? route('attendance.checkin', $s->code) : ($s->checkin_mode === 'open' ? route('attendance.checkin', $s->code) : route('attendance.scan')) }}"
               class="mx-4 mt-4 flex items-center gap-3 overflow-hidden rounded-2xl border p-3.5 {{ $s->already ? 'border-white/[0.07] bg-bench' : 'border-neon/40 bg-gradient-to-r from-neon/[0.14] to-transparent' }}">
                <span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-xl" style="background: color-mix(in oklab, {{ $s->type->color }} 18%, transparent); color: {{ $s->type->color }}">
                    <i class="{{ $s->type->icon }} text-2xl"></i>
                    @unless ($s->already) <span class="absolute -top-1 -right-1 h-3 w-3 animate-ping rounded-full bg-neon"></span><span class="absolute -top-1 -right-1 h-3 w-3 rounded-full bg-neon"></span> @endunless
                </span>
                <span class="min-w-0 grow">
                    <span class="block truncate font-semibold">{{ $s->type->name }}{{ $s->title ? ' — '.$s->title : '' }}</span>
                    <span class="block truncate text-sm text-mist">{{ $s->starts_at->format('H:i') }}{{ $s->location ? ' · '.$s->location : '' }} · tutup {{ $s->closes_at->format('H:i') }}</span>
                </span>
                @if ($s->already)
                    <span class="chip-tayang shrink-0"><i class="ri-check-line"></i> Sudah</span>
                @else
                    <span class="shrink-0 rounded-xl bg-neon px-3 py-2 text-sm font-semibold text-[#04170d]">{{ $s->checkin_mode === 'open' ? 'Absen' : 'Scan' }}</span>
                @endif
            </a>
        @endforeach

        {{-- Pengumuman penting / darurat --}}
        @if ($ticker)
            <a href="{{ route('info.show', $ticker) }}" class="mx-4 mt-4 flex overflow-hidden rounded-2xl border {{ $ticker->level === 'darurat' ? 'border-alarm/40 bg-alarm/10' : 'border-hivis/30 bg-hivis/[0.07]' }}">
                <span class="{{ $ticker->level === 'darurat' ? 'hazard' : 'bg-hivis' }} w-2 shrink-0"></span>
                <span class="ticker-sweep relative flex min-w-0 grow items-center gap-3 px-4 py-3">
                    <i class="{{ \App\Models\Announcement::LEVELS[$ticker->level]['icon'] }} text-2xl {{ $ticker->level === 'darurat' ? 'text-alarm' : 'text-hivis' }}"></i>
                    <span class="min-w-0 grow">
                        <span class="block truncate text-[15px] font-semibold">{{ $ticker->title }}</span>
                        <span class="block truncate text-sm text-fog">{{ \Illuminate\Support\Str::limit(strip_tags($ticker->body), 80) }}</span>
                    </span>
                    <i class="ri-arrow-right-s-line text-xl text-mist"></i>
                </span>
            </a>
        @endif

        {{-- Terakhir dibuka --}}
        @if ($recent->isNotEmpty())
            <section class="mt-7">
                <h2 class="section-title px-4">Terakhir dibuka</h2>
                <div class="scroller mt-3 pb-1">
                    @foreach ($recent as $app)
                        <a href="{{ route('apps.go', $app) }}" @if ($app->new_tab) target="_blank" rel="noopener" @endif
                           class="flex w-[220px] shrink-0 snap-start items-center gap-3 rounded-2xl border border-white/[0.07] bg-bench p-3 transition hover:border-white/15">
                            <x-app-icon :app="$app" variant="chip" :size="44" />
                            <span class="min-w-0">
                                <span class="block truncate text-[15px] font-semibold">{{ $app->name }}</span>
                                <span class="block truncate text-xs text-mist">{{ $app->host() }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Banner lebar --}}
        @if ($banners->isNotEmpty())
            <section class="mt-7" x-data="carousel({{ $banners->count() }})" aria-label="Banner">
                <div x-ref="track" @scroll.debounce.60ms="onScroll()" @pointerdown="clearInterval(timer)" class="scroller">
                    @foreach ($banners as $banner)
                        <a href="{{ route('campaigns.open', $banner) }}" @if ($banner->link_url && ! str_starts_with($banner->link_url, '/')) target="_blank" rel="noopener" @endif
                           class="campaign-card aspect-[2/1] w-[calc(100%-8px)]">
                            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
                @if ($banners->count() > 1)
                    <div class="mt-3 flex justify-center gap-1.5">
                        @foreach ($banners as $i => $b)
                            <button type="button" @click="go({{ $i }})" :class="active === {{ $i }} ? 'w-6 bg-neon' : 'w-1.5 bg-white/25'" class="h-1.5 rounded-full transition-all" aria-label="Banner {{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        {{-- Kampanye (kartu 4:5) --}}
        <section class="mt-7">
            <div class="flex items-end justify-between px-4">
                <h2 class="section-title">{{ $campaignTitle }}</h2>
                @if ($cards->isNotEmpty()) <a href="{{ route('info.index') }}#kampanye" class="section-link">Semua <i class="ri-arrow-right-s-line"></i></a> @endif
            </div>
            @if ($cards->isNotEmpty())
                <div class="scroller mt-3 pb-1">
                    @foreach ($cards as $card)
                        <a href="{{ route('campaigns.open', $card) }}" @if ($card->link_url && ! str_starts_with($card->link_url, '/')) target="_blank" rel="noopener" @endif
                           class="campaign-card aspect-[4/5] w-[46%] min-w-[168px]">
                            <img src="{{ $card->imageUrl() }}" alt="{{ $card->title }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
            @else
                <div class="mx-4 mt-3 flex items-center gap-4 rounded-[22px] border border-dashed border-white/10 p-5">
                    <x-mentari :size="64" class="m-still shrink-0" />
                    <p class="text-sm leading-relaxed text-mist">Belum ada kampanye yang tayang. Kampanye safety, training, dan improvement akan muncul di sini.</p>
                </div>
            @endif
        </section>

        {{-- Favorit --}}
        @if ($favorites->isNotEmpty())
            <section class="mt-7 px-4">
                <div class="flex items-end justify-between">
                    <h2 class="section-title">Favorit saya</h2>
                    <a href="{{ route('apps.index', ['tab' => 'favorit']) }}" class="section-link">Atur <i class="ri-arrow-right-s-line"></i></a>
                </div>
                <div class="mt-3 grid grid-cols-4 gap-3">
                    @foreach ($favorites->take(8) as $app)
                        <a href="{{ route('apps.go', $app) }}" @if ($app->new_tab) target="_blank" rel="noopener" @endif class="flex flex-col items-center gap-1.5 rounded-2xl bg-bench p-3 text-center">
                            <x-app-icon :app="$app" variant="chip" :size="42" />
                            <span class="line-clamp-1 text-xs text-fog">{{ $app->name }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Pasang aplikasi --}}
        <section x-data x-show="!$store.install.installed && ($store.install.prompt || $store.install.ios)" x-cloak class="mx-4 mt-7">
            <div class="flex items-center gap-4 rounded-[22px] border border-neon/20 bg-gradient-to-r from-neon/10 to-transparent p-4">
                <img src="/icons/icon-192.png" alt="" class="h-12 w-12 rounded-xl">
                <div class="min-w-0 grow">
                    <p class="text-[15px] font-semibold">Pasang {{ $siteName }} di HP</p>
                    <p class="text-sm text-mist" x-text="$store.install.ios ? 'Safari → Bagikan → Tambah ke Layar Utama' : 'Buka langsung dari layar utama, tanpa ketik alamat.'"></p>
                </div>
                <button x-show="$store.install.prompt" type="button" @click="$store.install.run()" class="btn-neon !min-h-10 shrink-0 !px-4 text-sm">Pasang</button>
            </div>
        </section>

        <footer class="mt-10 flex flex-col items-center gap-3 px-4 pb-4 text-center">
            <img src="/images/kpp-mining.png" alt="KPP Mining" class="h-10 w-auto opacity-70">
            <p class="text-xs text-mist/70">Plant ASTO · PT Kalimantan Prima Persada</p>
        </footer>

        {{-- Lapisan pencarian --}}
        <template x-teleport="body">
            <div x-show="open" x-cloak x-transition.opacity.duration.150ms @keydown.escape.window="hide()" class="fixed inset-0 z-50 bg-pit/97 backdrop-blur-xl">
                <div class="mx-auto flex h-full max-w-[480px] flex-col">
                    <div class="flex items-center gap-2 px-3 pt-[max(.75rem,env(safe-area-inset-top))] pb-3">
                        <button type="button" @click="hide()" class="icon-btn h-11 w-11" aria-label="Tutup pencarian"><i class="ri-arrow-left-line text-2xl"></i></button>
                        <div class="relative grow">
                            <i class="ri-search-line pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-xl text-mist"></i>
                            <input x-ref="q" x-model="q" type="search" placeholder="Ketik nama aplikasi…" enterkeyhint="search" class="field h-12 rounded-full pl-12">
                        </div>
                    </div>
                    <ul class="grow space-y-1 overflow-y-auto px-3 pb-6">
                        <template x-for="app in results" :key="app.id">
                            <li>
                                <a :href="app.href" :target="app.new_tab ? '_blank' : null" rel="noopener" class="flex items-center gap-3 rounded-2xl p-2.5 hover:bg-white/[0.05]">
                                    <span class="app-chip h-12 w-12 shrink-0" :style="`--app: ${app.color}`">
                                        <template x-if="app.icon"><i :class="app.icon" class="text-2xl"></i></template>
                                        <template x-if="app.image"><img :src="app.image" alt="" class="h-7 w-7 object-contain"></template>
                                    </span>
                                    <span class="min-w-0 grow">
                                        <span class="block truncate font-semibold" x-text="app.name"></span>
                                        <span class="block truncate text-sm text-mist" x-text="app.tagline || app.category"></span>
                                    </span>
                                    <span x-show="app.status === 'down'" class="chip-nonaktif">Gangguan</span>
                                </a>
                            </li>
                        </template>
                        <li x-show="q && !results.length" class="px-3 pt-10 text-center">
                            <p class="text-fog">Tidak ada aplikasi bernama "<span x-text="q"></span>".</p>
                            <button type="button" @click="askMentari()" class="btn-ghost mt-4"><i class="ri-chat-smile-3-line text-lg text-neon"></i> Tanya Mentari</button>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </div>
</x-layouts.portal>
