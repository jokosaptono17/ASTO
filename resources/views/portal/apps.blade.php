<x-layouts.portal title="Aplikasi">
    <div x-data="appsPage(@js($searchIndex), @js($favoriteIds), @js($tab))">
        @include('portal._topbar', ['heading' => 'Aplikasi', 'sub' => 'Semua sistem Plant ASTO di satu tempat'])

        <div class="sticky top-0 z-20 space-y-3 bg-pit/90 px-4 pt-1 pb-3 backdrop-blur-xl">
            <div class="relative">
                <i class="ri-search-line pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-xl text-mist"></i>
                <input x-model="q" type="search" placeholder="Cari aplikasi" class="field h-12 rounded-full pl-12">
            </div>
            <div class="flex gap-2" role="tablist">
                <button type="button" role="tab" @click="tab = 'semua'" :aria-selected="tab === 'semua'" :class="tab === 'semua' ? 'bg-neon text-[#04170d]' : 'bg-white/[0.05] text-fog'" class="rounded-full px-4 py-2 text-sm font-semibold transition">Semua</button>
                <button type="button" role="tab" @click="tab = 'favorit'" :aria-selected="tab === 'favorit'" :class="tab === 'favorit' ? 'bg-neon text-[#04170d]' : 'bg-white/[0.05] text-fog'" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold transition">
                    <i class="ri-star-fill"></i> Favorit <span class="opacity-70" x-text="favs.length ? `(${favs.length})` : ''"></span>
                </button>
            </div>
        </div>

        <div class="space-y-7 px-4 pt-2">
            @foreach ($groups as $group)
                <section x-show="anyVisible(@js($group['apps']->pluck('id')))">
                    <h2 class="flex items-center gap-2 text-[15px] font-semibold text-fog">
                        <i class="{{ $group['category']->icon }} text-lg text-neon"></i>{{ $group['category']->name }}
                    </h2>
                    <ul class="mt-2.5 space-y-2">
                        @foreach ($group['apps'] as $app)
                            <li x-show="visible({{ $app->id }})" class="flex items-center gap-3 rounded-2xl border border-white/[0.06] bg-bench p-3">
                                <a href="{{ route('apps.go', $app) }}" @if ($app->new_tab) target="_blank" rel="noopener" @endif class="flex min-w-0 grow items-center gap-3">
                                    <x-app-icon :app="$app" variant="chip" :size="48" />
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2">
                                            <span class="truncate text-[15px] font-semibold">{{ $app->name }}</span>
                                            @if ($app->badge) <span class="chip-penting shrink-0">{{ $app->badge }}</span> @endif
                                        </span>
                                        <span class="line-clamp-2 text-[13px] leading-snug text-mist">{{ $app->tagline ?: $app->host() }}</span>
                                    </span>
                                </a>
                                <button type="button" @click="toggleFav({{ $app->id }}, @js(route('apps.favorite', $app)))" :aria-pressed="favs.includes({{ $app->id }})"
                                        class="icon-btn h-11 w-11 shrink-0" :class="favs.includes({{ $app->id }}) && '!text-hivis'"
                                        :aria-label="favs.includes({{ $app->id }}) ? 'Hapus dari favorit' : 'Tambah ke favorit'">
                                    <i :class="favs.includes({{ $app->id }}) ? 'ri-star-fill' : 'ri-star-line'" class="text-[22px]"></i>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <div x-show="q && !results.length" x-cloak class="py-10 text-center">
                <p class="text-fog">Tidak ada aplikasi yang cocok dengan "<span x-text="q"></span>".</p>
                <button type="button" @click="askMentari()" class="btn-ghost mt-4"><i class="ri-chat-smile-3-line text-lg text-neon"></i> Tanya Mentari</button>
            </div>
            <div x-show="tab === 'favorit' && !favs.length" x-cloak class="flex flex-col items-center py-10 text-center">
                <i class="ri-star-smile-line text-5xl text-mist/50"></i>
                <p class="mt-3 max-w-[30ch] text-fog">Ketuk bintang pada aplikasi yang sering Anda pakai. Favorit tampil di beranda.</p>
            </div>
        </div>
    </div>
</x-layouts.portal>
