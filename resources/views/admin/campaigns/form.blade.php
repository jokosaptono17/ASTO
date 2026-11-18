<x-layouts.admin :title="$campaign->exists ? 'Ubah kampanye' : 'Kampanye baru'">
    @php
        $fmt = old('format', $campaign->format);
        $startVal = old('starts_at', $campaign->starts_at?->format('Y-m-d\TH:i'));
        $endVal = old('ends_at', $campaign->ends_at?->format('Y-m-d'));
    @endphp
    <form method="POST" enctype="multipart/form-data" action="{{ $campaign->exists ? route('admin.campaigns.update', $campaign) : route('admin.campaigns.store') }}"
          x-data="{ format: @js($fmt), preview: @js($campaign->exists ? $campaign->imageUrl() : null), title: @js(old('title', $campaign->title)),
                    pick(e) { const f = e.target.files[0]; if (f) { this.preview = URL.createObjectURL(f); } } }"
          class="grid gap-6 lg:grid-cols-[1fr_340px]">
        @csrf
        @if ($campaign->exists) @method('PUT') @endif

        <div class="space-y-5">
            <section class="a-card space-y-5 p-5">
                <div>
                    <label class="label">Format</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach (\App\Models\Campaign::FORMATS as $key => $f)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border p-3.5 transition" :class="format === '{{ $key }}' ? 'border-neon/60 bg-neon/[0.07]' : 'border-white/10 hover:border-white/20'">
                                <input type="radio" name="format" value="{{ $key }}" x-model="format" class="sr-only">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-white/[0.05]">
                                    <span class="rounded-sm border-2 border-current {{ $key === 'card' ? 'h-8 w-6' : 'h-4 w-8' }}" :class="format === '{{ $key }}' ? 'text-neon' : 'text-mist'"></span>
                                </span>
                                <span class="leading-tight">
                                    <span class="block text-sm font-semibold">{{ $f['label'] }}</span>
                                    <span class="text-xs text-mist">{{ $key === 'card' ? 'Deretan kartu di beranda' : 'Slider lebar di beranda' }} · {{ $f['width'] }}×{{ $f['height'] }} px</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="label" for="image">Gambar poster {{ $campaign->exists ? '(kosongkan bila tidak diganti)' : '' }}</label>
                    <label for="image" class="flex cursor-pointer items-center gap-4 rounded-2xl border border-dashed border-white/15 bg-white/[0.02] p-4 transition hover:border-neon/50">
                        <i class="ri-image-add-line text-3xl text-neon"></i>
                        <span class="text-sm">
                            <span class="block font-semibold">Pilih gambar JPG, PNG, atau WebP (maks 8 MB)</span>
                            <span class="text-mist">Bagian tengah gambar dipotong otomatis sesuai format. Teks penting letakkan di tengah.</span>
                        </span>
                    </label>
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" @change="pick($event)" class="sr-only">
                </div>

                <div>
                    <label class="label" for="title">Judul</label>
                    <input id="title" name="title" x-model="title" required maxlength="120" class="field" placeholder="Contoh: Bulan K3 Nasional 2027">
                    <p class="mt-1 text-xs text-mist">Dipakai sebagai teks alternatif gambar dan jawaban Mentari.</p>
                </div>
                <div>
                    <label class="label" for="subtitle">Keterangan singkat (opsional)</label>
                    <textarea id="subtitle" name="subtitle" rows="2" maxlength="200" class="field">{{ old('subtitle', $campaign->subtitle) }}</textarea>
                </div>
            </section>

            <section class="a-card grid gap-5 p-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="link_url">Tautan saat diketuk (opsional)</label>
                    <input id="link_url" name="link_url" value="{{ old('link_url', $campaign->link_url) }}" class="field" placeholder="https://… atau /aplikasi">
                    <p class="mt-1 text-xs text-mist">Kosongkan untuk membuka poster ukuran penuh beserta keterangannya.</p>
                </div>
                <div>
                    <label class="label" for="starts_at">Mulai tayang</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ $startVal }}" class="field">
                </div>
                <div>
                    <label class="label" for="ends_at">Tayang sampai (opsional)</label>
                    <input id="ends_at" name="ends_at" type="date" value="{{ $endVal }}" class="field">
                </div>
                <div>
                    <label class="label" for="sort_order">Urutan</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $campaign->sort_order ?? 0) }}" class="field">
                    <p class="mt-1 text-xs text-mist">Angka kecil tampil lebih dulu.</p>
                </div>
                <label class="flex items-center gap-3 self-center rounded-2xl bg-white/[0.03] p-3.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $campaign->is_active)) class="h-5 w-5 accent-[var(--color-neon)]">
                    <span><span class="block font-semibold">Aktif</span><span class="text-mist">Matikan untuk menyembunyikan tanpa menghapus</span></span>
                </label>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <button class="btn-neon"><i class="ri-save-3-line text-lg"></i> Simpan kampanye</button>
                <a href="{{ route('admin.campaigns.index') }}" class="btn-ghost">Batal</a>
                @if ($campaign->exists)
                    <span class="grow"></span>
                    <span class="text-xs text-mist">Dibuat {{ $campaign->creator?->displayName() ?? '–' }} · diubah {{ $campaign->editor?->displayName() ?? '–' }} {{ $campaign->updated_at?->locale('id')->diffForHumans() }}</span>
                @endif
            </div>
        </div>

        {{-- Pratinjau di HP --}}
        <aside class="lg:sticky lg:top-8 lg:self-start">
            <p class="label">Pratinjau di beranda</p>
            <div class="rounded-[34px] border border-white/10 bg-pit p-4 shadow-2xl">
                <div class="mb-3 h-1.5 w-16 rounded-full bg-white/10 mx-auto"></div>
                <p class="mb-2 font-display text-sm font-semibold">{{ \App\Models\Setting::get('campaign_section_title') }}</p>
                <div :class="format === 'banner' ? 'aspect-[2/1] w-full' : 'aspect-[4/5] w-[62%]'" class="overflow-hidden rounded-[18px] border border-white/10 bg-ridge transition-all">
                    <template x-if="preview"><img :src="preview" alt="" class="h-full w-full object-cover"></template>
                    <div x-show="!preview" class="grid h-full place-items-center p-4 text-center text-xs text-mist">Gambar akan tampil di sini</div>
                </div>
                <p class="mt-2 truncate text-xs text-mist" x-text="title || 'Judul kampanye'"></p>
            </div>
        </aside>
    </form>

    @if ($campaign->exists)
        <form method="POST" action="{{ route('admin.campaigns.destroy', $campaign) }}" class="mt-8 border-t border-white/[0.06] pt-5" onsubmit="return confirm('Hapus kampanye ini beserta gambarnya?')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-red-300 hover:text-red-200"><i class="ri-delete-bin-line text-lg"></i> Hapus kampanye</button>
        </form>
    @endif
</x-layouts.admin>
