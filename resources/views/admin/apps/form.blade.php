<x-layouts.admin :title="$app->exists ? 'Ubah aplikasi' : 'Tambah aplikasi'">
    @php
        $icons = ['ri-graduation-cap-fill', 'ri-book-3-fill', 'ri-book-open-fill', 'ri-file-list-3-fill', 'ri-award-fill', 'ri-medal-fill', 'ri-shield-user-fill', 'ri-shield-check-fill', 'ri-first-aid-kit-fill', 'ri-heart-pulse-fill', 'ri-alarm-warning-fill', 'ri-tools-fill', 'ri-hammer-fill', 'ri-settings-5-fill', 'ri-truck-fill', 'ri-steering-2-fill', 'ri-car-fill', 'ri-oil-fill', 'ri-gas-station-fill', 'ri-flashlight-fill', 'ri-cpu-fill', 'ri-router-fill', 'ri-signal-tower-fill', 'ri-bar-chart-box-fill', 'ri-line-chart-fill', 'ri-pie-chart-2-fill', 'ri-dashboard-3-fill', 'ri-database-2-fill', 'ri-folder-shared-fill', 'ri-cloud-fill', 'ri-drive-fill', 'ri-id-card-fill', 'ri-qr-code-fill', 'ri-calendar-check-fill', 'ri-time-fill', 'ri-team-fill', 'ri-user-star-fill', 'ri-lightbulb-flash-fill', 'ri-trophy-fill', 'ri-camera-fill', 'ri-map-pin-fill', 'ri-global-fill', 'ri-apps-2-fill', 'ri-chat-3-fill', 'ri-mail-fill', 'ri-whatsapp-fill'];
        $colors = ['#3BFF8A', '#4ADE80', '#2DD4BF', '#22D3EE', '#38BDF8', '#60A5FA', '#818CF8', '#A78BFA', '#F472B6', '#FB7185', '#FB923C', '#FACC15', '#FDE047', '#E5E7EB'];
    @endphp
    <form method="POST" enctype="multipart/form-data" action="{{ $app->exists ? route('admin.apps.update', $app) : route('admin.apps.store') }}"
          x-data="{ name: @js(old('name', $app->name)), icon: @js(old('icon', $app->icon_type === 'remix' ? $app->icon : 'ri-apps-2-fill')), color: @js(old('color', $app->color)),
                    type: @js(old('icon_type', $app->icon_type)), image: @js($app->icon_type === 'image' ? $app->iconUrl() : null), placement: @js(old('placement', $app->placement)),
                    pick(e) { const f = e.target.files[0]; if (f) { this.image = URL.createObjectURL(f); this.type = 'image'; } } }"
          class="grid gap-6 lg:grid-cols-[1fr_300px]">
        @csrf
        @if ($app->exists) @method('PUT') @endif

        <div class="space-y-5">
            <section class="a-card grid gap-5 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Nama</label>
                    <input id="name" name="name" x-model="name" required maxlength="60" class="field" placeholder="Contoh: Mekanik Pintar">
                    <p class="mt-1 text-xs text-mist">Singkat saja (1–2 kata) agar rapi di beranda.</p>
                </div>
                <div>
                    <label class="label" for="category_id">Kategori</label>
                    <select id="category_id" name="category_id" class="field">
                        <option value="">Tanpa kategori</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $app->category_id) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="url">Alamat (URL)</label>
                    <input id="url" name="url" type="url" value="{{ old('url', $app->url) }}" required class="field" placeholder="https://…">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="tagline">Keterangan</label>
                    <input id="tagline" name="tagline" value="{{ old('tagline', $app->tagline) }}" maxlength="160" class="field" placeholder="Apa fungsi aplikasi ini? Dipakai juga untuk pencarian & Mentari">
                </div>
            </section>

            <section class="a-card grid gap-5 p-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <h2 class="font-display font-semibold">Pengetahuan untuk Mentari</h2>
                    <p class="text-xs text-mist">Dipakai Mentari saat ada yang bertanya "apa itu {{ $app->name ?: 'aplikasi ini' }}?", "cara pakai …?", atau "siapa admin …?".</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="description">Penjelasan (apa itu)</label>
                    <textarea id="description" name="description" rows="3" maxlength="3000" class="field" placeholder="Jelaskan fungsi aplikasi ini dengan bahasa sederhana">{{ old('description', $app->description) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="howto">Cara pakai / login</label>
                    <textarea id="howto" name="howto" rows="4" maxlength="3000" class="field" placeholder="1. Buka …&#10;2. Login dengan …&#10;3. …">{{ old('howto', $app->howto) }}</textarea>
                </div>
                <div>
                    <label class="label" for="pic_name">PIC / admin aplikasi</label>
                    <input id="pic_name" name="pic_name" value="{{ old('pic_name', $app->pic_name) }}" maxlength="100" class="field" placeholder="Nama PIC">
                </div>
                <div>
                    <label class="label" for="pic_phone">WhatsApp PIC</label>
                    <input id="pic_phone" name="pic_phone" value="{{ old('pic_phone', $app->pic_phone) }}" inputmode="tel" class="field" placeholder="08…">
                </div>
            </section>

            <section class="a-card space-y-4 p-5">
                <div class="flex gap-2">
                    <button type="button" @click="type = 'remix'" :class="type === 'remix' ? 'bg-neon text-[#04170d]' : 'bg-white/[0.05] text-fog'" class="rounded-full px-4 py-1.5 text-sm font-semibold">Pilih ikon</button>
                    <label :class="type === 'image' ? 'bg-neon text-[#04170d]' : 'bg-white/[0.05] text-fog'" class="cursor-pointer rounded-full px-4 py-1.5 text-sm font-semibold">
                        Unggah logo<input type="file" name="icon_file" accept="image/png,image/jpeg,image/webp,image/svg+xml" @change="pick($event)" class="sr-only">
                    </label>
                    <input type="hidden" name="icon_type" :value="type">
                </div>
                <div x-show="type === 'remix'">
                    <div class="grid grid-cols-8 gap-1.5 sm:grid-cols-12">
                        @foreach ($icons as $ic)
                            <button type="button" @click="icon = '{{ $ic }}'" :class="icon === '{{ $ic }}' ? 'bg-neon/15 text-neon ring-1 ring-neon/60' : 'text-fog hover:bg-white/[0.06]'"
                                    class="grid aspect-square place-items-center rounded-xl text-xl" title="{{ $ic }}"><i class="{{ $ic }}"></i></button>
                        @endforeach
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <input name="icon" x-model="icon" class="field !py-2 font-mono text-sm" pattern="ri-[a-z0-9-]+">
                        <a href="https://remixicon.com" target="_blank" rel="noopener" class="shrink-0 text-xs text-neon-soft">Ikon lain di remixicon.com</a>
                    </div>
                </div>
                <p x-show="type === 'image'" x-cloak class="text-sm text-mist">PNG/SVG transparan paling bagus. Logo diperkecil otomatis ke 256 px.</p>
                <div>
                    <label class="label">Warna</label>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($colors as $c)
                            <button type="button" @click="color = '{{ $c }}'" class="h-8 w-8 rounded-full ring-offset-2 ring-offset-bench transition" :class="color.toUpperCase() === '{{ $c }}' && 'ring-2 ring-white'" style="background: {{ $c }}" aria-label="Warna {{ $c }}"></button>
                        @endforeach
                        <input type="color" x-model="color" class="h-8 w-10 cursor-pointer rounded-lg border border-white/10 bg-transparent" aria-label="Warna lain">
                        <input type="hidden" name="color" :value="color">
                    </div>
                </div>
            </section>

            <section class="a-card grid gap-5 p-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label">Tampil di</label>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach (\App\Models\AppLink::PLACEMENTS as $key => $label)
                            <label class="cursor-pointer rounded-xl border px-3 py-2.5 text-sm transition" :class="placement === '{{ $key }}' ? 'border-neon/60 bg-neon/[0.07] text-ink' : 'border-white/10 text-fog'">
                                <input type="radio" name="placement" value="{{ $key }}" x-model="placement" class="sr-only">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="label" for="badge">Label kecil (opsional)</label>
                    <input id="badge" name="badge" value="{{ old('badge', $app->badge) }}" maxlength="20" class="field" placeholder="Baru">
                </div>
                <div>
                    <label class="label" for="sort_order">Urutan</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $app->sort_order) }}" class="field">
                </div>
                @foreach (['is_active' => ['Aktif', 'Tampil di portal'], 'new_tab' => ['Buka di tab baru', 'Portal tetap terbuka di belakang'], 'health_check' => ['Cek status otomatis', 'Lampu online/gangguan tiap 10 menit']] as $field => [$t, $d])
                    <label class="flex items-center gap-3 rounded-2xl bg-white/[0.03] p-3.5 text-sm">
                        <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $app->{$field})) class="h-5 w-5 accent-[var(--color-neon)]">
                        <span><span class="block font-semibold">{{ $t }}</span><span class="text-mist">{{ $d }}</span></span>
                    </label>
                @endforeach
            </section>

            <div class="flex gap-3">
                <button class="btn-neon"><i class="ri-save-3-line text-lg"></i> Simpan aplikasi</button>
                <a href="{{ route('admin.apps.index') }}" class="btn-ghost">Batal</a>
            </div>
        </div>

        <aside class="lg:sticky lg:top-8 lg:self-start">
            <p class="label">Pratinjau</p>
            <div class="a-card flex flex-col items-center gap-5 p-6">
                <div class="flex flex-col items-center gap-2">
                    <span :class="placement === 'featured' ? 'app-orb h-[58px] w-[58px]' : 'app-chip h-[46px] w-[46px]'" :style="`--app: ${color}`">
                        <i x-show="type === 'remix'" :class="icon" :style="`font-size: ${placement === 'featured' ? 27 : 23}px`"></i>
                        <img x-show="type === 'image' && image" :src="image" alt="" class="h-[62%] w-[62%] object-contain">
                    </span>
                    <span class="text-xs text-fog" x-text="name || 'Nama aplikasi'"></span>
                </div>
            </div>
        </aside>
    </form>

    @if ($app->exists)
        <form method="POST" action="{{ route('admin.apps.destroy', $app) }}" class="mt-8 border-t border-white/[0.06] pt-5" onsubmit="return confirm('Hapus {{ $app->name }} dari portal?')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-red-300"><i class="ri-delete-bin-line text-lg"></i> Hapus aplikasi</button>
        </form>
    @endif
</x-layouts.admin>
