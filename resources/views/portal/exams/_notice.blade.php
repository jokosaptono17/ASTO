{{-- Pemberitahuan otomatis saat login: ada ujian yang harus dikerjakan --}}
@if ($examNotice)
    @php $exam = $examNotice->exam; $live = $examNotice->attempt; @endphp
    <div x-data="{ open: false }" x-init="setTimeout(() => { open = true; document.documentElement.style.overflow = 'hidden' }, 450)"
         x-show="open" x-cloak @keydown.escape.window="open = false; document.documentElement.style.overflow = ''"
         class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="exam-notice-title">
        <div x-show="open" x-transition.opacity.duration.300ms class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="open = false; document.documentElement.style.overflow = ''"></div>

        <div x-show="open" x-transition:enter="transition duration-500 ease-[cubic-bezier(.2,1.2,.4,1)]" x-transition:enter-start="opacity-0 translate-y-16 sm:translate-y-6 sm:scale-90" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition duration-200" x-transition:leave-end="opacity-0 translate-y-10"
             class="relative w-full max-w-[440px] overflow-hidden rounded-t-[30px] border border-hivis/40 bg-bench pb-[env(safe-area-inset-bottom)] shadow-[0_0_80px_-16px_rgba(255,194,61,.55)] sm:rounded-[30px]">
            <div class="relative overflow-hidden px-6 pt-7 pb-6 text-center">
                <div class="absolute inset-0 bg-[radial-gradient(80%_70%_at_50%_0%,rgba(255,194,61,.22),transparent_70%)]"></div>
                <x-contour class="absolute -top-10 left-1/2 h-48 w-[520px] -translate-x-1/2 opacity-25" />
                <span class="exam-medal relative mx-auto !h-20 !w-20 !rounded-[26px] !text-[44px]"><i class="ri-medal-2-fill"></i></span>
                <p class="relative mt-4 text-sm font-semibold text-hivis">{{ $live ? 'Ujian Anda belum selesai' : 'Anda ditunjuk sebagai peserta' }}</p>
                <h2 id="exam-notice-title" class="relative mt-1 font-display text-[1.6rem] leading-tight font-bold text-balance">{{ $exam->title }}</h2>
                @if ($examNotice->label)
                    <p class="relative mt-2 inline-flex items-center gap-1.5 rounded-full border border-hivis/30 bg-hivis/10 px-3 py-1 text-sm font-semibold text-hivis"><i class="ri-user-star-line"></i> {{ $examNotice->label }}</p>
                @endif
            </div>
            <dl class="mx-6 grid grid-cols-3 divide-x divide-white/[0.07] rounded-2xl border border-white/[0.08] bg-white/[0.03] text-center">
                <div class="px-2 py-3"><dt class="text-[11px] text-mist">Soal</dt><dd class="font-display text-xl font-bold">{{ $exam->questionCount() }}</dd></div>
                <div class="px-2 py-3"><dt class="text-[11px] text-mist">Waktu</dt><dd class="font-display text-xl font-bold">{{ $exam->duration_minutes }}<span class="text-sm font-medium text-mist"> mnt</span></dd></div>
                <div class="px-2 py-3"><dt class="text-[11px] text-mist">{{ $live ? 'Sisa' : 'Tutup' }}</dt>
                    <dd class="font-display text-xl font-bold text-hivis">
                        @if ($live) <span x-data="countdown({{ $live->deadline_at->timestamp }})" x-text="clock"></span>
                        @elseif ($exam->closes_at) {{ $exam->closes_at->format('H:i') }}@if (! $exam->closes_at->isToday())<span class="block text-[11px] font-medium text-mist">{{ $exam->closes_at->locale('id')->translatedFormat('j M') }}</span>@endif
                        @else – @endif
                    </dd>
                </div>
            </dl>
            <p class="mx-6 mt-4 flex gap-2.5 text-sm leading-relaxed text-fog">
                <i class="ri-key-2-line mt-0.5 text-lg text-hivis"></i>
                <span>{{ $live ? 'Lanjutkan dari soal terakhir; jawaban Anda sudah tersimpan dan waktu tetap berjalan.' : 'Siapkan kode ujian dari pengawas. Nama & NRP otomatis dari akun Anda.' }}</span>
            </p>
            <div class="grid gap-2 p-6 pt-5">
                <a href="{{ $live ? route('exams.room', $live) : route('exams.show', $exam) }}" class="btn-hivis !min-h-14 text-base"><i class="ri-quill-pen-line text-xl"></i> {{ $live ? 'Lanjutkan ujian' : 'Kerjakan sekarang' }}</a>
                <button type="button" @click="open = false; document.documentElement.style.overflow = ''" class="btn-ghost !min-h-12 text-sm">Nanti saja</button>
            </div>
        </div>
    </div>
@endif
