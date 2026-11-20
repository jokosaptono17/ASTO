<x-layouts.admin title="Katalog hadiah" subtitle="Hadiah yang bisa ditukar karyawan dengan Poin HEBAT">
    <x-slot:actions>
        <a href="{{ route('admin.points.rewards.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Tambah hadiah</a>
    </x-slot:actions>
    @include('admin.points._tabs')

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
        @forelse ($rewards as $r)
            <a href="{{ route('admin.points.rewards.edit', $r) }}" class="a-card group overflow-hidden transition hover:border-hivis/40 {{ $r->is_active ? '' : 'opacity-50' }}">
                <div class="relative aspect-[4/3] bg-ridge">
                    @if ($r->imageUrl()) <img src="{{ $r->imageUrl() }}" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    @else <span class="grid h-full place-items-center text-5xl text-hivis/60"><i class="{{ $r->categoryMeta()['icon'] }}"></i></span> @endif
                    <span class="absolute top-3 left-3 chip bg-black/60 text-fog backdrop-blur"><i class="{{ $r->categoryMeta()['icon'] }}"></i> {{ $r->categoryMeta()['label'] }}</span>
                    @unless ($r->is_active) <span class="absolute top-3 right-3 chip-nonaktif">Nonaktif</span> @endunless
                    @if ($r->stock !== null && $r->stock <= 3 && $r->is_active) <span class="absolute top-3 right-3 chip bg-alarm text-white">{{ $r->stock ? 'Sisa '.$r->stock : 'Habis' }}</span> @endif
                </div>
                <div class="p-4">
                    <p class="truncate font-semibold">{{ $r->name }}</p>
                    <p class="mt-1 flex items-center gap-1.5 font-display text-xl font-bold text-hivis"><x-coin :size="18" />{{ number_format($r->cost, 0, ',', '.') }}</p>
                    <dl class="mt-3 grid grid-cols-3 gap-2 border-t border-white/[0.06] pt-3 text-center text-xs">
                        <div><dt class="text-mist">Stok</dt><dd class="font-display text-base font-bold">{{ $r->stock ?? '∞' }}</dd></div>
                        <div><dt class="text-mist">Ditukar</dt><dd class="font-display text-base font-bold">{{ $r->taken }}</dd></div>
                        <div><dt class="text-mist">Belum diambil</dt><dd class="font-display text-base font-bold {{ $r->waiting ? 'text-hivis' : '' }}">{{ $r->waiting }}</dd></div>
                    </dl>
                </div>
            </a>
        @empty
            <div class="a-card col-span-full flex flex-col items-center px-6 py-16 text-center">
                <i class="ri-gift-2-line text-5xl text-hivis"></i>
                <p class="mt-3 font-display text-xl font-semibold">Katalog masih kosong</p>
                <p class="mt-1 max-w-md text-sm text-mist">Tambahkan hadiah seperti merchandise ASTO, APD premium, atau kupon makan. Karyawan bisa langsung menukarnya dengan poin.</p>
                <a href="{{ route('admin.points.rewards.create') }}" class="btn-neon mt-4 !min-h-11 text-sm"><i class="ri-add-line"></i> Tambah hadiah pertama</a>
            </div>
        @endforelse
    </div>
</x-layouts.admin>
