<x-layouts.exam :title="$exam->title">
@php
    $cfg = [
        'id' => $attempt->id,
        'paper' => $paper,
        'deadline' => $attempt->deadline_at->getTimestampMs(),
        'serverNow' => now()->getTimestampMs(),
        'maxViolations' => (int) $exam->max_violations,
        'violations' => (int) $attempt->violations,
        'allowClear' => false,
        'urls' => [
            'answer' => route('exams.answer', $attempt), 'sync' => route('exams.sync', $attempt),
            'violation' => route('exams.violation', $attempt), 'submit' => route('exams.submit', $attempt),
            'result' => route('exams.result', $attempt),
        ],
    ];
@endphp
<div x-data="examRoom(@js($cfg))" x-cloak @keydown.window="onKey($event)" class="min-h-svh pb-[calc(92px+env(safe-area-inset-bottom))] lg:pb-10">

    {{-- Bilah atas --}}
    <header class="sticky top-0 z-30 border-b border-white/[0.07] bg-pit/90 pt-[env(safe-area-inset-top)] backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 lg:px-8">
            <span class="hidden h-10 w-10 shrink-0 place-items-center rounded-xl bg-hivis/15 text-hivis sm:grid"><i class="ri-medal-2-fill text-xl"></i></span>
            <div class="min-w-0 grow leading-tight">
                <p class="truncate text-[13px] text-mist">{{ $exam->title }}</p>
                <p class="font-display text-[17px] font-bold">Soal <span x-text="i + 1"></span><span class="text-mist">/<span x-text="q.length"></span></span></p>
            </div>
            <span class="flex items-center gap-1.5 text-xs" :class="{ saved: 'text-mist', saving: 'text-fog', offline: 'text-hivis' }[status]" role="status" aria-live="polite">
                <i :class="{ saved: 'ri-cloud-line', saving: 'ri-loader-4-line animate-spin', offline: 'ri-wifi-off-line' }[status]" class="text-lg"></i>
                <span class="hidden sm:inline" x-text="{ saved: 'Tersimpan', saving: 'Menyimpan…', offline: 'Offline · disimpan di HP' }[status]"></span>
            </span>
            <span class="flex h-10 items-center gap-1.5 rounded-full px-3.5 font-display text-lg font-bold tabular-nums ring-1"
                  :class="{ neon: 'bg-neon/10 text-neon ring-neon/30', hivis: 'bg-hivis/15 text-hivis ring-hivis/40', alarm: 'timer-alarm bg-alarm/15 text-alarm ring-alarm/50' }[urgency]"
                  :aria-label="`Sisa waktu ${clock}`">
                <i class="ri-timer-line text-lg"></i><span x-text="clock"></span>
            </span>
            <button type="button" @click="navOpen = true" class="relative grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-white/10 bg-white/[0.05] lg:hidden" aria-label="Daftar soal">
                <i class="ri-layout-grid-line text-xl"></i>
                <span x-show="flagged" class="absolute -top-1 -right-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-hivis px-1 text-[10px] font-bold text-[#1a1203]" x-text="flagged"></span>
            </button>
        </div>
        <div class="h-1 bg-white/[0.05]"><div class="h-full bg-gradient-to-r from-neon to-neon-soft transition-[width] duration-500" :style="`width: ${progress}%`"></div></div>
    </header>

    <div class="mx-auto max-w-6xl lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8 lg:px-8 lg:pt-8">
        {{-- Soal --}}
        <main id="soal" class="scroll-mt-20 px-4 pt-5 lg:px-0 lg:pt-0">
            <template x-for="item in [cur]" :key="item.id">
                <article :class="dir > 0 ? 'q-in-r' : 'q-in-l'" class="lg:rounded-[26px] lg:border lg:border-white/[0.07] lg:bg-bench lg:p-7">
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg bg-white/[0.06] px-2.5 py-1 font-display text-sm font-semibold text-fog">No. <span x-text="item.no"></span></span>
                        <button type="button" @click="toggleFlag()" class="inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-semibold ring-1 transition"
                                :class="item.flagged ? 'bg-hivis/15 text-hivis ring-hivis/50' : 'text-mist ring-white/10 hover:text-fog'" :aria-pressed="item.flagged">
                            <i :class="item.flagged ? 'ri-flag-2-fill' : 'ri-flag-2-line'"></i> Ragu-ragu
                        </button>
                        <div class="ml-auto flex items-center rounded-full ring-1 ring-white/10">
                            <button type="button" @click="zoomOut()" class="grid h-9 w-9 place-items-center text-mist hover:text-ink" aria-label="Perkecil huruf"><span class="font-display text-sm">A</span></button>
                            <span class="h-4 w-px bg-white/10"></span>
                            <button type="button" @click="zoomIn()" class="grid h-9 w-9 place-items-center text-mist hover:text-ink" aria-label="Perbesar huruf"><span class="font-display text-lg">A</span></button>
                        </div>
                    </div>

                    <p class="mt-4 leading-relaxed font-medium whitespace-pre-line text-ink" :style="`font-size: ${17 * scale}px`" x-text="item.body"></p>

                    <template x-if="item.image">
                        <button type="button" @click="zoom = item.image" class="group relative mt-4 block w-full overflow-hidden rounded-2xl border border-white/10 bg-white">
                            <img :src="item.image" alt="Gambar soal" class="mx-auto max-h-[46svh] w-auto object-contain" loading="eager" draggable="false">
                            <span class="absolute right-2 bottom-2 inline-flex items-center gap-1 rounded-full bg-black/70 px-2.5 py-1 text-xs text-white"><i class="ri-zoom-in-line"></i> Perbesar</span>
                        </button>
                    </template>

                    <div class="mt-5 space-y-2.5" role="radiogroup" :aria-label="`Pilihan jawaban soal ${item.no}`">
                        <template x-for="opt in item.options" :key="opt.key">
                            <button type="button" role="radio" :aria-checked="item.choice === opt.key" @click="choose(opt.key)" class="exam-opt">
                                <span class="letter" x-text="opt.label"></span>
                                <span class="min-w-0 grow pt-1.5 leading-snug whitespace-pre-line text-fog" :class="item.choice === opt.key && '!text-ink'" :style="`font-size: ${15.5 * scale}px`" x-text="opt.text"></span>
                                <i x-show="item.choice === opt.key" class="ri-checkbox-circle-fill mt-1.5 text-xl text-neon"></i>
                                <span x-show="advancing && item.choice === opt.key" class="advance-bar" aria-hidden="true"></span>
                            </button>
                        </template>
                    </div>

                    {{-- Lanjut otomatis setelah memilih --}}
                    <div class="mt-3 flex items-center justify-end gap-2 text-xs text-mist">
                        <span x-show="advancing" x-transition.opacity class="mr-auto text-neon-soft" aria-live="polite"><i class="ri-arrow-right-double-line"></i> <span x-text="i < q.length - 1 ? 'Lanjut ke soal berikutnya…' : 'Selesai — memeriksa soal kosong…'"></span></span>
                        <span>Lanjut otomatis</span>
                        <button type="button" role="switch" :aria-checked="auto" @click="toggleAuto()" aria-label="Lanjut otomatis ke soal berikutnya setelah memilih jawaban"
                                class="relative h-6 w-11 shrink-0 rounded-full transition" :class="auto ? 'bg-neon' : 'bg-white/15'">
                            <span class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform" :class="auto && 'translate-x-5'"></span>
                        </button>
                    </div>

                    {{-- Navigasi desktop --}}
                    <div class="mt-7 hidden items-center gap-3 lg:flex">
                        <button type="button" @click="prev()" :disabled="i === 0" class="btn-ghost"><i class="ri-arrow-left-line text-lg"></i> Sebelumnya</button>
                        <span class="grow text-center text-xs text-mist">Tombol <kbd class="rounded border border-white/15 px-1 font-display">A</kbd>–<kbd class="rounded border border-white/15 px-1 font-display">D</kbd> memilih, <kbd class="rounded border border-white/15 px-1 font-display">←</kbd> <kbd class="rounded border border-white/15 px-1 font-display">→</kbd> pindah soal</span>
                        <button type="button" @click="next()" class="btn-neon" x-show="i < q.length - 1">Berikutnya <i class="ri-arrow-right-line text-lg"></i></button>
                        <button type="button" @click="confirmOpen = true" class="btn-hivis" x-show="i === q.length - 1"><i class="ri-send-plane-2-line text-lg"></i> Selesai</button>
                    </div>
                </article>
            </template>
        </main>

        {{-- Panel samping (desktop) --}}
        <aside class="hidden lg:block">
            <div class="sticky top-28 space-y-4">
                <div class="rounded-[22px] border border-white/[0.07] bg-bench p-5">
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-neon/10 py-2"><p class="font-display text-xl font-bold text-neon" x-text="answered"></p><p class="text-[11px] text-mist">dijawab</p></div>
                        <div class="rounded-xl bg-hivis/10 py-2"><p class="font-display text-xl font-bold text-hivis" x-text="flagged"></p><p class="text-[11px] text-mist">ragu</p></div>
                        <div class="rounded-xl bg-white/[0.05] py-2"><p class="font-display text-xl font-bold" x-text="empty"></p><p class="text-[11px] text-mist">kosong</p></div>
                    </div>
                    <div class="mt-4 grid max-h-[46vh] grid-cols-6 gap-1.5 overflow-y-auto pr-1">
                        <template x-for="(x, n) in q" :key="x.id">
                            <button type="button" @click="go(n)" class="nav-cell" :data-s="x.flagged ? 'flag' : (x.choice ? 'done' : 'empty')" :aria-current="n === i" x-text="n + 1" :aria-label="`Soal ${n + 1}`"></button>
                        </template>
                    </div>
                    <button type="button" @click="confirmOpen = true" class="btn-hivis mt-5 w-full"><i class="ri-send-plane-2-line text-lg"></i> Kumpulkan</button>
                </div>
                <p class="flex items-center gap-2 px-1 text-xs text-mist"><i class="ri-user-3-line"></i> {{ auth()->user()->displayName() }} · <span class="font-display">{{ auth()->user()->nrp }}</span></p>
            </div>
        </aside>
    </div>

    {{-- Bilah bawah (HP) --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-white/[0.07] bg-pit/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl lg:hidden" aria-label="Navigasi soal">
        <div class="flex items-center gap-2 px-4 py-3">
            <button type="button" @click="prev()" :disabled="i === 0" class="btn-ghost !min-h-14 !w-14 !px-0 disabled:opacity-30" aria-label="Soal sebelumnya"><i class="ri-arrow-left-line text-2xl"></i></button>
            <button type="button" @click="navOpen = true" class="flex min-h-14 grow flex-col items-center justify-center rounded-2xl text-xs text-mist">
                <span class="font-display text-base font-bold text-ink"><span x-text="answered"></span>/<span x-text="q.length"></span></span>dijawab
            </button>
            <button type="button" @click="next()" class="btn-neon !min-h-14 grow-[2] text-base" x-show="i < q.length - 1">Berikutnya <i class="ri-arrow-right-line text-xl"></i></button>
            <button type="button" @click="confirmOpen = true" class="btn-hivis !min-h-14 grow-[2] text-base" x-show="i === q.length - 1"><i class="ri-send-plane-2-line text-xl"></i> Selesai</button>
        </div>
    </nav>

    {{-- Daftar soal (HP) --}}
    <div x-show="navOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/70 lg:hidden" @click="navOpen = false"></div>
    <section x-show="navOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:leave="transition duration-200" x-transition:leave-end="translate-y-full"
             class="fixed inset-x-0 bottom-0 z-50 max-h-[82svh] overflow-y-auto rounded-t-[28px] border-t border-white/10 bg-bench px-5 pt-3 pb-[calc(1.25rem+env(safe-area-inset-bottom))] lg:hidden" aria-label="Daftar soal">
        <div class="mx-auto h-1.5 w-12 rounded-full bg-white/15"></div>
        <div class="mt-4 flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold">Daftar soal</h2>
            <button type="button" @click="navOpen = false" class="icon-btn" aria-label="Tutup"><i class="ri-close-line text-2xl"></i></button>
        </div>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-mist">
            <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded bg-neon/60"></span>Dijawab <b class="text-fog" x-text="answered"></b></span>
            <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded bg-hivis/70"></span>Ragu <b class="text-fog" x-text="flagged"></b></span>
            <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded border border-white/20"></span>Kosong <b class="text-fog" x-text="empty"></b></span>
        </div>
        <div class="mt-4 grid grid-cols-6 gap-2 sm:grid-cols-8">
            <template x-for="(x, n) in q" :key="x.id">
                <button type="button" @click="go(n)" class="nav-cell" :data-s="x.flagged ? 'flag' : (x.choice ? 'done' : 'empty')" :aria-current="n === i" x-text="n + 1"></button>
            </template>
        </div>
        <button type="button" @click="navOpen = false; confirmOpen = true" class="btn-hivis mt-5 w-full !min-h-14"><i class="ri-send-plane-2-line text-xl"></i> Kumpulkan jawaban</button>
    </section>

    {{-- Konfirmasi kumpul --}}
    <div x-show="confirmOpen" x-transition.opacity class="fixed inset-0 z-[60] flex items-end justify-center bg-black/75 backdrop-blur-sm sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
        <div @click.outside="!submitting && (confirmOpen = false)" class="w-full max-w-md rounded-t-[28px] border border-white/10 bg-bench p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] sm:rounded-[28px]">
            <h2 id="confirm-title" class="font-display text-xl font-bold">Kumpulkan jawaban?</h2>
            <p class="mt-1 text-sm text-mist">Setelah dikumpulkan, jawaban tidak bisa diubah.</p>
            <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-2xl bg-neon/10 py-3"><dd class="font-display text-2xl font-bold text-neon" x-text="answered"></dd><dt class="text-xs text-mist">dijawab</dt></div>
                <div class="rounded-2xl bg-hivis/10 py-3"><dd class="font-display text-2xl font-bold text-hivis" x-text="flagged"></dd><dt class="text-xs text-mist">ragu-ragu</dt></div>
                <div class="rounded-2xl py-3" :class="empty ? 'bg-alarm/10' : 'bg-white/[0.05]'"><dd class="font-display text-2xl font-bold" :class="empty && 'text-alarm'" x-text="empty"></dd><dt class="text-xs text-mist">kosong</dt></div>
            </dl>
            <p x-show="empty" class="mt-4 flex gap-2 rounded-xl bg-alarm/10 px-3 py-2.5 text-sm text-red-200"><i class="ri-error-warning-line text-lg"></i><span><b x-text="empty"></b> soal belum dijawab dan akan dihitung salah.</span></p>
            <p x-show="submitError" x-text="submitError" class="mt-4 rounded-xl bg-hivis/10 px-3 py-2.5 text-sm text-hivis"></p>
            <div class="mt-5 grid gap-2">
                <button type="button" @click="submit()" :disabled="submitting" class="btn-hivis !min-h-14 text-base">
                    <i :class="submitting ? 'ri-loader-4-line animate-spin' : 'ri-send-plane-2-line'" class="text-xl"></i><span x-text="submitting ? 'Mengirim…' : 'Ya, kumpulkan'"></span>
                </button>
                <button type="button" x-show="empty" @click="firstEmpty()" :disabled="submitting" class="btn-ghost">Periksa soal kosong</button>
                <button type="button" x-show="!empty" @click="confirmOpen = false" :disabled="submitting" class="btn-ghost">Periksa lagi</button>
            </div>
        </div>
    </div>

    {{-- Peringatan keluar halaman --}}
    <div x-show="warn.show" x-transition.opacity class="fixed inset-0 z-[70] flex items-center justify-center bg-[#1a0606]/95 p-6 backdrop-blur" role="alertdialog" aria-modal="true" aria-labelledby="warn-title">
        <div class="w-full max-w-sm text-center">
            <div class="hazard mx-auto h-3 w-40 rounded-full"></div>
            <span class="mx-auto mt-6 grid h-20 w-20 place-items-center rounded-full bg-alarm/15 text-alarm ring-1 ring-alarm/50"><i class="ri-eye-off-line text-[40px]"></i></span>
            <h2 id="warn-title" class="mt-5 font-display text-2xl font-bold">Anda meninggalkan halaman ujian</h2>
            <p class="mt-2 leading-relaxed text-fog" x-text="warn.text"></p>
            <button type="button" @click="warn.show = false" class="btn-neon mt-6 w-full !min-h-14">Kembali ke soal</button>
        </div>
    </div>

    {{-- Gambar diperbesar --}}
    <div x-show="zoom" x-transition.opacity @click="zoom = null" class="fixed inset-0 z-[65] flex items-center justify-center bg-black/95 p-3" role="dialog" aria-label="Gambar soal">
        <img :src="zoom" alt="Gambar soal diperbesar" class="max-h-full max-w-full rounded-xl bg-white object-contain" draggable="false">
        <button type="button" class="absolute top-[max(1rem,env(safe-area-inset-top))] right-4 grid h-12 w-12 place-items-center rounded-full bg-white/10 text-white" aria-label="Tutup"><i class="ri-close-line text-3xl"></i></button>
    </div>

    {{-- Mengirim (waktu habis) --}}
    <div x-show="submitting && !confirmOpen" class="fixed inset-0 z-[80] grid place-items-center bg-pit/95">
        <div class="text-center"><i class="ri-loader-4-line animate-spin text-5xl text-neon"></i><p class="mt-3 font-display text-lg font-semibold">Mengumpulkan jawaban…</p><p class="text-sm text-mist">Jangan tutup halaman ini.</p></div>
    </div>
</div>
</x-layouts.exam>
