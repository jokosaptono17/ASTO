<x-layouts.admin title="Impor soal" subtitle="Dari Google Sheet lama atau template Excel">
    <x-slot:actions>
        <a href="{{ route('admin.exams.bank.template') }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-download-2-line text-lg text-neon"></i> Unduh template</a>
    </x-slot:actions>
    @include('admin.exams._tabs')

    @if ($summary = session('summary'))
        <section class="a-card mb-5 overflow-hidden">
            <div class="flex flex-wrap items-center gap-4 border-b border-white/[0.06] p-5">
                <span class="grid h-12 w-12 place-items-center rounded-2xl {{ $summary['created'] ? 'bg-neon/15 text-neon' : 'bg-hivis/15 text-hivis' }}"><i class="{{ $summary['created'] ? 'ri-check-double-line' : 'ri-information-line' }} text-2xl"></i></span>
                <div class="grow leading-tight"><p class="font-display text-lg font-semibold">{{ number_format($summary['created'], 0, ',', '.') }} soal baru diimpor</p><p class="text-sm text-mist">dari {{ $summary['file'] }}</p></div>
                <div class="flex gap-6 text-center">
                    <div><p class="font-display text-2xl font-bold">{{ count($summary['sets']) }}</p><p class="text-xs text-mist">paket</p></div>
                    <div><p class="font-display text-2xl font-bold text-mist">{{ $summary['duplicates'] }}</p><p class="text-xs text-mist">sudah ada</p></div>
                    <div><p class="font-display text-2xl font-bold {{ $summary['skipped'] ? 'text-hivis' : 'text-mist' }}">{{ count($summary['skipped']) }}</p><p class="text-xs text-mist">dilewati</p></div>
                </div>
            </div>
            <div class="grid gap-5 p-5 lg:grid-cols-2">
                @if ($summary['sets'])
                    <div>
                        <p class="text-sm font-semibold text-fog">Masuk ke paket</p>
                        <ul class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($summary['sets'] as $name => $n) <li class="rounded-full bg-white/[0.06] px-3 py-1 text-sm">{{ $name }} <b class="font-display text-neon">{{ $n }}</b></li> @endforeach
                        </ul>
                        <a href="{{ route('admin.exams.bank') }}" class="section-link mt-3">Buka bank soal <i class="ri-arrow-right-s-line"></i></a>
                    </div>
                @endif
                @if ($summary['skipped'])
                    <div>
                        <p class="text-sm font-semibold text-hivis">Baris yang dilewati — perbaiki di sheet lalu impor ulang (yang sudah masuk tidak akan dobel)</p>
                        <ul class="mt-2 max-h-56 space-y-1 overflow-y-auto text-sm">
                            @foreach (collect($summary['skipped'])->take(200) as [$row, $why]) <li class="flex gap-3"><span class="w-16 shrink-0 font-display text-mist">baris {{ $row }}</span><span class="text-fog">{{ $why }}</span></li> @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_400px]">
        <form method="POST" action="{{ route('admin.exams.bank.import.store') }}" enctype="multipart/form-data" class="a-card space-y-5 p-5 lg:p-6" x-data="{ name: '' }">
            @csrf
            <label class="flex cursor-pointer flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-white/15 px-6 py-12 text-center transition hover:border-neon/50 hover:bg-neon/[0.03]" :class="name && '!border-neon/60 bg-neon/[0.05]'">
                <i class="text-5xl" :class="name ? 'ri-file-excel-2-fill text-neon' : 'ri-upload-cloud-2-line text-mist'"></i>
                <span class="font-semibold" x-text="name || 'Pilih file .xlsx atau .csv'"></span>
                <span class="text-sm text-mist">Sheet bernama “Soal” dibaca otomatis; selain itu sheet pertama.</span>
                <input type="file" name="file" accept=".xlsx,.csv" required class="sr-only" @change="name = $event.target.files[0]?.name || ''">
            </label>
            @error('file') <p class="error"><i class="ri-error-warning-line"></i>{{ $message }}</p> @enderror
            <div>
                <label class="label" for="set_id">Masukkan ke paket</label>
                <select id="set_id" name="set_id" class="field">
                    <option value="">Otomatis dari kolom KodeUjian / Paket</option>
                    @foreach ($sets as $s) <option value="{{ $s->id }}" @selected(request('paket') == $s->id)>{{ $s->name }}</option> @endforeach
                </select>
            </div>
            <button class="btn-neon w-full" :disabled="!name"><i class="ri-upload-2-line text-lg"></i> Impor soal</button>
        </form>

        <aside class="space-y-4">
            <section class="a-card p-5">
                <h2 class="flex items-center gap-2 font-semibold"><i class="ri-google-line text-lg text-neon"></i> Dari Google Sheet lama</h2>
                <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-sm leading-relaxed text-fog">
                    <li>Buka <b>INTEGRATED_KUMPULAN_SOAL</b>.</li>
                    <li>File → Download → <b>Microsoft Excel (.xlsx)</b>.</li>
                    <li>Unggah di sini. Kolom <b>KodeUjian</b> menjadi nama paket; link gambar GitHub diubah otomatis ke tautan gambar langsung.</li>
                </ol>
            </section>
            <section class="a-card p-5">
                <h2 class="flex items-center gap-2 font-semibold"><i class="ri-table-line text-lg text-neon"></i> Kolom yang dikenali</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex gap-3"><dt class="w-24 shrink-0 font-semibold text-fog">Paket</dt><dd class="text-mist">Paket, KodeUjian, Kode</dd></div>
                    <div class="flex gap-3"><dt class="w-24 shrink-0 font-semibold text-fog">Pertanyaan</dt><dd class="text-mist">Pertanyaan, Soal</dd></div>
                    <div class="flex gap-3"><dt class="w-24 shrink-0 font-semibold text-fog">Opsi</dt><dd class="text-mist">A–F atau Opsi A–Opsi F (min. 2)</dd></div>
                    <div class="flex gap-3"><dt class="w-24 shrink-0 font-semibold text-fog">Kunci</dt><dd class="text-mist">Jawaban / Kunci: huruf (A, b, “C.”) atau teks opsinya</dd></div>
                    <div class="flex gap-3"><dt class="w-24 shrink-0 font-semibold text-fog">Opsional</dt><dd class="text-mist">Gambar (URL), Pembahasan</dd></div>
                </dl>
                <p class="mt-3 text-xs text-mist">Impor ulang aman: soal yang isinya sama di paket yang sama dilewati.</p>
            </section>
        </aside>
    </div>
</x-layouts.admin>
