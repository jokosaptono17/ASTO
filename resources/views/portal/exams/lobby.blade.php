<x-layouts.portal :title="$exam->title" :mentari-tips="['Kode ujian diumumkan pengawas.', 'Jawaban tersimpan otomatis tiap Anda memilih.']">
    @include('portal._topbar', ['heading' => 'Ujian', 'sub' => $label ?: 'Ujian event Plant ASTO', 'back' => route('exams.index')])

    <div class="flex flex-col gap-5 px-4 pb-8 lg:grid lg:grid-cols-[minmax(0,1fr)_420px] lg:items-start lg:gap-8 lg:px-0">
        {{-- HP: identitas ujian → kolom kode → tata tertib; desktop: dua kolom --}}
        <div class="min-w-0 space-y-5 max-lg:contents">
            {{-- Identitas ujian --}}
            <section class="order-1 relative overflow-hidden rounded-[26px] border border-hivis/30 bg-bench">
                <div class="absolute inset-0 bg-[radial-gradient(70%_80%_at_0%_0%,rgba(255,194,61,.16),transparent_65%)]"></div>
                <x-contour class="pointer-events-none absolute -top-16 -right-24 h-64 w-[420px] opacity-25" />
                <div class="relative p-5 lg:p-7">
                    <div class="flex items-start gap-4">
                        <span class="exam-medal lg:!h-16 lg:!w-16 lg:!text-[34px]"><i class="ri-medal-2-fill"></i></span>
                        <div class="min-w-0">
                            <span class="{{ $exam->stateInfo()['chip'] }} text-xs"><i class="{{ $exam->stateInfo()['icon'] }}"></i> {{ $exam->stateInfo()['label'] }}</span>
                            <h2 class="mt-2 font-display text-[1.55rem] leading-tight font-bold text-balance lg:text-[2rem]">{{ $exam->title }}</h2>
                            <p class="mt-1 text-sm text-mist"><i class="ri-calendar-line"></i> {{ $exam->scheduleLabel() }}</p>
                        </div>
                    </div>
                    @if ($exam->description)
                        <p class="mt-4 text-[15px] leading-relaxed whitespace-pre-line text-fog">{{ $exam->description }}</p>
                    @endif
                    <dl class="mt-5 grid grid-cols-3 gap-2">
                        <div class="rounded-2xl bg-white/[0.04] px-3 py-3"><dt class="text-xs text-mist">Jumlah soal</dt><dd class="mt-0.5 font-display text-2xl font-bold">{{ $count }}</dd></div>
                        <div class="rounded-2xl bg-white/[0.04] px-3 py-3"><dt class="text-xs text-mist">Waktu</dt><dd class="mt-0.5 font-display text-2xl font-bold">{{ $exam->duration_minutes }}<span class="text-sm font-medium text-mist"> mnt</span></dd></div>
                        <div class="rounded-2xl bg-white/[0.04] px-3 py-3"><dt class="text-xs text-mist">Kesempatan</dt><dd class="mt-0.5 font-display text-2xl font-bold">{{ $exam->max_attempts }}<span class="text-sm font-medium text-mist">×</span></dd></div>
                    </dl>
                </div>
            </section>

            {{-- Tata tertib --}}
            <section class="order-3 rounded-[22px] border border-white/[0.07] bg-bench p-5">
                <h3 class="section-title">Sebelum mulai</h3>
                <ul class="mt-3 space-y-3 text-[14.5px] leading-relaxed text-fog">
                    <li class="flex gap-3"><i class="ri-save-3-line mt-0.5 text-lg text-neon"></i><span>Setiap jawaban <b class="text-ink">tersimpan otomatis</b>. Bila HP mati atau sinyal hilang, buka lagi portal dan lanjutkan dari soal terakhir.</span></li>
                    <li class="flex gap-3"><i class="ri-timer-flash-line mt-0.5 text-lg text-hivis"></i><span>Waktu <b class="text-ink">{{ $exam->duration_minutes }} menit</b> berjalan sejak Anda masuk dan tidak berhenti walau keluar aplikasi. Saat habis, jawaban dikumpulkan otomatis.</span></li>
                    <li class="flex gap-3"><i class="ri-eye-off-line mt-0.5 text-lg text-alarm"></i><span>Jangan pindah ke aplikasi atau tab lain.
                        @if ($exam->max_violations) Pada pelanggaran ke-<b class="text-ink">{{ $exam->max_violations }}</b> ujian langsung dikumpulkan. @else Setiap kejadian tercatat untuk panitia. @endif</span></li>
                    <li class="flex gap-3"><i class="ri-flag-2-line mt-0.5 text-lg text-hivis"></i><span>Tandai soal <b class="text-ink">Ragu-ragu</b> untuk diperiksa lagi sebelum mengumpulkan.</span></li>
                    @if ($exam->showsScore())
                        <li class="flex gap-3"><i class="ri-award-line mt-0.5 text-lg text-neon"></i><span>Nilai lulus <b class="text-ink">{{ $exam->pass_score }}</b>. Nilai tampil setelah Anda mengumpulkan.</span></li>
                    @else
                        <li class="flex gap-3"><i class="ri-award-line mt-0.5 text-lg text-neon"></i><span>Hasil diumumkan panitia setelah ujian ditutup.</span></li>
                    @endif
                </ul>
            </section>
        </div>

        {{-- Masuk --}}
        <aside class="order-2 lg:sticky lg:top-24">
            <div class="rounded-[26px] border border-white/[0.08] bg-bench p-5 lg:p-6">
                <div class="flex items-center gap-3 rounded-2xl bg-white/[0.04] p-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-neon font-display font-bold text-[#04170d]">{{ auth()->user()->initials() }}</span>
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate font-semibold">{{ auth()->user()->displayName() }}</span>
                        <span class="block truncate text-sm text-mist"><span class="font-display">{{ auth()->user()->nrp }}</span> · {{ auth()->user()->department }}</span>
                    </span>
                    <i class="ri-shield-check-fill ml-auto text-xl text-neon" title="Identitas dari akun login"></i>
                </div>

                @if ($active)
                    <div class="mt-5 text-center" x-data="countdown({{ $active->deadline_at->timestamp }})">
                        <p class="text-sm text-mist">Sisa waktu Anda</p>
                        <p class="font-display text-[2.6rem] leading-none font-bold text-sky-300" x-text="clock"></p>
                        <p class="mt-2 text-sm text-fog">{{ $active->answered }} dari {{ $active->total }} soal sudah dijawab</p>
                    </div>
                    <a href="{{ route('exams.room', $active) }}" class="btn-neon mt-5 w-full !min-h-14 text-base"><i class="ri-play-circle-line text-xl"></i> Lanjutkan ujian</a>
                @elseif ($blocked)
                    <div class="mt-5 flex gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-sm leading-relaxed text-fog">
                        <i class="{{ $exam->state() === 'scheduled' ? 'ri-calendar-schedule-line text-hivis' : 'ri-information-line text-mist' }} text-2xl"></i>
                        <p>{{ $blocked }}</p>
                    </div>
                    @if ($exam->state() === 'scheduled')
                        <div class="mt-4 text-center" x-data="countdown({{ $exam->opens_at->timestamp }}, 'reload')">
                            <p class="text-sm text-mist">Dibuka dalam</p>
                            <p class="font-display text-[2.4rem] leading-none font-bold text-hivis" x-text="clock"></p>
                        </div>
                    @endif
                @else
                    <form method="POST" action="{{ route('exams.start', $exam) }}" class="mt-5" x-data="codeField(@js(old('code', '')))">
                        @csrf
                        <label for="code" class="label text-center">Kode ujian</label>
                        <input id="code" name="code" x-model="code" @input="clean()" autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="go" required
                               placeholder="Dari pengawas" class="code-cell" autofocus aria-describedby="code-help">
                        @error('code') <p class="error justify-center text-center"><i class="ri-error-warning-line"></i>{{ $message }}</p> @enderror
                        <p id="code-help" class="mt-2 text-center text-xs text-mist">Kode dibacakan atau ditampilkan pengawas di ruang ujian.</p>
                        <button class="btn-hivis mt-5 w-full !min-h-14 text-base" :disabled="code.length < 3"><i class="ri-login-circle-line text-xl"></i> Masuk ruang ujian</button>
                    </form>
                @endif
            </div>

            @if ($tries->isNotEmpty())
                <div class="mt-4 rounded-[22px] border border-white/[0.07] bg-bench p-4">
                    <p class="text-sm font-semibold text-fog">Percobaan Anda</p>
                    @foreach ($tries as $t)
                        <a href="{{ route('exams.result', $t) }}" class="mt-2 flex items-center gap-3 rounded-xl bg-white/[0.03] px-3 py-2.5 text-sm hover:bg-white/[0.06]">
                            <span class="font-display text-mist">#{{ $t->attempt_no }}</span>
                            <span class="grow text-fog">{{ $t->submitted_at?->locale('id')->translatedFormat('j M, H:i') }}</span>
                            @if ($exam->showsScore() && $t->status === 'submitted') <span class="font-display text-lg font-bold {{ $t->passed() ? 'text-neon' : 'text-hivis' }}">{{ rtrim(rtrim(number_format($t->score, 2, ',', ''), '0'), ',') }}</span>
                            @else <span class="{{ \App\Models\ExamAttempt::STATUSES[$t->status]['chip'] }} text-xs">{{ \App\Models\ExamAttempt::STATUSES[$t->status]['label'] }}</span> @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
</x-layouts.portal>
