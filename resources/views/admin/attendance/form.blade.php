<x-layouts.admin :title="$session->exists ? 'Ubah sesi' : 'Sesi absensi baru'">
    @php
        $targets = old('target_departments', $session->target_departments ?? []);
        $areaIds = array_map('intval', old('location_ids', $session->location_ids ?? []));
    @endphp
    <form method="POST" action="{{ $session->exists ? route('admin.attendance.update', $session) : route('admin.attendance.store') }}" class="grid max-w-5xl gap-5 lg:grid-cols-[1fr_300px]"
          x-data="{ type: @js((string) old('attendance_type_id', $session->attendance_type_id)), mode: @js(old('checkin_mode', $session->checkin_mode)), start: @js(old('start_time', $session->starts_at?->format('H:i'))),
                    before: {{ (int) old('open_before', $open[0]) }}, after: {{ (int) old('open_after', $open[1]) }}, late: @js(old('late_after', $session->late_after)),
                    fmt(m) { if (!this.start) return '–'; let [h, i] = this.start.split(':').map(Number); let t = (h * 60 + i + m + 1440) % 1440; return String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0'); } }">
        @csrf
        @if ($session->exists) @method('PUT') @endif

        <div class="space-y-5">
            <section class="a-card space-y-5 p-5">
                <div>
                    <label class="label">Jenis kegiatan</label>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ($types as $t)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border p-3 transition" :class="type === '{{ $t->id }}' ? 'border-neon/60 bg-neon/[0.07]' : 'border-white/10 hover:border-white/20'">
                                <input type="radio" name="attendance_type_id" value="{{ $t->id }}" x-model="type" class="sr-only">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg" style="background: color-mix(in oklab, {{ $t->color }} 16%, transparent); color: {{ $t->color }}"><i class="{{ $t->icon }} text-lg"></i></span>
                                <span class="text-sm font-semibold">{{ $t->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="label" for="title">Topik / judul materi</label>
                    <input id="title" name="title" value="{{ old('title', $session->title) }}" maxlength="200" class="field" placeholder="Contoh: Bahaya pinch point saat ganti tyre">
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label class="label" for="date">Tanggal</label>
                        <input id="date" name="date" type="date" value="{{ old('date', $session->date?->format('Y-m-d')) }}" required class="field">
                    </div>
                    <div>
                        <label class="label" for="start_time">Jam mulai</label>
                        <input id="start_time" name="start_time" type="time" x-model="start" required class="field">
                    </div>
                    <div>
                        <label class="label" for="shift">Shift</label>
                        <select id="shift" name="shift" class="field">
                            <option value="">–</option>
                            @foreach (['1', '2'] as $sh) <option value="{{ $sh }}" @selected(old('shift', $session->shift) === $sh)>Shift {{ $sh }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="location">Lokasi</label>
                        <input id="location" name="location" list="loc-list" value="{{ old('location', $session->location) }}" maxlength="100" class="field" placeholder="WS 25, Quarry, Crusher…">
                        <datalist id="loc-list">@foreach (array_unique(array_merge($locations->all(), ['WS 25', 'Quarry', 'Crusher'])) as $l)<option value="{{ $l }}">@endforeach</datalist>
                    </div>
                    <div>
                        <label class="label" for="speaker_nrp">NRP pemateri (opsional)</label>
                        <input id="speaker_nrp" name="speaker_nrp" value="{{ old('speaker_nrp', $session->speaker_nrp) }}" maxlength="30" class="field font-display uppercase" placeholder="KT16026">
                    </div>
                </div>
            </section>

            <section class="a-card space-y-4 p-5">
                <div>
                    <h2 class="font-display font-semibold">Peserta</h2>
                    <p class="text-xs text-mist">Pilih seksi peserta agar daftar "belum hadir" bisa dihitung. Kosongkan untuk terbuka bagi semua.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($departments as $d)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="target_departments[]" value="{{ $d }}" @checked(in_array($d, $targets)) class="peer sr-only">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 px-3 py-1.5 text-sm text-fog transition peer-checked:border-neon/60 peer-checked:bg-neon/10 peer-checked:text-neon">
                                {{ $d }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="company">Perusahaan pelaksana</label>
                        <input id="company" name="company" value="{{ old('company', $session->company) }}" maxlength="100" class="field">
                    </div>
                    <div>
                        <label class="label" for="department">Departemen pelaksana</label>
                        <input id="department" name="department" value="{{ old('department', $session->department) }}" maxlength="100" class="field">
                    </div>
                </div>
            </section>

            <section class="a-card space-y-5 p-5">
                <h2 class="font-display font-semibold">Aturan absen</h2>
                <div>
                    <div class="grid gap-2 sm:grid-cols-3">
                @foreach (['gps' => ['QR tempel + GPS', 'Peserta absen dari QR absensi / beranda, posisi harus di area. Disarankan.', 'ri-map-pin-2-line'], 'qr' => ['QR layar + GPS', 'Ditambah QR di TV yang berganti tiap 30 detik. Paling ketat.', 'ri-qr-code-line'], 'open' => ['Tanpa cek lokasi', 'Untuk kegiatan di luar area (mis. online).', 'ri-smartphone-line']] as $k => [$label, $hint, $icon])
                        <label class="flex cursor-pointer gap-3 rounded-2xl border p-3.5 transition" :class="mode === '{{ $k }}' ? 'border-neon/60 bg-neon/[0.07]' : 'border-white/10'">
                            <input type="radio" name="checkin_mode" value="{{ $k }}" x-model="mode" class="sr-only">
                            <i class="{{ $icon }} text-2xl" :class="mode === '{{ $k }}' ? 'text-neon' : 'text-mist'"></i>
                            <span class="leading-tight"><span class="block text-sm font-semibold">{{ $label }}</span><span class="text-xs text-mist">{{ $hint }}</span></span>
                        </label>
                    @endforeach
                </div>
                </div>
                <div x-show="mode !== 'open'" x-cloak>
                    <p class="label">Area GPS yang sah</p>
                    @if ($areas->isEmpty())
                        <p class="text-sm text-mist">Belum ada area. @can('manage-content')<a href="{{ route('admin.attendance.locations') }}" class="text-neon">Petakan area</a>@endcan</p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($areas as $a)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="location_ids[]" value="{{ $a->id }}" @checked(in_array($a->id, $areaIds, true)) class="peer sr-only">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 px-3 py-1.5 text-sm text-fog transition peer-checked:border-neon/60 peer-checked:bg-neon/10 peer-checked:text-neon">
                                        <i class="ri-map-pin-2-line"></i>{{ $a->name }} @unless ($a->isMapped()) <span class="text-[11px] text-hivis">belum dipetakan</span> @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1.5 text-xs text-mist">Tidak dicentang = boleh dari semua area.</p>
                    @endif
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label class="label" for="open_before">Dibuka (menit sebelum)</label>
                        <input id="open_before" name="open_before" type="number" min="0" max="240" x-model.number="before" required class="field">
                    </div>
                    <div>
                        <label class="label" for="late_after">Terlambat setelah (menit)</label>
                        <input id="late_after" name="late_after" type="number" min="0" max="240" x-model="late" class="field" placeholder="Kosong = tidak dihitung">
                    </div>
                    <div>
                        <label class="label" for="open_after">Ditutup (menit setelah)</label>
                        <input id="open_after" name="open_after" type="number" min="5" max="720" x-model.number="after" required class="field">
                    </div>
                </div>
                <div>
                    <label class="label" for="notes">Catatan untuk peserta (opsional)</label>
                    <textarea id="notes" name="notes" rows="2" maxlength="1000" class="field">{{ old('notes', $session->notes) }}</textarea>
                </div>
            </section>

            <div class="flex gap-3">
                <button class="btn-neon"><i class="ri-qr-code-line text-lg"></i> {{ $session->exists ? 'Simpan sesi' : 'Buat sesi' }}</button>
                <a href="{{ $session->exists ? route('admin.attendance.show', $session) : route('admin.attendance.index') }}" class="btn-ghost">Batal</a>
            </div>
        </div>

        {{-- Garis waktu --}}
        <aside class="lg:sticky lg:top-8 lg:self-start">
            <p class="label">Garis waktu absen</p>
            <div class="a-card p-5">
                <ol class="relative space-y-5 border-l border-white/10 pl-5">
                    <li><span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-neon"></span><p class="font-display text-lg font-semibold" x-text="fmt(-before)"></p><p class="text-xs text-mist">Absen dibuka</p></li>
                    <li><span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-ink"></span><p class="font-display text-lg font-semibold" x-text="fmt(0)"></p><p class="text-xs text-mist">Kegiatan mulai</p></li>
                    <li x-show="late !== '' && late !== null"><span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-hivis"></span><p class="font-display text-lg font-semibold" x-text="fmt(Number(late))"></p><p class="text-xs text-mist">Setelah ini dihitung terlambat</p></li>
                    <li><span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-alarm"></span><p class="font-display text-lg font-semibold" x-text="fmt(after)"></p><p class="text-xs text-mist">Absen ditutup otomatis</p></li>
                </ol>
            </div>
        </aside>
    </form>

    @if ($session->exists)
        <form method="POST" action="{{ route('admin.attendance.destroy', $session) }}" class="mt-8 max-w-5xl border-t border-white/[0.06] pt-5" onsubmit="return confirm('Hapus sesi ini beserta daftar hadirnya?')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-red-300"><i class="ri-delete-bin-line text-lg"></i> Hapus sesi</button>
        </form>
    @endif
</x-layouts.admin>
