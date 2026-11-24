<x-layouts.admin :title="'Lembar '.$attempt->user->displayName()" :subtitle="$exam->title.' · percobaan '.$attempt->attempt_no">
@php
    $s = \App\Models\ExamAttempt::STATUSES[$attempt->status];
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
@endphp
    <x-slot:actions>
        <a href="{{ route('admin.exams.show', $exam) }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-arrow-left-line text-lg"></i> Kembali</a>
    </x-slot:actions>

    <div class="grid gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <div class="a-card p-5">
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-full bg-neon font-display font-bold text-[#04170d]">{{ $attempt->user->initials() }}</span>
                    <div class="min-w-0 leading-tight"><p class="truncate font-semibold">{{ $attempt->user->displayName() }}</p><p class="text-sm text-mist"><span class="font-display">{{ $attempt->user->nrp }}</span> · {{ $attempt->user->department }}</p></div>
                </div>
                <div class="mt-4 flex items-end justify-between">
                    <span class="{{ $s['chip'] }}"><i class="{{ $s['icon'] }}"></i> {{ $s['label'] }}</span>
                    <span class="font-display text-[2.4rem] leading-none font-bold {{ $attempt->passed() ? 'text-neon' : 'text-hivis' }}">{{ $fmt($attempt->score) }}</span>
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-mist">Benar</dt><dd class="font-display">{{ $attempt->correct ?? '–' }} / {{ $attempt->total }}</dd></div>
                    <div class="flex justify-between"><dt class="text-mist">Dijawab</dt><dd class="font-display">{{ $attempt->answered }}</dd></div>
                    <div class="flex justify-between"><dt class="text-mist">Mulai</dt><dd>{{ $attempt->started_at->format('d/m H:i:s') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-mist">Selesai</dt><dd>{{ $attempt->submitted_at?->format('d/m H:i:s') ?? '–' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-mist">Lama</dt><dd class="font-display">{{ \App\Models\ExamAttempt::formatDuration($attempt->durationSeconds()) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-mist">Cara selesai</dt><dd>{{ \App\Models\ExamAttempt::REASONS[$attempt->finish_reason] ?? '–' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-mist">Keluar halaman</dt><dd class="font-display {{ $attempt->violations ? 'text-alarm' : '' }}">{{ $attempt->violations }}×</dd></div>
                </dl>
                @if ($attempt->violation_log)
                    <details class="mt-3 rounded-xl bg-white/[0.04] px-3 py-2 text-xs text-mist"><summary class="cursor-pointer font-semibold text-fog">Waktu keluar halaman</summary>
                        <p class="mt-2 font-display leading-relaxed">{{ collect($attempt->violation_log)->pluck('t')->implode(' · ') }}</p></details>
                @endif
                <p class="mt-3 truncate text-[11px] text-mist" title="{{ $attempt->user_agent }}">{{ $attempt->ip }} · {{ $attempt->user_agent }}</p>
            </div>
            <form method="POST" action="{{ route('admin.exams.attempt.action', [$exam, $attempt]) }}" class="a-card grid gap-2 p-4" onsubmit="return confirm('Lanjutkan tindakan ini?')">
                @csrf
                @if ($attempt->isActive())
                    <button name="action" value="extend" class="btn-ghost !min-h-10 text-sm"><i class="ri-timer-flash-line text-hivis"></i> Tambah 10 menit</button>
                    <button name="action" value="finish" class="btn-ghost !min-h-10 text-sm"><i class="ri-send-plane-2-line text-neon"></i> Kumpulkan sekarang</button>
                @endif
                @if ($attempt->status === 'disqualified')
                    <button name="action" value="restore" class="btn-ghost !min-h-10 text-sm"><i class="ri-arrow-go-back-line text-neon"></i> Batalkan diskualifikasi</button>
                @else
                    <button name="action" value="disqualify" class="btn-ghost !min-h-10 text-sm text-red-300"><i class="ri-forbid-2-line"></i> Diskualifikasi</button>
                @endif
            </form>
        </aside>

        <section class="a-card overflow-hidden">
            <div class="flex flex-wrap gap-2 border-b border-white/[0.06] p-4">
                @foreach ($answers as $a)
                    <a href="#s{{ $a->position }}" class="grid h-9 w-9 place-items-center rounded-lg font-display text-sm font-semibold {{ $a->choice === null ? 'bg-white/[0.04] text-mist' : ($attempt->isActive() ? 'bg-sky-400/15 text-sky-300' : ($a->is_correct ? 'bg-neon/15 text-neon' : 'bg-alarm/15 text-red-300')) }} {{ $a->flagged ? 'ring-1 ring-hivis' : '' }}">{{ $a->position }}</a>
                @endforeach
            </div>
            <ol class="divide-y divide-white/[0.05]">
                @foreach ($answers as $a)
                    @php $q = $a->question; $opts = $q->options; @endphp
                    <li id="s{{ $a->position }}" class="scroll-mt-24 p-5">
                        <p class="flex flex-wrap items-center gap-2 text-xs text-mist">
                            <span class="font-display text-sm font-bold text-fog">No. {{ $a->position }}</span> · {{ $q->set->name }} #{{ $q->id }}
                            @if ($a->flagged) <span class="chip chip-penting"><i class="ri-flag-2-fill"></i> ragu</span> @endif
                            @if ($a->answered_at) <span>dijawab {{ $a->answered_at->format('H:i:s') }}</span> @endif
                            <a href="{{ route('admin.exams.bank.edit', $q) }}" class="ml-auto font-semibold text-neon">Ubah soal</a>
                        </p>
                        <p class="mt-2 leading-relaxed whitespace-pre-line">{{ $q->body }}</p>
                        @if ($q->imageUrl()) <img src="{{ $q->imageUrl() }}" alt="" class="mt-3 max-h-56 rounded-xl bg-white object-contain" loading="lazy"> @endif
                        <ul class="mt-3 grid gap-1.5 text-sm md:grid-cols-2">
                            @foreach (str_split($a->option_order) as $i => $key)
                                @php $right = $key === $q->answer; $mine = $key === $a->choice; @endphp
                                <li class="flex gap-2 rounded-xl px-3 py-2 {{ $right ? 'bg-neon/10 text-ink ring-1 ring-neon/30' : ($mine ? 'bg-alarm/10 text-red-200 ring-1 ring-alarm/30' : 'bg-white/[0.03] text-mist') }}">
                                    <span class="font-display font-bold">{{ \App\Models\Question::LETTERS[$i] }}</span><span class="grow">{{ $opts[$key] ?? '' }}</span>
                                    @if ($mine) <i class="ri-user-3-fill" title="Jawaban peserta"></i> @endif
                                    @if ($right) <i class="ri-key-2-line text-neon" title="Kunci"></i> @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.admin>
