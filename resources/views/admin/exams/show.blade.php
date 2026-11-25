<x-layouts.admin :title="$exam->title" heading="Pantau ujian" :hide-heading="true">
@php
    $st = $exam->stateInfo();
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
@endphp
    @include('admin.exams._tabs')

    {{-- Kepala --}}
    <section class="relative z-10 mb-5 rounded-[26px] border {{ $exam->isOpen() ? 'border-hivis/35' : 'border-white/[0.07]' }} bg-bench">
        {{-- Hiasan dipotong di lapisan sendiri agar menu "Aksi lain" tidak ikut terpotong --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-[26px]" aria-hidden="true">
            @if ($exam->isOpen()) <span class="exam-card-glow"></span> @endif
            <x-contour class="absolute -top-16 -right-24 h-72 w-[560px] opacity-20" />
        </div>
        <div class="relative flex flex-wrap items-start gap-5 p-5 lg:p-7">
            <div class="min-w-0 grow">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="{{ $st['chip'] }}"><i class="{{ $st['icon'] }}"></i> {{ $st['label'] }}</span>
                    @if ($exam->isArchived()) <span class="chip bg-white/[0.08] text-mist"><i class="ri-archive-line"></i> Diarsipkan {{ $exam->archived_at->locale('id')->translatedFormat('j M Y') }}</span> @endif
                    @if ($exam->category) <a href="{{ route('admin.exams.index', ['kategori' => $exam->category]) }}" class="chip bg-white/[0.06] text-fog hover:text-ink"><i class="ri-price-tag-3-line"></i> {{ $exam->category }}</a> @endif
                    <span class="chip bg-white/[0.06] text-fog"><i class="{{ $exam->audience === 'open' ? 'ri-door-open-line' : 'ri-user-star-line' }}"></i> {{ $exam->audience === 'open' ? 'Terbuka dengan kode' : 'Peserta ditunjuk' }}</span>
                    <span class="chip bg-white/[0.06] text-fog"><i class="ri-eye-line"></i> Hasil: {{ \App\Models\Exam::RESULT_MODES[$exam->result_mode]['label'] }}</span>
                </div>
                <h1 class="mt-3 font-display text-[1.7rem] leading-tight font-bold lg:text-[2.1rem]">{{ $exam->title }}</h1>
                <p class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-sm text-mist">
                    <span><i class="ri-calendar-line"></i> {{ $exam->scheduleLabel() }}</span>
                    <span><i class="ri-timer-line"></i> {{ $exam->duration_minutes }} menit</span>
                    <span><i class="ri-file-list-3-line"></i> {{ $count }} soal · {{ $exam->sets->pluck('name')->implode(', ') ?: 'belum ada paket' }}</span>
                    <span><i class="ri-award-line"></i> lulus ≥ {{ $exam->pass_score }}</span>
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    @if (! $exam->is_published)
                        <form method="POST" action="{{ route('admin.exams.publish', $exam) }}">@csrf<button class="btn-neon !min-h-11 text-sm"><i class="ri-send-plane-2-line text-lg"></i> Terbitkan</button></form>
                    @endif
                    @if ($exam->is_published)
                        <form method="POST" action="{{ route('admin.exams.toggle', $exam) }}" onsubmit="return confirm(@js($exam->isOpen() ? 'Tutup ujian sekarang? Peserta baru tidak bisa masuk.' : 'Buka ujian sekarang?'))">@csrf
                            <button class="{{ $exam->isOpen() ? 'btn-ghost' : 'btn-hivis' }} !min-h-11 text-sm"><i class="{{ $exam->isOpen() ? 'ri-stop-circle-line text-alarm' : 'ri-play-circle-line' }} text-lg"></i> {{ $exam->isOpen() ? 'Tutup sekarang' : 'Buka sekarang' }}</button>
                        </form>
                    @endif
                    <a href="{{ route('admin.exams.screen', $exam) }}" target="_blank" class="btn-ghost !min-h-11 text-sm"><i class="ri-tv-2-line text-lg text-neon"></i> Layar proyektor</a>
                    <a href="{{ route('admin.exams.edit', $exam) }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-edit-line text-lg"></i> Ubah</a>
                    <a href="{{ route('admin.exams.export', $exam) }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-file-excel-2-line text-lg text-neon"></i> Ekspor Excel</a>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="btn-ghost !min-h-11 !px-3 text-sm" aria-label="Aksi lain"><i class="ri-more-2-fill text-lg"></i></button>
                        <div x-show="open" x-cloak @click.outside="open = false" class="absolute left-0 z-20 mt-1 w-64 overflow-hidden rounded-xl border border-white/10 bg-ridge p-1 shadow-2xl">
                            <form method="POST" action="{{ route('admin.exams.regrade', $exam) }}">@csrf<button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-white/[0.06]"><i class="ri-calculator-line text-neon"></i> Hitung ulang nilai</button></form>
                            <a href="{{ route('admin.exams.create', ['salin' => $exam->id]) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-white/[0.06]"><i class="ri-file-copy-line text-neon"></i> Salin jadi ujian baru</a>
                            @if ($exam->is_published)
                                <form method="POST" action="{{ route('admin.exams.publish', $exam) }}">@csrf<button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-white/[0.06]"><i class="ri-draft-line text-mist"></i> Tarik jadi draf</button></form>
                            @endif
                            @if ($exam->isArchived() || $exam->canArchive())
                                <form method="POST" action="{{ route('admin.exams.archive', $exam) }}">@csrf<button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-white/[0.06]"><i class="{{ $exam->isArchived() ? 'ri-inbox-unarchive-line' : 'ri-archive-line' }} text-mist"></i> {{ $exam->isArchived() ? 'Keluarkan dari arsip' : 'Arsipkan' }}</button></form>
                            @endif
                            <div class="my-1 h-px bg-white/[0.07]"></div>
                            <form method="POST" action="{{ route('admin.exams.destroy', $exam) }}" onsubmit="return confirm(@js('Pindahkan ujian ini ke Tempat sampah? Peserta tidak bisa melihatnya lagi. Masih bisa dipulihkan selama '.\App\Models\Exam::TRASH_DAYS.' hari dari halaman Event ujian.'))">@csrf @method('DELETE')
                                <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-300 hover:bg-alarm/10"><i class="ri-delete-bin-line"></i> Hapus (ke Tempat sampah)</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full shrink-0 rounded-2xl border border-hivis/30 bg-black/25 p-4 text-center sm:w-auto sm:min-w-[240px]" x-data="{ copied: false }">
                <p class="text-xs text-mist">Kode ujian</p>
                <p class="mt-1 font-display text-[2.4rem] leading-none font-bold tracking-[.16em] text-hivis">{{ $exam->code }}</p>
                <button type="button" @click="navigator.clipboard?.writeText(@js($exam->code)); copied = true; setTimeout(() => copied = false, 1500)" class="mt-3 text-sm font-semibold text-fog hover:text-ink">
                    <i :class="copied ? 'ri-check-line text-neon' : 'ri-file-copy-line'"></i> <span x-text="copied ? 'Tersalin' : 'Salin kode'"></span>
                </button>
            </div>
        </div>
    </section>

    <div x-data="examLive(@js(['feed' => $feed, 'feedUrl' => route('admin.exams.feed', $exam), 'actionUrl' => route('admin.exams.attempt.action', [$exam, '__ID__'])]))" class="space-y-5">
        {{-- Statistik --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ([['participants', 'Peserta', 'ri-team-line', 'text-ink'], ['waiting', 'Belum mulai', 'ri-hourglass-line', 'text-mist'], ['live', 'Mengerjakan', 'ri-edit-2-line', 'text-sky-300'], ['done', 'Selesai', 'ri-checkbox-circle-line', 'text-neon'], ['avg', 'Rata-rata', 'ri-bar-chart-2-line', 'text-hivis'], ['max', 'Tertinggi', 'ri-trophy-line', 'text-hivis']] as [$k, $l, $ic, $tone])
                <div class="a-card p-4">
                    <p class="flex items-center gap-1.5 text-xs text-mist"><i class="{{ $ic }} {{ $tone }}"></i> {{ $l }}</p>
                    <p class="mt-1 font-display text-[1.9rem] leading-none font-bold {{ $tone }}" x-text="num(stats.{{ $k }})"></p>
                    @if ($k === 'done') <p class="mt-1 text-xs text-mist" x-show="stats.done" x-text="`${stats.pass} lulus (${Math.round(stats.pass / stats.done * 100)}%)`"></p> @endif
                    @if ($k === 'live') <p class="mt-1 text-xs text-mist" x-show="stats.dq" x-text="`${stats.dq} diskualifikasi`"></p> @endif
                </div>
            @endforeach
        </div>

        <div x-data="{ view: 'live', fromHash() { this.view = { '#peringkat': 'rank', '#analisis': 'items' }[location.hash] || 'live' } }" x-init="fromHash()" @hashchange.window="fromHash()">
            <div class="mb-4 flex flex-wrap items-center gap-1 rounded-2xl border border-white/[0.07] bg-bench p-1 sm:inline-flex">
                @foreach (['live' => ['Pantau langsung', 'ri-broadcast-line', ''], 'rank' => ['Peringkat', 'ri-trophy-line', '#peringkat'], 'items' => ['Analisis soal', 'ri-pie-chart-2-line', '#analisis']] as $k => [$l, $ic, $hash])
                    <button type="button" @click="view = '{{ $k }}'; history.replaceState(null, '', '{{ $hash ?: '#' }}')" class="flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition" :class="view === '{{ $k }}' ? 'bg-neon text-[#04170d]' : 'text-fog hover:text-ink'"><i class="{{ $ic }}"></i> {{ $l }}</button>
                @endforeach
            </div>

            {{-- Pantau langsung --}}
            <section x-show="view === 'live'" class="a-card overflow-hidden">
                <div class="flex flex-wrap items-center gap-2 border-b border-white/[0.06] p-4">
                    @foreach (['semua' => 'Semua', 'mengerjakan' => 'Mengerjakan', 'belum' => 'Belum mulai', 'selesai' => 'Selesai', 'dq' => 'Diskualifikasi'] as $k => $l)
                        <button type="button" @click="tab = '{{ $k }}'" class="rounded-full px-3 py-1.5 text-sm font-semibold transition" :class="tab === '{{ $k }}' ? 'bg-white/[0.12] text-ink' : 'text-mist hover:text-fog'">{{ $l }} <span class="opacity-60" x-text="count('{{ $k }}')"></span></button>
                    @endforeach
                    <div class="relative ml-auto w-full sm:w-64"><i class="ri-search-line pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-mist"></i><input x-model="q" type="search" placeholder="Cari peserta…" class="field !py-2 !pl-9 text-sm"></div>
                </div>
                <p x-show="flash" x-transition class="border-b border-white/[0.06] bg-neon/10 px-4 py-2 text-sm text-neon" x-text="flash"></p>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px]">
                        <thead><tr><th class="a-th">Peserta</th><th class="a-th">Status</th><th class="a-th w-[200px]">Progres</th><th class="a-th text-center">Sisa / lama</th><th class="a-th text-center">Keluar hlm.</th><th class="a-th text-right">Nilai</th><th class="a-th"></th></tr></thead>
                        <tbody>
                            <template x-for="r in shown" :key="r.user_id">
                                <tr class="transition" :class="busy === r.user_id && 'opacity-50'">
                                    <td class="a-td">
                                        <div class="flex items-center gap-3">
                                            <span class="relative grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/[0.06] font-display text-xs font-bold text-fog">
                                                <span x-text="r.name.split(' ').slice(0, 2).map((w) => w[0]).join('')"></span>
                                                <span x-show="r.status === 'in_progress'" class="absolute -right-0.5 -bottom-0.5 h-3 w-3 rounded-full ring-2 ring-bench" :class="r.online ? 'bg-neon' : 'bg-mist/60'" :title="r.online ? 'Aktif' : `Terakhir terlihat ${r.seen}`"></span>
                                            </span>
                                            <span class="min-w-0 leading-tight"><span class="block truncate font-medium" x-text="r.name"></span><span class="text-xs text-mist"><span class="font-display" x-text="r.nrp"></span><span x-show="r.label" x-text="` · ${r.label}`"></span><span x-show="!r.assigned" class="text-hivis"> · lewat kode</span></span></span>
                                        </div>
                                    </td>
                                    <td class="a-td">
                                        <span class="chip" :class="{ waiting: 'bg-white/10 text-mist', in_progress: 'bg-sky-400/15 text-sky-300', submitted: 'chip-tayang', disqualified: 'chip-darurat' }[r.status]"
                                              x-text="{ waiting: 'Belum mulai', in_progress: 'Mengerjakan', submitted: 'Selesai', disqualified: 'Diskualifikasi' }[r.status]"></span>
                                        <p x-show="r.reason && r.reason !== 'user'" class="mt-1 text-[11px] text-hivis" x-text="{ timeout: 'waktu habis', violations: 'batas pelanggaran', admin: 'oleh admin' }[r.reason]"></p>
                                    </td>
                                    <td class="a-td">
                                        <template x-if="r.total">
                                            <div><div class="flex justify-between text-xs text-mist"><span x-text="`${r.answered}/${r.total}`"></span><span x-text="`${Math.round(r.answered / r.total * 100)}%`"></span></div>
                                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/[0.07]"><div class="h-full rounded-full" :class="r.status === 'in_progress' ? 'bg-sky-300' : 'bg-neon'" :style="`width: ${r.answered / r.total * 100}%`"></div></div></div>
                                        </template>
                                        <span x-show="!r.total" class="text-xs text-mist">–</span>
                                    </td>
                                    <td class="a-td text-center font-display">
                                        <span x-show="r.left !== null" :class="r.left < 300 ? 'text-hivis' : 'text-sky-300'" x-text="r.left !== null ? clock(r.left) : ''"></span>
                                        <span x-show="r.left === null" class="text-mist" x-text="r.duration || '–'"></span>
                                    </td>
                                    <td class="a-td text-center"><span class="font-display font-semibold" :class="r.violations ? 'text-alarm' : 'text-mist'" x-text="r.violations"></span></td>
                                    <td class="a-td text-right font-display text-lg font-bold" :class="r.score === null ? 'text-mist' : (r.score >= {{ $exam->pass_score }} ? 'text-neon' : 'text-hivis')" x-text="r.score === null ? '–' : r.score.toLocaleString('id-ID')"></td>
                                    <td class="a-td w-10 text-right">
                                        <div x-show="r.attempt" x-data="{ m: false }" class="relative">
                                            <button type="button" @click="m = !m" class="icon-btn" aria-label="Aksi"><i class="ri-more-2-fill text-lg"></i></button>
                                            <div x-show="m" x-cloak @click.outside="m = false" @click="m = false" class="absolute right-0 z-20 mt-1 w-56 overflow-hidden rounded-xl border border-white/10 bg-ridge p-1 text-left shadow-2xl">
                                                <a :href="`{{ route('admin.exams.attempt', [$exam, '__ID__']) }}`.replace('__ID__', r.attempt)" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-white/[0.06]"><i class="ri-file-search-line text-neon"></i> Lihat lembar jawaban</a>
                                                <template x-if="r.status === 'in_progress'">
                                                    <div>
                                                        <button type="button" @click="act(r, 'extend', { minutes: 10 })" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-white/[0.06]"><i class="ri-timer-flash-line text-hivis"></i> Tambah 10 menit</button>
                                                        <button type="button" @click="act(r, 'finish')" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-white/[0.06]"><i class="ri-send-plane-2-line text-neon"></i> Kumpulkan sekarang</button>
                                                    </div>
                                                </template>
                                                <button type="button" x-show="r.status !== 'disqualified'" @click="act(r, 'disqualify')" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-300 hover:bg-alarm/10"><i class="ri-forbid-2-line"></i> Diskualifikasi</button>
                                                <button type="button" x-show="r.status === 'disqualified'" @click="act(r, 'restore')" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-white/[0.06]"><i class="ri-arrow-go-back-line text-neon"></i> Batalkan diskualifikasi</button>
                                                <button type="button" @click="act(r, 'reset')" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-300 hover:bg-alarm/10"><i class="ri-restart-line"></i> Hapus & izinkan ulang</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="!shown.length"><td colspan="7" class="a-td py-12 text-center text-mist">
                                <span x-show="!rows.length">Belum ada peserta. <a href="{{ route('admin.exams.edit', $exam) }}" class="font-semibold text-neon">Tunjuk peserta</a>{{ $exam->audience === 'open' ? ' atau bagikan kode ujian' : '' }}.</span>
                                <span x-show="rows.length">Tidak ada peserta pada filter ini.</span>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="flex items-center gap-2 border-t border-white/[0.06] px-4 py-2.5 text-xs text-mist"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-neon"></span> Diperbarui otomatis · <span x-text="at"></span></p>
            </section>

            {{-- Peringkat --}}
            <section x-show="view === 'rank'" x-cloak class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="a-card overflow-hidden">
                    @if ($ranking->isEmpty())
                        <p class="py-14 text-center text-mist"><i class="ri-trophy-line text-4xl"></i><br>Peringkat muncul setelah peserta mengumpulkan.</p>
                    @else
                        @if ($ranking->count() >= 3)
                            <div class="grid grid-cols-3 items-end gap-3 border-b border-white/[0.06] px-5 pt-6 pb-0">
                                @foreach ([1, 0, 2] as $p)
                                    @php $a = $ranking[$p]; $h = ['h-28', 'h-20', 'h-14'][$p]; $tone = ['text-hivis', 'text-fog', 'text-[#e3a36b]'][$p]; @endphp
                                    <div class="text-center">
                                        <p class="truncate text-sm font-semibold">{{ $a->user->displayName() }}</p>
                                        <p class="font-display text-xl font-bold {{ $tone }}">{{ $fmt($a->score) }}</p>
                                        <div class="mt-2 grid {{ $h }} place-items-center rounded-t-2xl {{ $p === 0 ? 'bg-gradient-to-b from-hivis/30 to-hivis/5' : 'bg-white/[0.05]' }}"><span class="font-display text-3xl font-bold {{ $tone }}">{{ $p + 1 }}</span></div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <table class="w-full">
                            <thead><tr><th class="a-th w-14">#</th><th class="a-th">Peserta</th><th class="a-th text-right">Benar</th><th class="a-th text-right">Lama</th><th class="a-th text-right">Nilai</th></tr></thead>
                            <tbody>
                                @foreach ($ranking as $a)
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="a-td font-display text-lg font-bold {{ $a->rank <= 3 ? 'text-hivis' : 'text-mist' }}">{{ $a->rank }}</td>
                                        <td class="a-td"><a href="{{ route('admin.exams.attempt', [$exam, $a]) }}" class="hover:text-neon"><span class="block font-medium">{{ $a->user->displayName() }}</span><span class="text-xs text-mist"><span class="font-display">{{ $a->user->nrp }}</span> · {{ $a->user->department }}</span></a></td>
                                        <td class="a-td text-right font-display">{{ $a->correct }}/{{ $a->total }}</td>
                                        <td class="a-td text-right font-display text-mist">{{ \App\Models\ExamAttempt::formatDuration($a->durationSeconds()) }}</td>
                                        <td class="a-td text-right font-display text-lg font-bold {{ $a->passed() ? 'text-neon' : 'text-hivis' }}">{{ $fmt($a->score) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="border-t border-white/[0.06] px-4 py-2.5 text-xs text-mist">Nilai sama diurutkan dari waktu pengerjaan tercepat. Satu baris per peserta (percobaan terbaik).</p>
                    @endif
                </div>
                <div class="a-card p-5">
                    <h3 class="font-display font-semibold">Sebaran nilai</h3>
                    <div class="mt-4 flex h-40 items-end gap-1.5">
                        <template x-for="(n, b) in stats.histogram" :key="b">
                            <div class="flex grow flex-col items-center gap-1">
                                <span class="text-[11px] text-fog" x-text="n || ''"></span>
                                <div class="w-full rounded-t-md transition-all" :class="b * 10 >= {{ $exam->pass_score }} ? 'bg-neon/70' : 'bg-hivis/60'" :style="`height: ${Math.max(n ? 6 : 2, n / Math.max(1, ...stats.histogram) * 120)}px`"></div>
                            </div>
                        </template>
                    </div>
                    <div class="mt-1 flex justify-between font-display text-[11px] text-mist"><span>0</span><span>50</span><span>100</span></div>
                    <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-white/[0.04] py-2"><dd class="font-display text-lg font-bold" x-text="num(stats.min)"></dd><dt class="text-[11px] text-mist">terendah</dt></div>
                        <div class="rounded-xl bg-white/[0.04] py-2"><dd class="font-display text-lg font-bold" x-text="num(stats.avg)"></dd><dt class="text-[11px] text-mist">rata-rata</dt></div>
                        <div class="rounded-xl bg-white/[0.04] py-2"><dd class="font-display text-lg font-bold" x-text="num(stats.max)"></dd><dt class="text-[11px] text-mist">tertinggi</dt></div>
                    </dl>
                </div>
            </section>

            {{-- Analisis soal --}}
            <section x-show="view === 'items'" x-cloak class="a-card overflow-hidden">
                @if ($items->isEmpty())
                    <p class="py-14 text-center text-mist"><i class="ri-pie-chart-2-line text-4xl"></i><br>Analisis butir soal muncul setelah ada lembar yang dikumpulkan.</p>
                @else
                    <div class="flex flex-wrap items-center gap-3 border-b border-white/[0.06] p-4 text-sm">
                        <p class="grow text-fog">Diurutkan dari soal tersulit. <span class="text-hivis"><i class="ri-alert-line"></i> {{ $items->where('suspect', true)->count() }} soal</span> pengecohnya dipilih lebih banyak dari kunci — periksa kuncinya.</p>
                        <form method="POST" action="{{ route('admin.exams.regrade', $exam) }}">@csrf<button class="btn-ghost !min-h-10 text-sm"><i class="ri-calculator-line text-neon"></i> Hitung ulang nilai</button></form>
                    </div>
                    <ul class="divide-y divide-white/[0.05]">
                        @foreach ($items as $it)
                            @php $opts = $it->question->filledOptions(); @endphp
                            <li class="grid gap-3 p-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-center">
                                <div class="min-w-0">
                                    <p class="flex items-center gap-2 text-xs text-mist">
                                        <span>{{ $it->question->set->name }} · #{{ $it->question->id }}</span>
                                        @if ($it->suspect) <span class="chip chip-penting"><i class="ri-alert-line"></i> Kunci perlu dicek</span> @endif
                                    </p>
                                    <p class="mt-1 line-clamp-2 text-[14.5px]">{{ $it->question->body }}</p>
                                    <a href="{{ route('admin.exams.bank.edit', $it->question) }}" class="mt-1 inline-block text-xs font-semibold text-neon hover:underline">Ubah soal</a>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between text-xs"><span class="text-mist">{{ $it->correct }}/{{ $it->total }} benar</span><span class="font-display text-base font-bold {{ $it->rate < 30 ? 'text-alarm' : ($it->rate < 60 ? 'text-hivis' : 'text-neon') }}">{{ $it->rate }}%</span></div>
                                    <div class="mt-1.5 flex h-6 overflow-hidden rounded-lg bg-white/[0.04] text-[11px] font-display font-bold">
                                        @foreach ($opts as $k => $t)
                                            @php $n = $it->dist[$k] ?? 0; @endphp
                                            @if ($n)
                                                <span class="grid place-items-center {{ $k === $it->question->answer ? 'bg-neon/70 text-[#04170d]' : ($k === $it->topWrong && $it->suspect ? 'bg-hivis/70 text-[#1a1203]' : 'bg-white/[0.12] text-fog') }}" style="width: {{ $n / $it->total * 100 }}%" title="{{ $k }}: {{ $n }} — {{ \Illuminate\Support\Str::limit($t, 60) }}">{{ $k }}</span>
                                            @endif
                                        @endforeach
                                        @if ($it->blank) <span class="grid place-items-center bg-white/[0.04] text-mist" style="width: {{ $it->blank / $it->total * 100 }}%" title="Kosong: {{ $it->blank }}">–</span> @endif
                                    </div>
                                    <p class="mt-1 text-[11px] text-mist">Kunci {{ $it->question->answer }} · huruf = opsi asli di bank soal</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-layouts.admin>
