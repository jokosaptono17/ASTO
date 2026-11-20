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
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (\App\Models\Campaign::FORMATS as $key => $f)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border p-3.5 transition" :class="format === '{{ $key }}' ? 'border-neon/60 bg-neon/[0.07]' : 'border-white/10 hover:border-white/20'">
                                <input type="radio" name="format" value="{{ $key }}" x-model="format" class="sr-only">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-white/[0.05]">
                                    <span class="rounded-sm border-2 border-current {{ ['card' => 'h-8 w-6', 'banner' => 'h-4 w-8', 'popup' => 'h-8 w-6 rounded-md shadow-[0_0_0_3px_rgba(255,255,255,.08)]'][$key] }}" :class="format === '{{ $key }}' ? 'text-neon' : 'text-mist'"></span>
                                </span>
                                <span class="leading-tight">
                                    <span class="block text-sm font-semibold">{{ $f['label'] }}</span>
                                    <span class="text-xs text-mist">{{ ['card' => 'Deretan kartu di beranda', 'banner' => 'Slider lebar di beranda', 'popup' => 'Muncul saat aplikasi dibuka'][$key] }} · {{ $f['width'] }}×{{ $f['height'] }} px</span>
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


            {{-- Pengaturan pop-up --}}
            <section x-show="format === 'popup'" x-cloak class="a-card space-y-5 p-5" x-data="{ seconds: {{ (int) old('popup_seconds', $campaign->popup_seconds ?? 3) }}, ack: @js((bool) old('popup_ack', $campaign->popup_ack)) }">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-neon/10 text-neon"><i class="ri-notification-badge-line text-xl"></i></span>
                    <div><h2 class="font-display text-lg font-semibold">Pengaturan pop-up</h2><p class="text-sm text-mist">Muncul di tengah layar saat karyawan membuka AstoOne.</p></div>
                </div>
                <div>
                    <p class="label">Seberapa sering muncul</p>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach (\App\Models\Campaign::POPUP_FREQUENCIES as $k => $f)
                            <label class="cursor-pointer">
                                <input type="radio" name="popup_frequency" value="{{ $k }}" @checked(old('popup_frequency', $campaign->popup_frequency ?? 'once') === $k) class="peer sr-only">
                                <span class="block h-full rounded-2xl border border-white/10 p-3 transition peer-checked:border-neon/60 peer-checked:bg-neon/[0.07]">
                                    <span class="block text-sm font-semibold">{{ $f['label'] }}</span><span class="text-xs text-mist">{{ $f['hint'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <div class="flex items-baseline justify-between">
                        <label class="label !mb-0" for="popup_seconds">Tombol tutup aktif setelah</label>
                        <span class="font-display text-lg font-bold text-neon"><span x-text="seconds"></span> detik</span>
                    </div>
                    <input id="popup_seconds" name="popup_seconds" type="range" min="0" max="15" x-model.number="seconds" class="mt-2 w-full accent-[var(--color-neon)]">
                    <p class="text-xs text-mist">Karyawan harus melihat pop-up minimal selama ini sebelum bisa menutupnya. 0 = bisa langsung ditutup.</p>
                </div>
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition" :class="ack ? 'border-hivis/50 bg-hivis/[0.07]' : 'border-white/10'">
                    <input type="checkbox" name="popup_ack" value="1" x-model="ack" class="mt-0.5 h-5 w-5 accent-[var(--color-hivis)]">
                    <span><span class="block font-semibold">Wajib dibaca</span>
                        <span class="text-sm text-mist">Pop-up hanya bisa ditutup dengan tombol <b class="text-fog">"Saya sudah membaca"</b> dan terus muncul sampai dikonfirmasi. Admin bisa melihat siapa yang belum membaca. Cocok untuk safety alert & info penting.</span></span>
                </label>
                <details class="group rounded-2xl border border-white/[0.07] px-4 py-3" @if (old('target_departments', $campaign->target_departments)) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-2 text-sm"><i class="ri-group-line text-neon"></i> Tampilkan untuk
                        <span class="text-mist">{{ $campaign->target_departments ? count($campaign->target_departments).' seksi' : 'semua karyawan' }}</span><i class="ri-arrow-down-s-line ml-auto transition group-open:rotate-180"></i></summary>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($departments as $d)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="target_departments[]" value="{{ $d }}" @checked(in_array($d, old('target_departments', $campaign->target_departments ?? []), true)) class="peer sr-only">
                                <span class="inline-flex rounded-full border border-white/10 px-2.5 py-1 text-xs text-fog transition peer-checked:border-neon/60 peer-checked:bg-neon/10 peer-checked:text-neon">{{ $d }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-mist">Tidak dicentang = semua karyawan.</p>
                </details>
            </section>

            <section class="a-card grid gap-5 p-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="link_url">Tautan saat diketuk (opsional)</label>
                    <input id="link_url" name="link_url" value="{{ old('link_url', $campaign->link_url) }}" class="field" placeholder="https://… atau /aplikasi">
                    <p class="mt-1 text-xs text-mist">Kosongkan untuk membuka poster ukuran penuh beserta keterangannya.</p>
                </div>
                <div class="sm:col-span-2" x-show="format === 'popup'" x-cloak>
                    <label class="label" for="link_label">Teks tombol (opsional)</label>
                    <input id="link_label" name="link_label" value="{{ old('link_label', $campaign->link_label) }}" maxlength="40" class="field" placeholder="Lihat detail">
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
        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <p class="label" x-text="format === 'popup' ? 'Pratinjau pop-up' : 'Pratinjau di beranda'">Pratinjau di beranda</p>
            {{-- Pratinjau pop-up --}}
            <div x-show="format === 'popup'" x-cloak class="relative overflow-hidden rounded-[34px] border border-white/10 bg-pit p-6 shadow-2xl">
                <div class="absolute inset-0 bg-black/60"></div>
                <div class="relative mx-auto w-[86%] overflow-hidden rounded-[22px] border border-neon/30 bg-bench shadow-[0_0_50px_-12px_rgba(61,255,142,.5)]">
                    <div class="relative aspect-[3/4] bg-ridge">
                        <template x-if="preview"><img :src="preview" alt="" class="h-full w-full object-cover"></template>
                        <div x-show="!preview" class="grid h-full place-items-center p-4 text-center text-xs text-mist">Gambar 3:4 akan tampil di sini</div>
                        <span class="absolute top-2 right-2 grid h-7 w-7 place-items-center rounded-full bg-black/60 text-white"><i class="ri-close-line"></i></span>
                    </div>
                    <div class="p-3"><p class="truncate text-sm font-semibold" x-text="title || 'Judul kampanye'"></p><div class="mt-2 h-8 rounded-xl bg-neon"></div></div>
                </div>
            </div>
            @if ($campaign->exists && $campaign->isPopup())
                <a href="{{ route('home', ['pratinjau-popup' => $campaign->id]) }}" target="_blank" class="btn-ghost w-full !min-h-10 text-sm"><i class="ri-eye-line"></i> Coba di beranda</a>
            @endif
            <div x-show="format !== 'popup'" class="rounded-[34px] border border-white/10 bg-pit p-4 shadow-2xl">
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

    @if ($stats)
        @php $base = max(1, $stats['audience']); $done = $campaign->popup_ack ? $stats['ack'] : $stats['seen']; @endphp
        <section class="mt-8 grid gap-4 lg:grid-cols-[1fr_1.4fr]">
            <div class="a-card p-5">
                <h2 class="font-display text-lg font-semibold">Jangkauan pop-up</h2>
                <div class="mt-4 flex items-center gap-5">
                    <div class="relative grid h-28 w-28 shrink-0 place-items-center">
                        <svg viewBox="0 0 36 36" class="absolute inset-0 -rotate-90"><circle cx="18" cy="18" r="15.5" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="3.5"/><circle cx="18" cy="18" r="15.5" fill="none" stroke="{{ $campaign->popup_ack ? '#ffc23d' : '#3dff8e' }}" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="97.4" stroke-dashoffset="{{ 97.4 * (1 - min(1, $done / $base)) }}"/></svg>
                        <span class="text-center leading-none"><span class="block font-display text-2xl font-bold">{{ round(min(1, $done / $base) * 100) }}%</span><span class="text-[11px] text-mist">{{ $campaign->popup_ack ? 'sudah baca' : 'sudah lihat' }}</span></span>
                    </div>
                    <dl class="grid grow grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-xs text-mist">Sasaran</dt><dd class="font-display text-xl font-bold">{{ $stats['audience'] }}</dd></div>
                        <div><dt class="text-xs text-mist">Sudah melihat</dt><dd class="font-display text-xl font-bold">{{ $stats['seen'] }}</dd></div>
                        <div><dt class="text-xs text-mist">{{ $campaign->popup_ack ? 'Konfirmasi baca' : 'Tidak ingin lagi' }}</dt><dd class="font-display text-xl font-bold">{{ $campaign->popup_ack ? $stats['ack'] : $stats['dismissed'] }}</dd></div>
                        <div><dt class="text-xs text-mist">Klik tombol</dt><dd class="font-display text-xl font-bold">{{ $stats['clicks'] }}</dd></div>
                    </dl>
                </div>
            </div>
            <div class="a-card p-5" x-data="{ copy() { navigator.clipboard?.writeText(@js(($campaign->popup_ack ? 'Belum konfirmasi baca: ' : 'Belum melihat: ').$campaign->title."\n".$stats['pending']->map(fn ($u) => '- '.$u->name.' ('.$u->nrp.')')->join("\n"))); $store.toast?.show('Daftar disalin') } }">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">{{ $campaign->popup_ack ? 'Belum konfirmasi baca' : 'Belum melihat' }} <span class="text-mist">({{ $stats['pending']->count() }}{{ $stats['pending']->count() >= 200 ? '+' : '' }})</span></h2>
                    @if ($stats['pending']->isNotEmpty()) <button type="button" @click="copy()" class="btn-ghost !min-h-9 text-sm"><i class="ri-whatsapp-line text-neon"></i> Salin untuk WA</button> @endif
                </div>
                <ul class="mt-3 max-h-72 divide-y divide-white/[0.05] overflow-y-auto text-sm">
                    @forelse ($stats['pending'] as $u)
                        <li class="flex items-center gap-3 py-2"><span class="grow truncate">{{ \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($u->name)) }}</span><span class="font-display text-xs text-mist">{{ $u->nrp }}</span><span class="w-36 truncate text-right text-xs text-mist">{{ $u->department }}</span></li>
                    @empty
                        <li class="py-6 text-center text-mist"><i class="ri-checkbox-circle-line text-2xl text-neon"></i><br>Semua sasaran sudah {{ $campaign->popup_ack ? 'membaca' : 'melihat' }}.</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-mist">Hanya karyawan yang sudah aktivasi akun.</p>
            </div>
        </section>
    @endif

    @if ($campaign->exists)
        <form method="POST" action="{{ route('admin.campaigns.destroy', $campaign) }}" class="mt-8 border-t border-white/[0.06] pt-5" onsubmit="return confirm('Hapus kampanye ini beserta gambarnya?')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-red-300 hover:text-red-200"><i class="ri-delete-bin-line text-lg"></i> Hapus kampanye</button>
        </form>
    @endif
</x-layouts.admin>
