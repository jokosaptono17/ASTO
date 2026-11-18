<x-layouts.admin title="Aplikasi">
    <x-slot:actions>
        <a href="{{ route('admin.apps.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Tambah aplikasi</a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            @foreach (\App\Models\AppLink::PLACEMENTS as $placement => $label)
                @php $list = $apps->where('placement', $placement)->values(); $limit = ['featured' => 5, 'grid' => 4][$placement] ?? null; @endphp
                <section class="a-card overflow-hidden">
                    <header class="flex flex-wrap items-center gap-2 border-b border-white/[0.06] px-4 py-3">
                        <h2 class="grow font-display font-semibold">{{ $label }}</h2>
                        @if ($limit)
                            <span class="text-xs {{ $list->where('is_active', true)->count() > $limit ? 'text-hivis' : 'text-mist' }}">
                                {{ $list->where('is_active', true)->count() }}/{{ $limit }} tampil di beranda{{ $list->where('is_active', true)->count() > $limit ? ' — kelebihan hanya muncul di daftar' : '' }}
                            </span>
                        @endif
                    </header>
                    @forelse ($list as $i => $app)
                        <div class="flex items-center gap-3 border-b border-white/[0.04] px-4 py-3 last:border-0 {{ $app->is_active ? '' : 'opacity-50' }}">
                            <div class="flex flex-col">
                                <form method="POST" action="{{ route('admin.apps.move', $app) }}">@csrf<input type="hidden" name="direction" value="up">
                                    <button class="icon-btn h-6 w-7" @disabled($i === 0) aria-label="Naikkan"><i class="ri-arrow-up-s-line text-lg"></i></button></form>
                                <form method="POST" action="{{ route('admin.apps.move', $app) }}">@csrf<input type="hidden" name="direction" value="down">
                                    <button class="icon-btn h-6 w-7" @disabled($i === $list->count() - 1) aria-label="Turunkan"><i class="ri-arrow-down-s-line text-lg"></i></button></form>
                            </div>
                            <x-app-icon :app="$app" :variant="$placement === 'featured' ? 'orb' : 'chip'" :size="42" />
                            <a href="{{ route('admin.apps.edit', $app) }}" class="min-w-0 grow">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{ $app->name }}</span>
                                    @if (! $app->is_active) <span class="chip-nonaktif">Nonaktif</span> @endif
                                    @if ($app->badge) <span class="chip-penting">{{ $app->badge }}</span> @endif
                                </span>
                                <span class="block truncate text-xs text-mist">{{ $app->category?->name ?? 'Tanpa kategori' }} · {{ $app->host() }}</span>
                            </a>
                            <span class="hidden shrink-0 text-right text-xs sm:block">
                                @if (! $app->health_check) <span class="text-mist">Tidak dicek</span>
                                @elseif ($app->health_status === 'up') <span class="text-neon">● Online</span>@if ($app->health_ms)<span class="block text-mist">{{ $app->health_ms }} ms</span>@endif
                                @elseif ($app->health_status === 'down') <span class="text-alarm">● Gangguan</span>
                                @else <span class="text-mist">Belum dicek</span> @endif
                            </span>
                            <a href="{{ route('admin.apps.edit', $app) }}" class="icon-btn" aria-label="Ubah {{ $app->name }}"><i class="ri-pencil-line text-lg"></i></a>
                        </div>
                    @empty
                        <p class="px-4 py-5 text-sm text-mist">Belum ada aplikasi di kelompok ini.</p>
                    @endforelse
                </section>
            @endforeach
        </div>

        {{-- Kategori --}}
        <aside class="a-card self-start p-5" x-data="{ edit: null }">
            <h2 class="font-display font-semibold">Kategori</h2>
            <p class="mt-1 text-xs text-mist">Mengelompokkan aplikasi di halaman Aplikasi. Ikon memakai nama Remix Icon.</p>
            <ul class="mt-4 space-y-1.5">
                @foreach ($categories as $cat)
                    <li>
                        <div x-show="edit !== {{ $cat->id }}" class="flex items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-white/[0.03]">
                            <i class="{{ $cat->icon }} text-lg text-neon"></i>
                            <span class="grow text-sm">{{ $cat->name }}</span>
                            <span class="text-xs text-mist">{{ $apps->where('category_id', $cat->id)->count() }}</span>
                            <button type="button" @click="edit = {{ $cat->id }}" class="icon-btn h-8 w-8" aria-label="Ubah kategori"><i class="ri-pencil-line"></i></button>
                        </div>
                        <form x-show="edit === {{ $cat->id }}" x-cloak method="POST" action="{{ route('admin.categories.update', $cat) }}" class="space-y-2 rounded-xl bg-white/[0.03] p-2.5">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $cat->name }}" class="field !py-2 text-sm" required>
                            <div class="flex gap-2">
                                <input name="icon" value="{{ $cat->icon }}" class="field !py-2 text-sm" required>
                                <input name="sort_order" value="{{ $cat->sort_order }}" type="number" class="field !w-20 !py-2 text-sm" title="Urutan">
                            </div>
                            <div class="flex gap-2">
                                <button class="btn-neon !min-h-9 grow text-sm">Simpan</button>
                                <button type="button" @click="edit = null" class="btn-ghost !min-h-9 text-sm">Batal</button>
                                <button form="del-cat-{{ $cat->id }}" class="icon-btn h-9 w-9 text-red-300" aria-label="Hapus kategori"><i class="ri-delete-bin-line"></i></button>
                            </div>
                        </form>
                        <form id="del-cat-{{ $cat->id }}" method="POST" action="{{ route('admin.categories.destroy', $cat) }}" onsubmit="return confirm('Hapus kategori {{ $cat->name }}?')">@csrf @method('DELETE')</form>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-4 space-y-2 border-t border-white/[0.06] pt-4">
                @csrf
                <input name="name" placeholder="Nama kategori baru" class="field !py-2 text-sm" required>
                <div class="flex gap-2">
                    <input name="icon" value="ri-folder-line" class="field !py-2 text-sm" required>
                    <button class="btn-ghost !min-h-10 shrink-0 text-sm"><i class="ri-add-line"></i> Tambah</button>
                </div>
            </form>
        </aside>
    </div>
</x-layouts.admin>
