<x-layouts.admin title="Bank soal" :subtitle="number_format($active, 0, ',', '.').' soal aktif di '.$sets->count().' paket'">
    <x-slot:actions>
        <a href="{{ route('admin.exams.bank.import') }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-file-excel-2-line text-lg text-neon"></i> Impor Excel</a>
        <button type="button" x-data @click="$dispatch('new-set')" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Paket baru</button>
    </x-slot:actions>
    @include('admin.exams._tabs')

    <form method="GET" class="mb-5">
        <div class="relative max-w-xl"><i class="ri-search-line pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-lg text-mist"></i>
            <input name="q" value="{{ $q }}" type="search" placeholder="Cari nama paket atau isi soal…" class="field !pl-11"></div>
    </form>

    @if ($hits->isNotEmpty())
        <section class="a-card mb-5 overflow-hidden">
            <p class="border-b border-white/[0.06] px-4 py-3 text-sm font-semibold text-fog">Soal yang memuat “{{ $q }}”</p>
            <ul class="divide-y divide-white/[0.05]">
                @foreach ($hits as $h)
                    <li><a href="{{ route('admin.exams.bank.edit', $h) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-white/[0.03]"><span class="shrink-0 rounded-md bg-white/[0.06] px-2 py-0.5 text-xs text-mist">{{ $h->set->name }}</span><span class="truncate">{{ $h->body }}</span><i class="ri-arrow-right-s-line ml-auto text-mist"></i></a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @forelse ($sets as $s)
            <a href="{{ route('admin.exams.bank.set', $s) }}" class="group a-card flex flex-col p-4 transition hover:-translate-y-0.5 hover:border-white/20">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-neon/10 text-neon"><i class="ri-stack-line text-xl"></i></span>
                    <span class="min-w-0 leading-tight"><span class="block truncate font-semibold group-hover:text-neon">{{ $s->name }}</span><span class="text-xs text-mist">{{ $s->description ?: 'Paket soal' }}</span></span>
                </div>
                <div class="mt-4 flex items-end gap-4 text-sm">
                    <span><span class="block font-display text-2xl leading-none font-bold">{{ $s->active_count }}</span><span class="text-xs text-mist">soal aktif</span></span>
                    @if ($s->image_count) <span class="text-mist"><i class="ri-image-line"></i> {{ $s->image_count }}</span> @endif
                    @if ($s->questions_count > $s->active_count) <span class="text-mist"><i class="ri-eye-off-line"></i> {{ $s->questions_count - $s->active_count }}</span> @endif
                    <span class="ml-auto text-xs {{ $s->exams_count ? 'text-hivis' : 'text-mist' }}"><i class="ri-medal-2-line"></i> {{ $s->exams_count }} ujian</span>
                </div>
            </a>
        @empty
            <div class="a-card col-span-full flex flex-col items-center gap-3 py-14 text-center">
                <i class="ri-question-answer-line text-5xl text-mist"></i>
                <p class="max-w-md text-fog">{{ $q ? 'Tidak ada paket yang cocok.' : 'Bank soal masih kosong. Impor langsung dari Google Sheet lama (unduh sebagai .xlsx) atau dari template.' }}</p>
                @unless ($q) <a href="{{ route('admin.exams.bank.import') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-file-excel-2-line text-lg"></i> Impor soal</a> @endunless
            </div>
        @endforelse
    </div>

    {{-- Paket baru --}}
    <div x-data="{ open: {{ $errors->has('name') ? 'true' : 'false' }} }" @new-set.window="open = true; $nextTick(() => $refs.name.focus())" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @keydown.escape.window="open = false">
        <form method="POST" action="{{ route('admin.exams.bank.store') }}" @click.outside="open = false" class="w-full max-w-md rounded-2xl border border-white/10 bg-bench p-6">
            @csrf
            <h2 class="font-display text-xl font-semibold">Paket soal baru</h2>
            <label class="label mt-4" for="set-name">Nama paket</label>
            <input id="set-name" x-ref="name" name="name" value="{{ old('name') }}" required maxlength="120" class="field" placeholder="mis. BEST MEKANIK 2026 — HIDROLIK">
            @error('name') <p class="error">{{ $message }}</p> @enderror
            <label class="label mt-3" for="set-desc">Keterangan (opsional)</label>
            <input id="set-desc" name="description" maxlength="500" class="field" placeholder="Unit, materi, atau sumber soal">
            <div class="mt-5 flex justify-end gap-2"><button type="button" @click="open = false" class="btn-ghost !min-h-11 text-sm">Batal</button><button class="btn-neon !min-h-11 text-sm">Buat paket</button></div>
        </form>
    </div>
</x-layouts.admin>
