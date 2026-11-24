<x-layouts.admin title="Event ujian" subtitle="Ujian kompetisi & event: Best Mekanik, Best GL, pre/post test training">
    <x-slot:actions>
        <a href="{{ route('admin.exams.bank') }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-question-answer-line text-lg text-neon"></i> Bank soal</a>
        <a href="{{ route('admin.exams.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Buat ujian</a>
    </x-slot:actions>
    @include('admin.exams._tabs')

    @if ($exams->isEmpty() && ! $filter)
        <section class="a-card relative overflow-hidden p-6 lg:p-10">
            <x-contour class="pointer-events-none absolute -top-16 -right-24 h-72 w-[520px] opacity-25" />
            <div class="relative max-w-2xl">
                <span class="exam-medal"><i class="ri-medal-2-fill"></i></span>
                <h2 class="mt-4 font-display text-2xl font-bold">Ujian event pertama dalam 3 langkah</h2>
                <p class="mt-1 text-fog">Peserta cukup login portal; nama & NRP otomatis. Mereka langsung mendapat pemberitahuan dan masuk dengan satu kode dari pengawas.</p>
                <ol class="mt-6 grid gap-3 md:grid-cols-3">
                    <li class="rounded-2xl border border-white/[0.08] bg-white/[0.03] p-4">
                        <p class="font-display text-sm text-neon">Langkah 1</p><p class="mt-1 font-semibold">Siapkan bank soal</p>
                        <p class="mt-1 text-sm text-mist">{{ number_format($bank['questions'], 0, ',', '.') }} soal aktif di {{ $bank['sets'] }} paket.</p>
                        <a href="{{ $bank['questions'] ? route('admin.exams.bank') : route('admin.exams.bank.import') }}" class="section-link mt-3">{{ $bank['questions'] ? 'Buka bank soal' : 'Impor dari Excel' }} <i class="ri-arrow-right-s-line"></i></a>
                    </li>
                    <li class="rounded-2xl border border-white/[0.08] bg-white/[0.03] p-4">
                        <p class="font-display text-sm text-neon">Langkah 2</p><p class="mt-1 font-semibold">Buat ujian & tunjuk peserta</p>
                        <p class="mt-1 text-sm text-mist">Pilih paket soal, jadwal, durasi, lalu kandidat dari daftar karyawan.</p>
                        <a href="{{ route('admin.exams.create') }}" class="section-link mt-3">Buat ujian <i class="ri-arrow-right-s-line"></i></a>
                    </li>
                    <li class="rounded-2xl border border-white/[0.08] bg-white/[0.03] p-4">
                        <p class="font-display text-sm text-neon">Langkah 3</p><p class="mt-1 font-semibold">Pantau langsung</p>
                        <p class="mt-1 text-sm text-mist">Tampilkan kode di proyektor, lihat progres & peringkat secara langsung.</p>
                    </li>
                </ol>
            </div>
        </section>
    @else
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.exams.index') }}" class="rounded-full px-3.5 py-1.5 text-sm font-semibold {{ ! $filter ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog' }}">Semua</a>
            @foreach (\App\Models\Exam::STATES as $k => $s)
                <a href="{{ route('admin.exams.index', ['status' => $k]) }}" class="rounded-full px-3.5 py-1.5 text-sm font-semibold {{ $filter === $k ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog' }}">{{ $s['label'] }} <span class="opacity-70">{{ $counts[$k] ?? 0 }}</span></a>
            @endforeach
        </div>

        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
            @forelse ($exams as $exam)
                @php $st = $exam->stateInfo(); $state = $exam->state(); @endphp
                <a href="{{ route('admin.exams.show', $exam) }}" class="group relative flex flex-col overflow-hidden rounded-[22px] border {{ $state === 'open' ? 'border-hivis/40' : 'border-white/[0.07]' }} bg-bench p-5 transition hover:-translate-y-0.5 hover:border-white/20">
                    @if ($state === 'open') <span class="exam-card-glow" aria-hidden="true"></span> @endif
                    <div class="relative flex items-center gap-2">
                        <span class="{{ $st['chip'] }}"><i class="{{ $st['icon'] }}"></i> {{ $st['label'] }}</span>
                        @if ($exam->live_count)
                            <span class="chip bg-sky-400/15 text-sky-300"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-sky-300"></span>{{ $exam->live_count }} mengerjakan</span>
                        @endif
                        <span class="ml-auto rounded-lg border border-white/10 px-2 py-0.5 font-display text-sm font-semibold tracking-wider text-hivis">{{ $exam->code }}</span>
                    </div>
                    <h2 class="relative mt-3 line-clamp-2 font-display text-lg leading-snug font-bold group-hover:text-neon">{{ $exam->title }}</h2>
                    <p class="relative mt-1 text-sm text-mist"><i class="ri-calendar-line"></i> {{ $exam->scheduleLabel() }} · {{ $exam->duration_minutes }} mnt</p>
                    <dl class="relative mt-auto grid grid-cols-3 gap-2 pt-5 text-center">
                        <div class="rounded-xl bg-white/[0.04] py-2"><dd class="font-display text-lg font-bold">{{ $exam->audience === 'open' && ! $exam->participants_count ? '∞' : $exam->participants_count }}</dd><dt class="text-[11px] text-mist">peserta</dt></div>
                        <div class="rounded-xl bg-white/[0.04] py-2"><dd class="font-display text-lg font-bold">{{ $exam->done_count }}</dd><dt class="text-[11px] text-mist">selesai</dt></div>
                        <div class="rounded-xl bg-white/[0.04] py-2"><dd class="font-display text-lg font-bold {{ $exam->avg_score !== null && $exam->avg_score >= $exam->pass_score ? 'text-neon' : '' }}">{{ $exam->avg_score !== null ? number_format($exam->avg_score, 1, ',', '') : '–' }}</dd><dt class="text-[11px] text-mist">rata-rata</dt></div>
                    </dl>
                </a>
            @empty
                <p class="a-card col-span-full py-12 text-center text-mist">Tidak ada ujian dengan status ini.</p>
            @endforelse
        </div>
    @endif
</x-layouts.admin>
