<x-layouts.admin :title="$exam->exists ? 'Ubah ujian' : 'Buat ujian'" :subtitle="$exam->exists ? $exam->title : 'Atur soal, jadwal, dan peserta yang wajib mengerjakan'">
    @include('admin.exams._tabs')
    @php $dt = fn ($d) => $d ? $d->format('Y-m-d\TH:i') : ''; @endphp

    <form method="POST" action="{{ $exam->exists ? route('admin.exams.update', $exam) : route('admin.exams.store') }}"
          x-data="examForm(@js(['sets' => $sets, 'participants' => $participants, 'audience' => old('audience', $exam->audience),  'code' => old('code', $suggested), 'codeTouched' => $exam->exists || old('code') !== null, 'duration' => (int) old('duration_minutes', $exam->duration_minutes), 'peopleUrl' => route('admin.exams.people')]))"
          class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        @if ($exam->exists) @method('PUT') @endif

        <div class="min-w-0 space-y-5">
            {{-- 1. Identitas --}}
            <section class="a-card space-y-4 p-5">
                <h2 class="flex items-center gap-2 font-display text-lg font-semibold"><span class="grid h-7 w-7 place-items-center rounded-lg bg-neon/15 text-sm text-neon">1</span> Ujian</h2>
                <div>
                    <label class="label" for="title">Nama ujian</label>
                    <input id="title" name="title" value="{{ old('title', $exam->title) }}" @input.debounce.300ms="fromTitle($event.target.value)" required maxlength="150" class="field" placeholder="Contoh: Seleksi Best Mekanik 2026 — Tahap 1">
                </div>
                <div>
                    <label class="label" for="category">Kategori <span class="font-normal text-mist">(opsional)</span></label>
                    <input id="category" name="category" value="{{ old('category', $exam->category) }}" list="exam-categories" maxlength="60" class="field" placeholder="Pilih atau ketik, misalnya Best Mekanik" autocomplete="off">
                    <datalist id="exam-categories">@foreach ($categories as $c)<option value="{{ $c }}"></option>@endforeach</datalist>
                    <p class="mt-1.5 text-xs text-mist">Mengelompokkan event di daftar & ringkasan per kategori. Pakai nama yang sama untuk event sejenis.</p>
                </div>
                <div>
                    <label class="label" for="description">Keterangan untuk peserta (opsional)</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000" class="field" placeholder="Materi yang diujikan, lokasi ruang ujian, atau pesan panitia">{{ old('description', $exam->description) }}</textarea>
                </div>
                <div>
                    <label class="label" for="code">Kode ujian</label>
                    <div class="flex gap-2">
                        <input id="code" name="code" x-model="code" @input="cleanCode()" required maxlength="30" class="field font-display text-lg tracking-[.2em] uppercase" autocomplete="off">
                        <button type="button" @click="newCode()" class="btn-ghost !min-h-[50px] shrink-0 text-sm" title="Buat kode acak"><i class="ri-shuffle-line text-lg"></i> Acak</button>
                    </div>
                    <p class="mt-1.5 text-xs text-mist">Satu kode untuk semua peserta. Umumkan saat ujian dimulai agar tidak ada yang mulai lebih dulu dari tempat lain.</p>
                </div>
                <fieldset>
                    <legend class="label">Siapa yang boleh ikut</legend>
                    <div class="grid gap-2 md:grid-cols-2">
                        @foreach (['assigned' => ['Peserta yang ditunjuk', 'Hanya kandidat yang dipilih; mereka mendapat pemberitahuan di portal.', 'ri-user-star-line'], 'open' => ['Siapa saja yang punya kode', 'Untuk pre/post test kelas. Peserta ditunjuk tetap mendapat pemberitahuan.', 'ri-door-open-line']] as $k => [$l, $h, $ic])
                            <label class="flex cursor-pointer gap-3 rounded-2xl border p-3.5 transition" :class="audience === '{{ $k }}' ? 'border-neon/60 bg-neon/[0.07]' : 'border-white/10 hover:border-white/20'">
                                <input type="radio" name="audience" value="{{ $k }}" x-model="audience" class="sr-only">
                                <i class="{{ $ic }} text-2xl" :class="audience === '{{ $k }}' ? 'text-neon' : 'text-mist'"></i>
                                <span><span class="block font-semibold">{{ $l }}</span><span class="text-sm text-mist">{{ $h }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </section>

            {{-- 2. Soal --}}
            <section class="a-card overflow-hidden">
                <div class="flex flex-wrap items-center gap-3 border-b border-white/[0.06] p-5">
                    <h2 class="flex grow items-center gap-2 font-display text-lg font-semibold"><span class="grid h-7 w-7 place-items-center rounded-lg bg-neon/15 text-sm text-neon">2</span> Paket soal <span class="text-mist" x-text="`· ${total} soal per peserta`"></span></h2>
                    <a href="{{ route('admin.exams.bank.import') }}" class="text-sm font-semibold text-neon hover:underline" target="_blank"><i class="ri-file-excel-2-line"></i> Impor soal</a>
                </div>
                @if ($sets->isEmpty())
                    <p class="p-6 text-center text-mist">Bank soal masih kosong. <a href="{{ route('admin.exams.bank.import') }}" class="font-semibold text-neon">Impor dari Excel</a> dulu.</p>
                @else
                    <div class="border-b border-white/[0.06] px-5 py-3">
                        <div class="relative"><i class="ri-search-line pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-mist"></i><input x-model="setQ" type="search" placeholder="Cari paket…" class="field !py-2 !pl-10 text-sm"></div>
                    </div>
                    <ul class="max-h-[380px] divide-y divide-white/[0.05] overflow-y-auto">
                        <template x-for="s in visibleSets" :key="s.id">
                            <li class="flex items-center gap-3 px-5 py-2.5" :class="s.on && 'bg-neon/[0.04]'">
                                <label class="flex min-w-0 grow cursor-pointer items-center gap-3">
                                    <input type="checkbox" x-model="s.on" class="h-5 w-5 shrink-0 accent-[#3dff8e]">
                                    <span class="min-w-0"><span class="block truncate font-medium" x-text="s.name"></span><span class="text-xs text-mist" x-text="`${s.count} soal aktif`"></span></span>
                                </label>
                                <label x-show="s.on" class="flex shrink-0 items-center gap-2 text-sm text-mist">ambil
                                    <input type="number" min="1" :max="s.count" x-model.number="s.take" placeholder="semua" class="field !w-24 !py-1.5 text-center font-display">
                                    acak
                                </label>
                            </li>
                        </template>
                    </ul>
                    <template x-for="(s, n) in chosen" :key="s.id">
                        <span><input type="hidden" :name="`sets[${n}][id]`" :value="s.id"><input type="hidden" :name="`sets[${n}][take]`" :value="s.take || ''"></span>
                    </template>
                @endif
                <div class="flex flex-wrap gap-x-6 gap-y-2 border-t border-white/[0.06] px-5 py-3.5 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="shuffle_questions" value="1" @checked(old('shuffle_questions', $exam->shuffle_questions)) class="h-4 w-4 accent-[#3dff8e]"> Acak urutan soal tiap peserta</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="shuffle_options" value="1" @checked(old('shuffle_options', $exam->shuffle_options)) class="h-4 w-4 accent-[#3dff8e]"> Acak urutan pilihan A–D</label>
                </div>
            </section>

            {{-- 3. Jadwal & aturan --}}
            <section class="a-card space-y-4 p-5">
                <h2 class="flex items-center gap-2 font-display text-lg font-semibold"><span class="grid h-7 w-7 place-items-center rounded-lg bg-neon/15 text-sm text-neon">3</span> Jadwal & aturan</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div><label class="label" for="opens_at">Dibuka</label><input id="opens_at" type="datetime-local" name="opens_at" value="{{ old('opens_at', $dt($exam->opens_at)) }}" class="field"></div>
                    <div><label class="label" for="closes_at">Ditutup</label><input id="closes_at" type="datetime-local" name="closes_at" value="{{ old('closes_at', $dt($exam->closes_at)) }}" class="field"></div>
                </div>
                <p class="-mt-2 text-xs text-mist">Kosongkan bila ingin dibuka/ditutup manual dari halaman pantau. Peserta yang mulai mendekati waktu tutup mendapat sisa waktu sampai jam tutup.</p>
                <div>
                    <label class="label" for="duration_minutes">Durasi per peserta</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative w-32"><input id="duration_minutes" type="number" name="duration_minutes" x-model.number="duration" min="1" max="600" required class="field pr-14 font-display text-lg"><span class="absolute top-1/2 right-4 -translate-y-1/2 text-sm text-mist">mnt</span></div>
                        @foreach ([30, 45, 60, 90, 120] as $m)
                            <button type="button" @click="duration = {{ $m }}" class="rounded-full px-3 py-1.5 text-sm font-semibold transition" :class="duration === {{ $m }} ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog hover:bg-white/10'">{{ $m }}</button>
                        @endforeach
                        <span x-show="total" class="text-sm text-mist" x-text="`≈ ${perQuestion} detik per soal`"></span>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div><label class="label" for="pass_score">Nilai lulus</label><input id="pass_score" type="number" name="pass_score" min="0" max="100" value="{{ old('pass_score', $exam->pass_score) }}" required class="field font-display text-lg"></div>
                    <div><label class="label" for="max_attempts">Kesempatan mengerjakan</label><input id="max_attempts" type="number" name="max_attempts" min="1" max="10" value="{{ old('max_attempts', $exam->max_attempts) }}" required class="field font-display text-lg"></div>
                    <div><label class="label" for="max_violations">Batas keluar halaman</label><input id="max_violations" type="number" name="max_violations" min="0" max="50" value="{{ old('max_violations', $exam->max_violations) }}" required class="field font-display text-lg"><p class="mt-1 text-xs text-mist">0 = hanya dicatat</p></div>
                </div>
                <fieldset>
                    <legend class="label">Hasil untuk peserta</legend>
                    <div class="grid gap-2 md:grid-cols-3">
                        @foreach (\App\Models\Exam::RESULT_MODES as $k => $m)
                            <label class="flex cursor-pointer flex-col gap-1 rounded-2xl border border-white/10 p-3.5 transition has-[:checked]:border-neon/60 has-[:checked]:bg-neon/[0.07]">
                                <span class="flex items-center gap-2 font-semibold"><input type="radio" name="result_mode" value="{{ $k }}" @checked(old('result_mode', $exam->result_mode) === $k) class="accent-[#3dff8e]"> {{ $m['label'] }}</span>
                                <span class="text-xs leading-relaxed text-mist">{{ $m['hint'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </section>

            {{-- 4. Peserta --}}
            <section class="a-card overflow-hidden">
                <div class="flex flex-wrap items-center gap-3 border-b border-white/[0.06] p-5">
                    <h2 class="flex grow items-center gap-2 font-display text-lg font-semibold"><span class="grid h-7 w-7 place-items-center rounded-lg bg-neon/15 text-sm text-neon">4</span> Peserta <span class="text-mist" x-text="`(${people.length})`"></span></h2>
                    <label class="flex items-center gap-2 text-sm text-fog">Kelompok
                        <input x-model="label" list="label-list" maxlength="60" placeholder="mis. Kandidat Best Mekanik" class="field !w-56 !py-1.5 text-sm">
                    </label>
                    <datalist id="label-list"><option value="Kandidat Best Mekanik"><option value="Kandidat Best GL"><option value="Kandidat Best Operator"><option value="Peserta training"></datalist>
                    <button type="button" x-show="label && people.some((p) => !p.label)" @click="applyLabel()" class="text-sm font-semibold text-neon">Terapkan ke yang kosong</button>
                </div>
                <div class="grid gap-3 border-b border-white/[0.06] p-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <div class="relative md:col-span-3">
                        <i class="ri-user-search-line pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-lg text-mist"></i>
                        <input x-model="q" @input.debounce.250ms="search()" @keydown.enter.prevent="results[0] && add(results[0])" type="search" placeholder="Cari nama atau NRP…" class="field !pl-11" autocomplete="off">
                        <ul x-show="results.length" x-cloak class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-xl border border-white/10 bg-ridge shadow-2xl">
                            <template x-for="p in results" :key="p.id">
                                <li><button type="button" @click="add(p)" class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-white/[0.06]">
                                    <i class="ri-add-circle-line text-neon"></i><span class="grow"><span class="font-medium" x-text="p.name"></span> <span class="font-display text-xs text-mist" x-text="p.nrp"></span></span><span class="text-xs text-mist" x-text="p.position || p.department"></span>
                                </button></li>
                            </template>
                        </ul>
                    </div>
                    <select @change="addGroup('department', $event.target.value); $event.target.value = ''" class="field"><option value="">+ Satu seksi…</option>@foreach ($departments as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach</select>
                    <select @change="addGroup('position', $event.target.value); $event.target.value = ''" class="field"><option value="">+ Satu jabatan…</option>@foreach ($positions as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select>
                    <button type="button" @click="paste = !paste" class="btn-ghost !min-h-[46px] text-sm"><i class="ri-file-excel-2-line text-neon"></i> Tempel NRP</button>
                    <div x-show="paste" x-cloak class="md:col-span-3">
                        <textarea x-model="pasteText" rows="4" class="field font-display text-sm" placeholder="KT16042	Kandidat Best Mekanik&#10;KT17163	Kandidat Best GL"></textarea>
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <button type="button" @click="importPaste()" class="btn-neon !min-h-10 text-sm"><i class="ri-download-2-line"></i> Tambahkan</button>
                            <p class="text-xs text-mist">Salin kolom <b>NRP</b> dan (opsional) <b>kelompok</b> dari Excel.</p>
                            <p x-show="pasteMsg" class="w-full text-sm text-hivis" x-text="pasteMsg"></p>
                        </div>
                    </div>
                </div>
                <div x-show="people.length > 8" class="border-b border-white/[0.06] px-5 py-2.5"><input x-model="listQ" type="search" placeholder="Saring daftar peserta…" class="field !py-2 text-sm"></div>
                <ul class="max-h-[460px] divide-y divide-white/[0.05] overflow-y-auto">
                    <template x-for="p in visiblePeople" :key="p.id">
                        <li class="flex flex-wrap items-center gap-3 px-5 py-2.5">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/[0.06] font-display text-xs font-bold text-fog" x-text="p.name.split(' ').slice(0, 2).map((w) => w[0]).join('')"></span>
                            <span class="min-w-0 grow leading-tight"><span class="block truncate font-medium" x-text="p.name"></span><span class="text-xs text-mist"><span class="font-display" x-text="p.nrp"></span> · <span x-text="p.department"></span></span></span>
                            <input x-model="p.label" maxlength="60" list="label-list" placeholder="Kelompok" class="field !w-48 !py-1.5 text-sm">
                            <button type="button" @click="remove(p)" class="icon-btn hover:!text-alarm" aria-label="Hapus peserta"><i class="ri-close-line text-xl"></i></button>
                        </li>
                    </template>
                    <li x-show="!people.length" class="px-5 py-10 text-center text-sm text-mist"><i class="ri-user-star-line text-3xl"></i><p class="mt-1" x-text="audience === 'assigned' ? 'Belum ada peserta. Tambahkan kandidat yang wajib mengerjakan.' : 'Opsional: peserta yang ditunjuk akan mendapat pemberitahuan di portal.'"></p></li>
                </ul>
                <template x-for="(p, n) in people" :key="p.id">
                    <span><input type="hidden" :name="`participants[${n}][user_id]`" :value="p.id"><input type="hidden" :name="`participants[${n}][label]`" :value="p.label"></span>
                </template>
            </section>
        </div>

        {{-- Ringkasan --}}
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="a-card overflow-hidden">
                <div class="relative overflow-hidden border-b border-white/[0.06] p-5">
                    <div class="absolute inset-0 bg-[radial-gradient(80%_80%_at_0%_0%,rgba(255,194,61,.14),transparent)]"></div>
                    <p class="relative text-xs text-mist">Kode untuk diumumkan</p>
                    <p class="relative mt-1 font-display text-[2rem] leading-none font-bold tracking-[.18em] text-hivis" x-text="code || '—'"></p>
                </div>
                <dl class="grid grid-cols-2 gap-px bg-white/[0.05]">
                    <div class="bg-bench p-4"><dt class="text-xs text-mist">Soal / peserta</dt><dd class="font-display text-2xl font-bold" :class="!total && 'text-alarm'" x-text="total"></dd></div>
                    <div class="bg-bench p-4"><dt class="text-xs text-mist">Durasi</dt><dd class="font-display text-2xl font-bold" x-text="`${duration || 0}′`"></dd></div>
                    <div class="bg-bench p-4"><dt class="text-xs text-mist">Peserta</dt><dd class="font-display text-2xl font-bold" x-text="people.length || (audience === 'open' ? '∞' : 0)"></dd></div>
                    <div class="bg-bench p-4"><dt class="text-xs text-mist">Paket</dt><dd class="font-display text-2xl font-bold" x-text="chosen.length"></dd></div>
                </dl>
                <div class="space-y-2 p-5">
                    @if ($locked)
                        <p class="flex gap-2 rounded-xl bg-hivis/10 px-3 py-2.5 text-xs leading-relaxed text-hivis"><i class="ri-information-line text-base"></i> Sudah ada peserta yang mengerjakan. Perubahan soal hanya berlaku untuk peserta yang mulai setelah ini.</p>
                    @endif
                    <p x-show="!total" class="text-xs text-alarm">Pilih minimal satu paket soal.</p>
                    <p x-show="audience === 'assigned' && !people.length" class="text-xs text-hivis">Belum ada peserta ditunjuk.</p>
                    <button name="publish" value="1" class="btn-neon w-full" :disabled="!total"><i class="ri-send-plane-2-line text-lg"></i> {{ $exam->exists && $exam->is_published ? 'Simpan & tetap terbit' : 'Simpan & terbitkan' }}</button>
                    <button name="publish" value="0" class="btn-ghost w-full text-sm"><i class="ri-draft-line"></i> Simpan sebagai draf</button>
                    <p class="pt-1 text-center text-xs text-mist">Setelah terbit, peserta ditunjuk melihat pemberitahuan saat membuka portal.</p>
                </div>
            </div>
            @if ($exam->exists)
                <a href="{{ route('admin.exams.show', $exam) }}" class="mt-3 block text-center text-sm text-mist hover:text-ink"><i class="ri-arrow-left-line"></i> Kembali ke halaman pantau</a>
            @endif
        </aside>
    </form>
</x-layouts.admin>
