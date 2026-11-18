<x-layouts.admin title="Kampanye">
    <x-slot:actions>
        <a href="{{ route('admin.campaigns.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Kampanye baru</a>
    </x-slot:actions>

    @php $filters = ['' => 'Semua', 'tayang' => 'Tayang', 'terjadwal' => 'Terjadwal', 'berakhir' => 'Berakhir', 'nonaktif' => 'Nonaktif']; @endphp
    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ($filters as $key => $label)
            <a href="{{ route('admin.campaigns.index', $key ? ['status' => $key] : []) }}"
               class="rounded-full px-4 py-1.5 text-sm font-semibold {{ ($status ?? '') === $key ? 'bg-neon text-[#04170d]' : 'bg-white/[0.05] text-fog hover:bg-white/10' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($campaigns->isEmpty())
        <div class="a-card flex flex-col items-center px-6 py-14 text-center">
            <x-mentari :size="110" />
            <p class="mt-3 font-semibold">Belum ada kampanye di sini</p>
            <p class="mt-1 max-w-md text-sm text-mist">Unggah poster safety, training, atau improvement. Gambar otomatis dipotong dan dikompres agar ringan dibuka di HP.</p>
            <a href="{{ route('admin.campaigns.create') }}" class="btn-neon mt-5 text-sm"><i class="ri-add-line text-lg"></i> Buat kampanye</a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
            @foreach ($campaigns as $c)
                <article class="a-card flex flex-col overflow-hidden">
                    <a href="{{ route('admin.campaigns.edit', $c) }}" class="relative block bg-ridge {{ $c->format === 'banner' ? 'aspect-[2/1]' : 'aspect-[4/5]' }}">
                        <img src="{{ $c->imageUrl() }}" alt="" class="h-full w-full object-cover {{ $c->status() !== 'tayang' ? 'opacity-50 grayscale' : '' }}" loading="lazy">
                        <span class="chip-{{ $c->status() }} absolute top-2 left-2 backdrop-blur">{{ ucfirst($c->status()) }}</span>
                        @if ($c->format === 'banner') <span class="chip absolute top-2 right-2 bg-black/60 text-fog">Banner</span> @endif
                    </a>
                    <div class="flex grow flex-col p-3">
                        <a href="{{ route('admin.campaigns.edit', $c) }}" class="line-clamp-2 text-sm font-semibold hover:text-neon">{{ $c->title }}</a>
                        <p class="mt-1 text-xs text-mist">
                            {{ $c->starts_at?->locale('id')->translatedFormat('j M') ?? 'Sekarang' }} – {{ $c->ends_at?->locale('id')->translatedFormat('j M Y') ?? 'tanpa batas' }}
                        </p>
                        <div class="mt-auto flex items-center gap-1 pt-3">
                            <span class="grow text-xs text-mist"><i class="ri-cursor-line"></i> {{ number_format($c->clicks, 0, ',', '.') }} klik</span>
                            <form method="POST" action="{{ route('admin.campaigns.toggle', $c) }}">@csrf @method('PATCH')
                                <button class="icon-btn" title="{{ $c->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" aria-label="{{ $c->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"><i class="{{ $c->is_active ? 'ri-eye-off-line' : 'ri-eye-line' }} text-lg"></i></button>
                            </form>
                            <a href="{{ route('admin.campaigns.edit', $c) }}" class="icon-btn" aria-label="Ubah"><i class="ri-pencil-line text-lg"></i></a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
