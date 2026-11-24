<x-layouts.portal :title="'Hasil · '.$exam->title">
@php
    $show = $exam->showsScore() && $attempt->status === 'submitted';
    $passed = $attempt->passed();
    $score = (float) $attempt->score;
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
    $wrong = max(0, $attempt->answered - (int) $attempt->correct);
    $blank = max(0, $attempt->total - $attempt->answered);
    $len = 2 * M_PI * 70;
@endphp
    @include('portal._topbar', ['heading' => 'Hasil ujian', 'sub' => $exam->title, 'back' => route('exams.index')])

    <div class="space-y-5 px-4 pb-10 lg:mx-auto lg:max-w-3xl lg:px-0">
        <section class="relative overflow-hidden rounded-[28px] border {{ $attempt->status === 'disqualified' ? 'border-alarm/40' : ($show && ! $passed ? 'border-hivis/35' : 'border-neon/35') }} bg-bench px-5 pt-8 pb-6 text-center">
            <div class="absolute inset-0 {{ $attempt->status === 'disqualified' ? 'bg-[radial-gradient(70%_60%_at_50%_0%,rgba(255,93,93,.16),transparent)]' : 'bg-[radial-gradient(70%_60%_at_50%_0%,rgba(61,255,142,.14),transparent)]' }}"></div>
            <x-contour class="pointer-events-none absolute -top-12 left-1/2 h-56 w-[620px] -translate-x-1/2 opacity-25" />

            @if ($attempt->status === 'disqualified')
                <span class="relative mx-auto grid h-24 w-24 place-items-center rounded-full bg-alarm/15 text-alarm ring-1 ring-alarm/40"><i class="ri-forbid-2-line text-5xl"></i></span>
                <h2 class="relative mt-5 font-display text-2xl font-bold">Didiskualifikasi panitia</h2>
                <p class="relative mx-auto mt-2 max-w-sm text-fog">Hubungi panitia ujian bila ada pertanyaan tentang keputusan ini.</p>
            @elseif ($show)
                <div class="relative mx-auto h-[176px] w-[176px]">
                    <svg viewBox="0 0 160 160" class="h-full w-full -rotate-90" aria-hidden="true">
                        <circle cx="80" cy="80" r="70" fill="none" stroke="rgb(255 255 255 / .07)" stroke-width="12"/>
                        <circle cx="80" cy="80" r="70" fill="none" stroke="{{ $passed ? '#3dff8e' : '#ffc23d' }}" stroke-width="12" stroke-linecap="round"
                                class="score-ring" style="--len: {{ $len }}" stroke-dasharray="{{ $len }}" stroke-dashoffset="{{ $len * (1 - $score / 100) }}"/>
                    </svg>
                    <div class="absolute inset-0 grid place-content-center">
                        <p class="font-display text-[3.2rem] leading-none font-bold">{{ $fmt($score) }}</p>
                        <p class="mt-1 text-xs text-mist">nilai</p>
                    </div>
                </div>
                <p class="relative mt-4 inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 font-semibold {{ $passed ? 'bg-neon/15 text-neon' : 'bg-hivis/15 text-hivis' }}">
                    <i class="{{ $passed ? 'ri-award-fill' : 'ri-focus-3-line' }}"></i> {{ $passed ? 'Lulus' : 'Belum lulus' }} · batas {{ $exam->pass_score }}
                </p>
                <dl class="relative mt-6 grid grid-cols-4 gap-2">
                    <div class="rounded-2xl bg-neon/10 py-3"><dd class="font-display text-xl font-bold text-neon">{{ $attempt->correct }}</dd><dt class="text-[11px] text-mist">benar</dt></div>
                    <div class="rounded-2xl bg-alarm/10 py-3"><dd class="font-display text-xl font-bold text-red-300">{{ $wrong }}</dd><dt class="text-[11px] text-mist">salah</dt></div>
                    <div class="rounded-2xl bg-white/[0.05] py-3"><dd class="font-display text-xl font-bold">{{ $blank }}</dd><dt class="text-[11px] text-mist">kosong</dt></div>
                    <div class="rounded-2xl bg-white/[0.05] py-3"><dd class="font-display text-xl font-bold">{{ \App\Models\ExamAttempt::formatDuration($attempt->durationSeconds()) }}</dd><dt class="text-[11px] text-mist">waktu</dt></div>
                </dl>
            @else
                <span class="checkin-pop relative mx-auto grid h-24 w-24 place-items-center rounded-full bg-neon text-[#04170d]"><i class="ri-check-line text-5xl"></i></span>
                <h2 class="relative mt-6 font-display text-2xl font-bold">Jawaban terkirim</h2>
                <p class="relative mx-auto mt-2 max-w-sm text-fog">{{ $attempt->answered }} dari {{ $attempt->total }} soal dijawab. Hasil diumumkan panitia setelah ujian ditutup.</p>
            @endif

            <p class="relative mt-5 text-sm text-mist">
                <i class="ri-time-line"></i> {{ $attempt->submitted_at?->locale('id')->translatedFormat('l, j F Y · H:i') }}
                @if ($attempt->finish_reason && $attempt->finish_reason !== 'user') · <span class="text-hivis">{{ \App\Models\ExamAttempt::REASONS[$attempt->finish_reason] }}</span> @endif
            </p>
        </section>

        <div class="grid gap-2 sm:grid-cols-2">
            <a href="{{ route('home') }}" class="btn-ghost"><i class="ri-home-5-line text-lg"></i> Kembali ke beranda</a>
            @if ($canRetry)
                <a href="{{ route('exams.show', $exam) }}" class="btn-neon"><i class="ri-restart-line text-lg"></i> Kerjakan lagi</a>
            @else
                <a href="{{ route('exams.index') }}" class="btn-ghost"><i class="ri-medal-2-line text-lg"></i> Ujian saya</a>
            @endif
        </div>

        @if ($review)
            <section>
                <h2 class="section-title">Pembahasan</h2>
                <ol class="mt-3 space-y-3">
                    @foreach ($review as $a)
                        @php $opts = $a->question->options; @endphp
                        <li class="rounded-2xl border {{ $a->is_correct ? 'border-neon/25' : 'border-alarm/25' }} bg-bench p-4">
                            <p class="flex gap-2 text-[15px] leading-relaxed whitespace-pre-line"><span class="font-display font-bold {{ $a->is_correct ? 'text-neon' : 'text-red-300' }}">{{ $a->position }}.</span><span>{{ $a->question->body }}</span></p>
                            @if ($a->question->imageUrl()) <img src="{{ $a->question->imageUrl() }}" alt="" class="mt-3 max-h-60 rounded-xl bg-white object-contain" loading="lazy"> @endif
                            <ul class="mt-3 space-y-1.5 text-sm">
                                @foreach (str_split($a->option_order) as $i => $key)
                                    @php $right = $key === $a->question->answer; $mine = $key === $a->choice; @endphp
                                    <li class="flex gap-2 rounded-xl px-3 py-2 {{ $right ? 'bg-neon/10 text-ink' : ($mine ? 'bg-alarm/10 text-red-200' : 'text-mist') }}">
                                        <span class="font-display font-bold">{{ \App\Models\Question::LETTERS[$i] }}.</span><span class="grow">{{ $opts[$key] ?? '' }}</span>
                                        @if ($right) <i class="ri-check-line text-neon"></i> @elseif ($mine) <i class="ri-close-line"></i> @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if ($a->question->explanation) <p class="mt-3 rounded-xl bg-white/[0.04] px-3 py-2.5 text-sm leading-relaxed text-fog"><i class="ri-lightbulb-line text-hivis"></i> {{ $a->question->explanation }}</p> @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @elseif ($exam->result_mode === 'review' && $show)
            <p class="flex items-center gap-2 rounded-2xl bg-white/[0.04] px-4 py-3 text-sm text-mist"><i class="ri-lock-line text-lg"></i> Pembahasan dibuka setelah ujian ditutup{{ $exam->closes_at ? ' ('.$exam->closes_at->locale('id')->translatedFormat('j M H:i').')' : '' }}.</p>
        @endif
    </div>
</x-layouts.portal>
