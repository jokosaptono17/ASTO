<x-layouts.portal title="Ujian saya" :mentari-tips="['Masukkan kode dari pengawas untuk mulai.', 'Ujian yang ditunjuk untuk Anda muncul otomatis.']">
    @include('portal._topbar', ['heading' => 'Ujian saya', 'sub' => 'Kompetisi & ujian event Plant ASTO', 'back' => route('home')])

    <div class="flex flex-col gap-6 px-4 pb-8 lg:grid lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start lg:gap-8 lg:px-0">
        <div class="order-2 min-w-0 space-y-6 lg:order-none">
            @if ($pending->isNotEmpty())
                <div class="-mx-4 lg:mx-0">
                    @include('portal.exams._home-card', ['exams' => $pending, 'user' => auth()->user()])
                </div>
            @else
                <div class="flex items-center gap-4 rounded-[22px] border border-dashed border-white/10 p-5">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-white/[0.05] text-mist"><i class="ri-medal-2-line text-3xl"></i></span>
                    <p class="text-sm leading-relaxed text-mist">Belum ada ujian yang ditunjuk untuk Anda. Bila panitia memberi kode, masukkan di kolom kode.</p>
                </div>
            @endif

            <section>
                <h2 class="section-title">Riwayat ujian</h2>
                <div class="mt-3 overflow-hidden rounded-2xl border border-white/[0.07] bg-bench">
                    @forelse ($history as $a)
                        <a href="{{ route('exams.result', $a) }}" class="flex items-center gap-3 border-b border-white/[0.05] px-4 py-3 last:border-0 hover:bg-white/[0.03]">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $a->status === 'disqualified' ? 'bg-alarm/10 text-alarm' : 'bg-hivis/10 text-hivis' }}"><i class="ri-medal-2-line text-xl"></i></span>
                            <span class="min-w-0 grow leading-tight">
                                <span class="block truncate font-semibold">{{ $a->exam->title }}</span>
                                <span class="text-xs text-mist">{{ $a->submitted_at?->locale('id')->translatedFormat('j M Y, H:i') }}@if ($a->attempt_no > 1) · percobaan {{ $a->attempt_no }} @endif</span>
                            </span>
                            @if ($a->exam->showsScore() && $a->status === 'submitted')
                                <span class="text-right leading-tight"><span class="block font-display text-xl font-bold whitespace-nowrap {{ $a->passed() ? 'text-neon' : 'text-hivis' }}">{{ rtrim(rtrim(number_format($a->score, 2, ',', ''), '0'), ',') }}</span><span class="text-[11px] whitespace-nowrap text-mist">{{ $a->passed() ? 'Lulus' : 'Belum lulus' }}</span></span>
                            @else
                                <span class="{{ \App\Models\ExamAttempt::STATUSES[$a->status]['chip'] }}">{{ $a->status === 'submitted' ? 'Terkirim' : \App\Models\ExamAttempt::STATUSES[$a->status]['label'] }}</span>
                            @endif
                        </a>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-mist">Riwayat ujian Anda akan muncul di sini.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="order-1 lg:sticky lg:top-24 lg:order-none">
            <form method="POST" action="{{ route('exams.code') }}" class="rounded-[26px] border border-hivis/30 bg-bench p-5 lg:p-6" x-data="codeField(@js(old('code', '')))">
                @csrf
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-hivis/15 text-hivis"><i class="ri-key-2-line text-2xl"></i></span>
                    <div class="leading-tight"><p class="font-display text-lg font-semibold">Punya kode ujian?</p><p class="text-sm text-mist">Nama & NRP otomatis dari akun Anda</p></div>
                </div>
                <label for="code" class="sr-only">Kode ujian</label>
                <input id="code" name="code" x-model="code" @input="clean()" autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="go" required placeholder="Kode ujian" class="code-cell mt-4">
                @error('code') <p class="error"><i class="ri-error-warning-line"></i>{{ $message }}</p> @enderror
                <button class="btn-hivis mt-4 w-full !min-h-13" :disabled="code.length < 3"><i class="ri-login-circle-line text-xl"></i> Masuk ujian</button>
            </form>
        </aside>
    </div>
</x-layouts.portal>
