{{-- Ujian event yang ditunjuk untuk user (beranda) --}}
@if ($exams->isNotEmpty())
    <section class="mx-4 mt-4 space-y-3 lg:mx-0 lg:mt-0 lg:mb-5" aria-label="Ujian untuk Anda">
        @foreach ($exams->take(2) as $item)
            @php
                $exam = $item->exam;
                $label = $item->label;
                $live = $item->attempt;
                $open = $item->state === 'open' && ! $item->done;
            @endphp
            <article class="exam-card relative overflow-hidden rounded-[24px] border {{ $live ? 'border-sky-300/40' : ($open ? 'border-hivis/45' : 'border-white/[0.08]') }} bg-bench">
                @if ($open && ! $live) <span class="exam-card-glow" aria-hidden="true"></span> @endif
                <x-contour class="pointer-events-none absolute -top-14 -right-20 h-52 w-80 opacity-20" />
                <div class="relative flex gap-3.5 p-4">
                    <span class="exam-medal {{ $open || $live ? '' : 'opacity-60 grayscale' }}" aria-hidden="true"><i class="ri-medal-2-fill"></i></span>
                    <div class="min-w-0 grow">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                            <span class="font-semibold {{ $live ? 'text-sky-300' : ($open ? 'text-hivis' : 'text-mist') }}">
                                @if ($live) <i class="ri-edit-2-line"></i> Sedang dikerjakan
                                @elseif ($item->done) <i class="ri-checkbox-circle-line"></i> Sudah dikumpulkan
                                @elseif ($open) <span class="mr-0.5 inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-hivis align-middle"></span> Ujian untuk Anda
                                @else <i class="ri-calendar-schedule-line"></i> Ujian terjadwal @endif
                            </span>
                            @if ($label) <span class="rounded-full bg-white/[0.07] px-2 py-0.5 text-fog">{{ $label }}</span> @endif
                        </p>
                        <h3 class="mt-1 font-display text-lg leading-snug font-bold">{{ $exam->title }}</h3>
                        <p class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-sm text-mist">
                            <span><i class="ri-file-list-3-line"></i> {{ $exam->questionCount() }} soal</span>
                            <span><i class="ri-timer-line"></i> {{ $exam->duration_minutes }} menit</span>
                            @if ($exam->closes_at && $open && ! $live)
                                <span x-data="countdown({{ $exam->closes_at->timestamp }})"><i class="ri-hourglass-2-line"></i> tutup <span x-text="human" class="font-semibold text-fog"></span> lagi</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="relative px-4 pb-4">
                    @if ($live)
                        <a href="{{ route('exams.room', $live) }}" class="btn-neon w-full !min-h-12" x-data="countdown({{ $live->deadline_at->timestamp }})">
                            <i class="ri-play-circle-line text-xl"></i> Lanjutkan · sisa <span class="font-display" x-text="clock"></span>
                        </a>
                    @elseif ($open)
                        <a href="{{ route('exams.show', $exam) }}" class="btn-hivis w-full !min-h-12"><i class="ri-quill-pen-line text-xl"></i> Kerjakan sekarang</a>
                    @elseif ($item->done)
                        <a href="{{ route('exams.result', $item->last) }}" class="btn-ghost w-full !min-h-11 text-sm">Lihat status <i class="ri-arrow-right-s-line"></i></a>
                    @else
                        <div class="flex items-center gap-3 rounded-2xl bg-white/[0.04] px-3.5 py-2.5" x-data="countdown({{ $exam->opens_at->timestamp }}, 'reload')">
                            <i class="ri-calendar-event-line text-xl text-hivis"></i>
                            <span class="min-w-0 grow text-sm text-fog">Dibuka {{ $exam->opens_at->locale('id')->translatedFormat($exam->opens_at->isToday() ? '\p\u\k\u\l H:i' : 'l, j M · H:i') }}</span>
                            <span class="font-display text-lg font-bold text-hivis" x-text="human"></span>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
        @if ($exams->count() > 2)
            <a href="{{ route('exams.index') }}" class="block text-center text-sm font-semibold text-hivis">+{{ $exams->count() - 2 }} ujian lain <i class="ri-arrow-right-s-line"></i></a>
        @endif
    </section>
@endif
