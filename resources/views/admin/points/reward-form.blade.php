<x-layouts.admin :title="$reward->exists ? 'Ubah hadiah' : 'Tambah hadiah'">
    @include('admin.points._tabs')
    <form method="POST" enctype="multipart/form-data" action="{{ $reward->exists ? route('admin.points.rewards.update', $reward) : route('admin.points.rewards.store') }}"
          x-data="{ preview: @js($reward->imageUrl()), name: @js(old('name', $reward->name)), cost: {{ (int) old('cost', $reward->cost) }}, cat: @js(old('category', $reward->category)), icons: @js(collect(\App\Models\Reward::CATEGORIES)->map(fn ($c) => $c['icon'])),
                    pick(e) { const f = e.target.files[0]; if (f) this.preview = URL.createObjectURL(f) } }"
          class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        @csrf
        @if ($reward->exists) @method('PUT') @endif
        <div class="space-y-5">
            <section class="a-card space-y-4 p-5">
                <div>
                    <label class="label" for="name">Nama hadiah</label>
                    <input id="name" name="name" x-model="name" required maxlength="100" class="field" placeholder="Contoh: Tumbler ASTO 600 ml">
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <p class="label">Kategori</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Models\Reward::CATEGORIES as $k => $c)
                            <label class="cursor-pointer"><input type="radio" name="category" value="{{ $k }}" x-model="cat" class="peer sr-only">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 px-3 py-1.5 text-sm text-fog transition peer-checked:border-hivis/60 peer-checked:bg-hivis/10 peer-checked:text-hivis"><i class="{{ $c['icon'] }}"></i>{{ $c['label'] }}</span></label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="label" for="description">Deskripsi (opsional)</label>
                    <textarea id="description" name="description" rows="3" maxlength="1000" class="field" placeholder="Ukuran, warna, masa berlaku voucher, dll.">{{ old('description', $reward->description) }}</textarea>
                </div>
                <div>
                    <label class="label" for="image">Foto (opsional)</label>
                    <label for="image" class="flex cursor-pointer items-center gap-4 rounded-2xl border border-dashed border-white/15 bg-white/[0.02] p-4 transition hover:border-hivis/50">
                        <i class="ri-image-add-line text-3xl text-hivis"></i>
                        <span class="text-sm"><span class="block font-semibold">Pilih foto JPG/PNG/WebP</span><span class="text-mist">Dipotong persegi 800×800 px</span></span>
                    </label>
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" @change="pick($event)" class="sr-only">
                    @error('image') <p class="error">{{ $message }}</p> @enderror
                </div>
            </section>

            <section class="a-card grid gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="cost">Harga (poin)</label>
                    <input id="cost" name="cost" type="number" min="1" x-model.number="cost" required class="field font-display text-lg font-bold">
                    @error('cost') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="stock">Stok</label>
                    <input id="stock" name="stock" type="number" min="0" value="{{ old('stock', $reward->stock) }}" class="field" placeholder="∞ tidak terbatas">
                </div>
                <div>
                    <label class="label" for="per_user_limit">Batas per karyawan</label>
                    <input id="per_user_limit" name="per_user_limit" type="number" min="1" value="{{ old('per_user_limit', $reward->per_user_limit) }}" class="field" placeholder="Tanpa batas">
                </div>
                <div class="sm:col-span-3">
                    <label class="label" for="pickup_note">Cara mengambil</label>
                    <input id="pickup_note" name="pickup_note" maxlength="200" value="{{ old('pickup_note', $reward->pickup_note) }}" class="field" placeholder="Contoh: Ambil di ruang admin Plant WS 25, Senin–Jumat 08.00–16.00">
                </div>
                <div>
                    <label class="label" for="sort_order">Urutan</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $reward->sort_order ?? 0) }}" class="field">
                </div>
                <label class="flex items-center gap-3 self-end rounded-2xl bg-white/[0.03] p-3.5 text-sm sm:col-span-2">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $reward->is_active)) class="h-5 w-5 accent-[var(--color-neon)]">
                    <span><span class="block font-semibold">Tampil di katalog</span><span class="text-mist">Matikan untuk menyembunyikan sementara</span></span>
                </label>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <button class="btn-neon"><i class="ri-save-3-line text-lg"></i> Simpan hadiah</button>
                <a href="{{ route('admin.points.rewards.index') }}" class="btn-ghost">Batal</a>
            </div>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <p class="label">Pratinjau di katalog</p>
            <div class="mx-auto w-56 overflow-hidden rounded-2xl border border-white/[0.07] bg-bench shadow-2xl">
                <div class="aspect-square bg-ridge">
                    <template x-if="preview"><img :src="preview" alt="" class="h-full w-full object-cover"></template>
                    <template x-if="!preview"><span class="grid h-full place-items-center text-5xl text-hivis/60"><i :class="icons[cat]"></i></span></template>
                </div>
                <div class="p-3">
                    <p class="line-clamp-2 text-sm font-semibold" x-text="name || 'Nama hadiah'"></p>
                    <p class="mt-1.5 flex items-center gap-1.5 font-display text-lg font-bold text-hivis"><x-coin :size="18" /><span x-text="Number(cost || 0).toLocaleString('id-ID')"></span></p>
                </div>
            </div>
        </aside>
    </form>

    @if ($reward->exists)
        <form method="POST" action="{{ route('admin.points.rewards.destroy', $reward) }}" class="mt-8 border-t border-white/[0.06] pt-5" onsubmit="return confirm('Hapus hadiah ini? Bila sudah pernah ditukar, hadiah dinonaktifkan.')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-red-300"><i class="ri-delete-bin-line text-lg"></i> Hapus hadiah</button>
        </form>
    @endif
</x-layouts.admin>
