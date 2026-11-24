<x-layouts.admin :title="$set->name" :subtitle="$set->active_count.' soal aktif dari '.$set->questions_count.($set->description ? ' · '.$set->description : '')">
    <x-slot:actions>
        <a href="{{ route('admin.exams.bank.export', $set) }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-download-2-line text-lg"></i> Ekspor</a>
        <a href="{{ route('admin.exams.bank.import', ['paket' => $set->id]) }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-file-excel-2-line text-lg text-neon"></i> Impor ke paket ini</a>
        <a href="{{ route('admin.exams.bank.create', $set) }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Tambah soal</a>
    </x-slot:actions>
    @include('admin.exams._tabs')

    <div class="mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.exams.bank') }}" class="text-sm text-mist hover:text-ink"><i class="ri-arrow-left-line"></i> Semua paket</a>
        <span class="mx-1 h-4 w-px bg-white/10"></span>
        @foreach ([null => 'Semua', 'gambar' => 'Bergambar', 'nonaktif' => 'Nonaktif'] as $k => $l)
            <a href="{{ route('admin.exams.bank.set', [$set, 'tampil' => $k, 'q' => $q]) }}" class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $filter == $k ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog' }}">{{ $l }}</a>
        @endforeach
        <form method="GET" class="ml-auto w-full sm:w-72"><input type="hidden" name="tampil" value="{{ $filter }}">
            <div class="relative"><i class="ri-search-line pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-mist"></i><input name="q" value="{{ $q }}" type="search" placeholder="Cari di paket ini…" class="field !py-2 !pl-9 text-sm"></div>
        </form>
    </div>

    @if ($exams->isNotEmpty())
        <p class="mb-4 flex flex-wrap items-center gap-2 text-sm text-mist"><i class="ri-medal-2-line text-hivis"></i> Dipakai di:
            @foreach ($exams as $e) <a href="{{ route('admin.exams.show', $e) }}" class="rounded-full bg-white/[0.06] px-2.5 py-0.5 text-fog hover:text-ink">{{ $e->title }}</a> @endforeach
        </p>
    @endif

    <div class="space-y-3">
        @forelse ($questions as $n => $question)
            @php $opts = $question->filledOptions(); $rate = $question->answers_count ? round($question->correct_count / $question->answers_count * 100) : null; @endphp
            <article class="a-card p-4 {{ $question->is_active ? '' : 'opacity-60' }}">
                <div class="flex gap-4">
                    <span class="font-display text-sm font-bold text-mist">{{ $questions->firstItem() + $n }}</span>
                    <div class="min-w-0 grow">
                        <p class="leading-relaxed whitespace-pre-line">{{ $question->body }}</p>
                        @if ($question->imageUrl())
                            <img src="{{ $question->imageUrl() }}" alt="" loading="lazy" class="mt-3 max-h-44 rounded-xl bg-white object-contain" onerror="this.replaceWith(Object.assign(document.createElement('p'), { className: 'mt-2 text-xs text-alarm', textContent: 'Gambar tidak bisa dimuat: ' + this.src }))">
                        @endif
                        <ul class="mt-3 grid gap-1.5 text-sm md:grid-cols-2">
                            @foreach ($opts as $k => $t)
                                <li class="flex gap-2 rounded-xl px-3 py-1.5 {{ $k === $question->answer ? 'bg-neon/10 text-ink ring-1 ring-neon/30' : 'bg-white/[0.03] text-fog' }}"><span class="font-display font-bold {{ $k === $question->answer ? 'text-neon' : 'text-mist' }}">{{ $k }}</span><span>{{ $t }}</span></li>
                            @endforeach
                        </ul>
                        @if ($question->explanation) <p class="mt-2 text-sm text-mist"><i class="ri-lightbulb-line text-hivis"></i> {{ $question->explanation }}</p> @endif
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <a href="{{ route('admin.exams.bank.edit', [$question, 'page' => $questions->currentPage()]) }}" class="icon-btn" aria-label="Ubah"><i class="ri-edit-line text-lg"></i></a>
                        <form method="POST" action="{{ route('admin.exams.bank.toggle', $question) }}">@csrf @method('PATCH')<button class="icon-btn" aria-label="{{ $question->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" title="{{ $question->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"><i class="{{ $question->is_active ? 'ri-eye-line' : 'ri-eye-off-line' }} text-lg"></i></button></form>
                        <form method="POST" action="{{ route('admin.exams.bank.destroy', $question) }}" onsubmit="return confirm('Hapus soal ini?')">@csrf @method('DELETE')<button class="icon-btn hover:!text-alarm" aria-label="Hapus"><i class="ri-delete-bin-line text-lg"></i></button></form>
                    </div>
                </div>
                @if ($rate !== null)
                    <p class="mt-3 border-t border-white/[0.05] pt-2 text-xs text-mist">Dijawab {{ $question->answers_count }}× · <span class="{{ $rate < 30 ? 'text-alarm' : ($rate < 60 ? 'text-hivis' : 'text-neon') }}">{{ $rate }}% benar</span></p>
                @endif
            </article>
        @empty
            <div class="a-card py-14 text-center text-mist">
                <i class="ri-question-answer-line text-4xl"></i>
                <p class="mt-2">{{ $q || $filter ? 'Tidak ada soal yang cocok.' : 'Paket ini belum berisi soal.' }}</p>
                <a href="{{ route('admin.exams.bank.create', $set) }}" class="btn-neon mt-3 !min-h-10 text-sm">Tambah soal pertama</a>
            </div>
        @endforelse
    </div>
    <div class="mt-4">{{ $questions->links('admin.pagination') }}</div>

    <details class="a-card mt-8 p-5">
        <summary class="cursor-pointer font-semibold text-fog"><i class="ri-settings-3-line"></i> Pengaturan paket</summary>
        <form method="POST" action="{{ route('admin.exams.bank.update-set', $set) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
            @csrf @method('PUT')
            <div><label class="label">Nama paket</label><input name="name" value="{{ $set->name }}" required maxlength="120" class="field"></div>
            <div><label class="label">Keterangan</label><input name="description" value="{{ $set->description }}" maxlength="500" class="field"></div>
            <button class="btn-neon !min-h-[50px] text-sm">Simpan</button>
        </form>
        <form method="POST" action="{{ route('admin.exams.bank.destroy-set', $set) }}" onsubmit="return confirm('Hapus paket {{ $set->name }} beserta semua soalnya?')" class="mt-4 border-t border-white/[0.06] pt-4">
            @csrf @method('DELETE')
            <button class="text-sm font-semibold text-red-300 hover:underline"><i class="ri-delete-bin-line"></i> Hapus paket beserta soalnya</button>
        </form>
    </details>
</x-layouts.admin>
