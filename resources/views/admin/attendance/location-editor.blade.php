<x-layouts.admin :title="$location->exists ? 'Area '.$location->name : 'Area baru'">
    @php
        $others = \App\Models\AttendanceLocation::mapped()->where('id', '!=', $location->id ?? 0)->get()->map(fn ($l) => ['name' => $l->name, 'polygon' => $l->polygon])->values();
        $start = is_numeric(request('lat')) && is_numeric(request('lng')) ? [(float) request('lat'), (float) request('lng')] : null;
    @endphp
    <form method="POST" action="{{ $location->exists ? route('admin.attendance.locations.update', $location) : route('admin.attendance.locations.store') }}"
          x-data="areaEditor(@js($location->polygon ?? []), @js($others), {{ (int) old('buffer_m', $location->buffer_m) }}, @js($start))"
          class="grid gap-4 lg:grid-cols-[1fr_360px]">
        @csrf
        @if ($location->exists) @method('PUT') @endif
        <input type="hidden" name="polygon" :value="json">

        {{-- Peta --}}
        <div class="relative overflow-hidden rounded-3xl border border-white/[0.08] bg-pit">
            <div x-ref="map" class="h-[52vh] w-full lg:h-[calc(100vh-150px)]"></div>
            <div x-show="!mapReady" class="absolute inset-0 grid place-items-center text-sm text-mist">
                <span x-show="!mapError"><i class="ri-loader-4-line inline-block animate-spin"></i> Memuat peta…</span>
                <span x-show="mapError" x-cloak class="px-6 text-center">Peta tidak bisa dimuat (internet lemah). Titik tetap bisa direkam dari GPS.</span>
            </div>
            {{-- Status GPS --}}
            <div class="pointer-events-none absolute top-3 left-3 z-[500] flex flex-col items-start gap-2">
                <span class="pointer-events-auto chip backdrop-blur" :class="{ neon: 'bg-neon/20 text-neon', hivis: 'bg-hivis/20 text-hivis', alarm: 'bg-alarm/25 text-red-200', mist: 'bg-black/50 text-fog' }[quality.tone]">
                    <i class="ri-crosshair-2-line"></i> <span x-text="quality.label"></span>
                </span>
                <span x-show="walking" x-cloak class="chip bg-alarm/80 text-white"><span class="h-2 w-2 animate-pulse rounded-full bg-white"></span> Merekam jalur</span>
            </div>
            <div class="absolute right-3 bottom-7 z-[500] flex flex-col gap-2">
                <div class="flex overflow-hidden rounded-xl border border-white/10 bg-bench/90 text-xs font-semibold backdrop-blur">
                    <button type="button" @click="setLayer('satelit')" class="px-3 py-2" :class="layer === 'satelit' ? 'bg-neon text-[#04170d]' : 'text-fog'">Satelit</button>
                    <button type="button" @click="setLayer('jalan')" class="px-3 py-2" :class="layer === 'jalan' ? 'bg-neon text-[#04170d]' : 'text-fog'">Peta</button>
                </div>
                <button type="button" @click="locateMe()" class="ml-auto grid h-11 w-11 place-items-center rounded-xl border border-white/10 bg-bench/90 backdrop-blur" :class="follow ? 'text-neon' : 'text-fog'" aria-label="Ikuti posisi saya"><i class="ri-navigation-line text-xl"></i></button>
            </div>
        </div>

        {{-- Panel kontrol --}}
        <aside class="space-y-4">
            {{-- Lokasi saya sekarang --}}
            <section class="a-card p-4">
                <div class="flex items-center gap-3">
                    <span class="relative grid h-11 w-11 shrink-0 place-items-center rounded-xl" :class="{ neon: 'bg-neon/15 text-neon', hivis: 'bg-hivis/15 text-hivis', alarm: 'bg-alarm/15 text-alarm', mist: 'bg-white/[0.06] text-mist' }[quality.tone]">
                        <i class="ri-user-location-line text-2xl" :class="!me && 'animate-pulse'"></i>
                    </span>
                    <div class="min-w-0 grow leading-tight">
                        <p class="text-xs text-mist">Lokasi saya sekarang</p>
                        <p class="truncate font-display text-[15px] font-semibold" x-text="me ? coords : 'Mencari sinyal GPS…'"></p>
                        <p class="text-xs" :class="{ neon: 'text-neon', hivis: 'text-hivis', alarm: 'text-red-300', mist: 'text-mist' }[quality.tone]" x-text="me ? quality.label.replace(/^GPS /, 'Akurasi ') : ''"></p>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        <button type="button" @click="locateMe()" :disabled="!me" class="icon-btn h-9 w-9 disabled:opacity-40" aria-label="Pusatkan peta ke lokasi saya" title="Pusatkan peta"><i class="ri-focus-3-line"></i></button>
                        <button type="button" @click="copyCoords()" :disabled="!me" class="icon-btn h-9 w-9 disabled:opacity-40" aria-label="Salin koordinat" title="Salin koordinat"><i class="ri-file-copy-line"></i></button>
                    </div>
                </div>
                <template x-if="myVerdict">
                    <p class="mt-3 flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold" :class="myVerdict.tone === 'neon' ? 'bg-neon/10 text-neon' : 'bg-alarm/10 text-red-300'"><i class="ri-map-pin-user-line"></i> <span x-text="myVerdict.text"></span></p>
                </template>
                <p x-show="!me && markNote" x-cloak class="mt-2 text-xs text-hivis" x-text="markNote"></p>
            </section>

            <section class="a-card space-y-4 p-4">
                {{-- Tombol utama: tandai titik --}}
                <button type="button" @click="markHere()" :disabled="!me || marking || walking"
                        class="relative flex w-full items-center gap-4 overflow-hidden rounded-2xl bg-neon p-4 text-left text-[#04170d] transition disabled:opacity-40">
                    <span class="absolute inset-y-0 left-0 bg-black/15 transition-[width] duration-200" :style="`width: ${marking ? markProgress * 100 : 0}%`"></span>
                    <span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[#04170d]/15"><i class="text-3xl" :class="marking ? 'ri-loader-4-line animate-spin' : 'ri-map-pin-add-fill'"></i></span>
                    <span class="relative leading-tight">
                        <span class="block font-display text-lg font-bold" x-text="marking ? 'Mengunci posisi…' : 'Tandai titik di sini'"></span>
                        <span class="text-sm opacity-80" x-text="marking ? 'Diam sebentar (±5 detik)' : 'Berdiri di sudut area lalu tekan'"></span>
                    </span>
                </button>
                <button type="button" @click="toggleWalk()" :disabled="!me"
                        class="flex w-full items-center gap-3 rounded-2xl border p-3 text-left transition disabled:opacity-40" :class="walking ? 'border-alarm/60 bg-alarm/10' : 'border-white/10'">
                    <i class="text-2xl" :class="walking ? 'ri-stop-circle-fill text-alarm' : 'ri-walk-line text-neon'"></i>
                    <span class="leading-tight"><span class="block text-sm font-semibold" x-text="walking ? 'Selesai merekam' : 'Rekam sambil berjalan'"></span><span class="text-xs text-mist">Titik ditambah otomatis tiap 8 m</span></span>
                </button>
                <p x-show="markNote" x-cloak class="rounded-xl bg-white/[0.04] px-3 py-2 text-sm text-fog" x-text="markNote" aria-live="polite"></p>

                <div class="grid grid-cols-3 divide-x divide-white/[0.06] rounded-2xl bg-white/[0.03] text-center">
                    <div class="p-2.5"><p class="font-display text-xl font-bold" x-text="stats.n"></p><p class="text-[11px] text-mist">titik</p></div>
                    <div class="p-2.5"><p class="font-display text-xl font-bold" x-text="stats.area"></p><p class="text-[11px] text-mist">luas</p></div>
                    <div class="p-2.5"><p class="font-display text-xl font-bold" x-text="stats.perimeter"></p><p class="text-[11px] text-mist">keliling</p></div>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="undo()" :disabled="!points.length" class="btn-ghost !min-h-10 grow text-sm disabled:opacity-40"><i class="ri-arrow-go-back-line"></i> Urungkan</button>
                    <button type="button" @click="clearAll()" :disabled="!points.length" class="btn-ghost !min-h-10 grow text-sm text-red-300 disabled:opacity-40"><i class="ri-eraser-line"></i> Hapus semua</button>
                </div>
                <p class="text-xs text-mist"><i class="ri-information-line"></i> Geser angka di peta untuk merapikan titik. Ketuk peta untuk menambah titik manual.</p>
                @error('polygon') <p class="error">{{ $message }}</p> @enderror
            </section>

            <section class="a-card space-y-3 p-4">
                <div>
                    <label class="label" for="name">Nama area</label>
                    <input id="name" name="name" value="{{ old('name', $location->name) }}" required maxlength="60" class="field" placeholder="WS 25">
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-baseline justify-between">
                    <label class="label !mb-0" for="buffer_m">Toleransi di luar garis</label>
                    <span class="font-display text-lg font-bold text-neon"><span x-text="buffer"></span> m</span>
                </div>
                <input id="buffer_m" name="buffer_m" type="range" min="0" max="100" step="5" x-model.number="buffer" class="w-full accent-[var(--color-neon)]">
                <p class="text-xs text-mist">Karyawan yang berdiri sedikit di luar garis (mis. parkiran) tetap dihitung di area. Disarankan 20–30 m.</p>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->is_active ?? true)) class="h-4 w-4 accent-[var(--color-neon)]"> Area aktif</label>
            </section>

            <div class="flex gap-2">
                <button class="btn-neon grow" :disabled="points.length > 0 && points.length < 3"><i class="ri-save-3-line text-lg"></i> Simpan area</button>
                <a href="{{ route('admin.attendance.locations') }}" class="btn-ghost">Batal</a>
            </div>
            <p x-show="points.length > 0 && points.length < 3" x-cloak class="text-center text-xs text-hivis">Minimal 3 titik untuk membentuk area.</p>
        </aside>
    </form>

    @if ($location->exists)
        <form method="POST" action="{{ route('admin.attendance.locations.destroy', $location) }}" class="mt-6 border-t border-white/[0.06] pt-4" onsubmit="return confirm('Hapus area {{ $location->name }}?')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-red-300"><i class="ri-delete-bin-line text-lg"></i> Hapus area</button>
        </form>
    @endif
</x-layouts.admin>
