<x-layouts.admin title="Event ujian" subtitle="Ujian kompetisi & event: Best Mekanik, Best GL, pre/post test training">
@php
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $num = fn ($n) => number_format((int) $n, 0, ',', '.');
    $trash = $view === 'sampah';
    $tabs = ['' => ['Semua', 'ri-stack-line'], 'open' => ['Berlangsung', 'ri-broadcast-line'], 'scheduled' => ['Terjadwal', 'ri-calendar-schedule-line'], 'draft' => ['Draf', 'ri-draft-line'], 'closed' => ['Selesai', 'ri-flag-2-line']];
    $hasFilter = $filters['q'] !== '' || $filters['category'] !== '' || $filters['year'] || $filters['check'];
    $rowData = $exams->getCollection()->mapWithKeys(fn ($e) => [$e->id => [
        'title' => $e->title, 'attempts' => (int) $e->attempts_count, 'participants' => (int) $e->participants_count,
        'live' => (int) $e->live_count, 'archived' => $e->isArchived(), 'canArchive' => $e->canArchive(),
    ]]);
@endphp
    <x-slot:actions>
        <a href="{{ route('admin.exams.bank') }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-question-answer-line text-lg text-neon"></i> Bank soal</a>
        <a href="{{ route('admin.exams.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Buat ujian</a>
    </x-slot:actions>
    @include('admin.exams._tabs')

    @if ($isEmpty)
        <section class="a-card relative overflow-hidden p-6 lg:p-10">
            <x-contour class="pointer-events-none absolute -top-16 -right-24 h-72 w-[520px] opacity-25" />
            <div class="relative max-w-2xl">
                <span class="exam-medal"><i class="ri-medal-2-fill"></i></span>
                <h2 class="mt-4 font-display text-2xl font-bold">Ujian event pertama dalam 3 langkah</h2>
                <p class="mt-1 text-fog">Peserta cukup login portal; nama & NRP otomatis. Mereka langsung mendapat pemberitahuan dan masuk dengan satu kode dari pengawas.</p>
                <ol class="mt-6 grid gap-3 md:grid-cols-3">
                    <li class="rounded-2xl border border-white/[0.08] bg-white/[0.03] p-4">
                        <p class="font-display text-sm text-neon">Langkah 1</p><p class="mt-1 font-semibold">Siapkan bank soal</p>
                        <p class="mt-1 text-sm text-mist">{{ $num($bank['questions']) }} soal aktif di {{ $bank['sets'] }} paket.</p>
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
    <div x-data="examAdmin(@js(['rows' => $rowData, 'trashDays' => \App\Models\Exam::TRASH_DAYS]))" @keydown.escape.window="closeDialog()" :class="selected.length && 'pb-24'">

        {{-- ===== Sedang berlangsung ===== --}}
        @if ($dash['live']->isNotEmpty())
            <section class="relative mb-5 overflow-hidden rounded-[22px] border border-hivis/35 bg-bench" aria-labelledby="live-title">
                <span class="exam-card-glow" aria-hidden="true"></span>
                <div class="relative p-4 lg:p-5">
                    <h2 id="live-title" class="flex items-center gap-2 font-display text-sm font-semibold text-hivis">
                        <span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-hivis opacity-60"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-hivis"></span></span>
                        Sedang berlangsung
                    </h2>
                    <div class="mt-3 grid gap-3 {{ $dash['live']->count() > 1 ? 'xl:grid-cols-2' : '' }}">
                        @foreach ($dash['live'] as $e)
                            @php $target = $e->audience === 'open' && ! $e->participants_count ? null : $e->participants_count; $pct = $target ? min(100, round($e->done_count / $target * 100)) : null; @endphp
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-2xl border border-white/[0.07] bg-black/20 p-3.5">
                                <div class="min-w-0 grow basis-60">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-md border border-hivis/30 px-1.5 font-display text-xs font-semibold tracking-wider text-hivis">{{ $e->code }}</span>
                                        <span class="truncate text-xs text-mist">tutup {{ $e->closes_at ? $e->closes_at->locale('id')->diffForHumans() : 'tanpa batas' }}</span>
                                    </div>
                                    <a href="{{ route('admin.exams.show', $e) }}" class="mt-1 block truncate font-semibold hover:text-neon">{{ $e->title }}</a>
                                    <div class="mt-2 flex items-center gap-3 text-xs text-fog">
                                        <span><b class="font-display text-sky-300">{{ $e->live_count }}</b> mengerjakan</span>
                                        <span><b class="font-display text-neon">{{ $e->done_count }}</b>{{ $target ? '/'.$target : '' }} selesai</span>
                                        @if ($pct !== null)
                                            <span class="h-1.5 min-w-16 grow overflow-hidden rounded-full bg-white/[0.07]" role="img" aria-label="{{ $pct }}% selesai"><span class="block h-full rounded-full bg-neon" style="width: {{ $pct }}%"></span></span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.exams.screen', $e) }}" target="_blank" class="btn-ghost !min-h-10 !px-3 text-sm" title="Layar proyektor"><i class="ri-tv-2-line text-lg"></i><span class="sr-only">Layar proyektor</span></a>
                                    <a href="{{ route('admin.exams.show', $e) }}" class="btn-hivis !min-h-10 text-sm"><i class="ri-pulse-line text-lg"></i> Pantau</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ===== Ringkasan ===== --}}
        <section id="ringkasan" class="mb-6 scroll-mt-20" x-data="{ shut: localStorage.getItem('astoone.examDash') === '0' }" aria-labelledby="dash-title">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <h2 id="dash-title" class="mr-auto font-display text-lg font-semibold">Ringkasan</h2>
                <nav class="order-last flex w-full overflow-x-auto rounded-full bg-white/[0.05] p-1 text-sm sm:order-none sm:w-auto" aria-label="Periode ringkasan" x-show="!shut">
                    @foreach ($periods as $k => $label)
                        <a href="{{ request()->fullUrlWithQuery(['periode' => $k]) }}#ringkasan" @if ($period === $k) aria-current="true" @endif
                           class="shrink-0 grow rounded-full px-3 py-1 text-center font-semibold whitespace-nowrap transition sm:grow-0 {{ $period === $k ? 'bg-neon text-[#04170d]' : 'text-fog hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </nav>
                <button type="button" class="icon-btn" @click="shut = !shut; localStorage.setItem('astoone.examDash', shut ? '0' : '1')" :aria-expanded="!shut" aria-controls="dash-body" :title="shut ? 'Tampilkan ringkasan' : 'Sembunyikan ringkasan'">
                    <i class="text-lg" :class="shut ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line'"></i>
                </button>
            </div>

            <div id="dash-body" x-show="!shut" x-transition.opacity>
                {{-- Angka utama --}}
                <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <div class="a-card p-4">
                        <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-medal-2-line text-lg text-neon"></i>Event</p>
                        <p class="mt-1 font-display text-3xl font-bold">{{ $num($dash['events']['total']) }}</p>
                        <p class="mt-2 flex flex-wrap gap-x-3 text-xs text-mist">
                            <span><b class="text-fog">{{ $dash['events']['closed'] }}</b> selesai</span>
                            @if ($dash['events']['open']) <span><b class="text-hivis">{{ $dash['events']['open'] }}</b> berlangsung</span> @endif
                            <span><b class="text-fog">{{ $dash['events']['scheduled'] }}</b> terjadwal</span>
                            <span><b class="text-fog">{{ $dash['events']['draft'] }}</b> draf</span>
                        </p>
                    </div>
                    <div class="a-card p-4">
                        <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-file-check-line text-lg text-neon"></i>Lembar selesai</p>
                        <p class="mt-1 font-display text-3xl font-bold">{{ $num($dash['sheets']) }}</p>
                        <p class="mt-2 text-xs text-mist"><b class="text-fog">{{ $num($dash['people']) }}</b> karyawan berbeda @if ($dash['dq']) · <b class="text-red-300">{{ $dash['dq'] }}</b> diskualifikasi @endif</p>
                    </div>
                    <div class="a-card p-4">
                        <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-award-line text-lg text-neon"></i>Rata-rata nilai</p>
                        <p class="mt-1 font-display text-3xl font-bold">{{ $fmt($dash['avg']) }}</p>
                        @if ($dash['passRate'] !== null)
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/[0.06]" role="img" aria-label="Tingkat lulus {{ $dash['passRate'] }}%"><div class="h-full rounded-full bg-neon" style="width: {{ $dash['passRate'] }}%"></div></div>
                            <p class="mt-1.5 text-xs text-mist"><b class="text-fog">{{ $dash['passRate'] }}%</b> lulus ({{ $num($dash['pass']) }} lembar)</p>
                        @else
                            <p class="mt-2 text-xs text-mist">Belum ada lembar yang dinilai</p>
                        @endif
                    </div>
                    <div class="a-card p-4">
                        <p class="flex items-center gap-2 text-sm text-mist"><i class="ri-user-follow-line text-lg text-neon"></i>Kehadiran peserta</p>
                        <p class="mt-1 font-display text-3xl font-bold">{{ $dash['attendance'] !== null ? $dash['attendance'].'%' : '–' }}</p>
                        <p class="mt-2 text-xs text-mist">
                            @if ($dash['assigned']) <b class="{{ $dash['absent'] ? 'text-hivis' : 'text-fog' }}">{{ $num($dash['absent']) }}</b> dari {{ $num($dash['assigned']) }} peserta ditunjuk tidak mengerjakan
                            @else Dihitung dari event selesai yang pesertanya ditunjuk @endif
                        </p>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 xl:grid-cols-[minmax(0,1fr)_360px]">
                    {{-- Grafik 12 bulan --}}
                    <figure class="a-card p-4 lg:p-5">
                        <figcaption class="flex flex-wrap items-baseline gap-x-3">
                            <span class="font-semibold">Lembar selesai per bulan</span>
                            <span class="text-xs text-mist">12 bulan terakhir · arahkan kursor untuk rincian</span>
                        </figcaption>
                        <div class="mt-4 flex h-44 items-end gap-1.5 sm:gap-2.5" aria-hidden="true">
                            @foreach ($dash['series'] as $i => $m)
                                @php $h = $m['sheets'] ? max(4, round($m['sheets'] / $dash['seriesMax'] * 100)) : 0; @endphp
                                <div class="group relative flex h-full min-w-0 flex-1 flex-col items-center justify-end" title="{{ $m['full'] }}: {{ $m['sheets'] }} lembar · {{ $m['events'] }} event{{ $m['avg'] !== null ? ' · rata-rata '.$fmt($m['avg']) : '' }}">
                                    <span class="mb-1 font-display text-[11px] font-semibold {{ $m['sheets'] ? ($m['current'] ? 'text-neon' : 'text-fog') : 'text-transparent' }}">{{ $m['sheets'] }}</span>
                                    <span class="w-full max-w-9 rounded-t-md transition {{ $m['current'] ? 'bg-neon' : 'bg-neon/35 group-hover:bg-neon/60' }}" style="height: {{ $h }}%"></span>
                                    <span class="absolute -bottom-px h-px w-full bg-white/10"></span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1.5 flex gap-1.5 sm:gap-2.5" aria-hidden="true">
                            @foreach ($dash['series'] as $i => $m)
                                <span class="min-w-0 flex-1 truncate text-center text-[11px] {{ $m['current'] ? 'font-semibold text-neon' : 'text-mist' }}">{{ $m['label'] }}@if ($i === 0 || $m['label'] === 'Jan')<span class="hidden sm:inline"> {{ substr($m['year'], 2) }}</span>@endif</span>
                            @endforeach
                        </div>
                        <table class="sr-only">
                            <caption>Lembar selesai per bulan, 12 bulan terakhir</caption>
                            <thead><tr><th>Bulan</th><th>Lembar</th><th>Event</th><th>Rata-rata</th></tr></thead>
                            <tbody>@foreach ($dash['series'] as $m)<tr><td>{{ $m['full'] }}</td><td>{{ $m['sheets'] }}</td><td>{{ $m['events'] }}</td><td>{{ $fmt($m['avg']) }}</td></tr>@endforeach</tbody>
                        </table>
                    </figure>

                    {{-- Perlu perhatian + berikutnya --}}
                    <div class="a-card flex flex-col p-4 lg:p-5">
                        <h3 class="font-semibold">Perlu perhatian</h3>
                        @if ($dash['attention']->isEmpty() && ! $dash['trash'])
                            <p class="mt-2 flex items-center gap-2 text-sm text-mist"><i class="ri-checkbox-circle-line text-lg text-neon"></i> Semua event rapi. Tidak ada yang perlu ditindaklanjuti.</p>
                        @else
                            <ul class="mt-2 space-y-1">
                                @foreach ($dash['attention'] as $a)
                                    <li>
                                        <a href="{{ route('admin.exams.index', ['cek' => $a['key']]) }}#daftar" class="flex items-center gap-3 rounded-xl px-2 py-2 text-sm hover:bg-white/[0.05]">
                                            <span class="grid h-7 min-w-7 place-items-center rounded-lg px-1.5 font-display text-xs font-bold {{ $a['tone'] === 'alarm' ? 'bg-alarm/15 text-red-300' : 'bg-white/[0.07] text-fog' }}">{{ $a['count'] }}</span>
                                            <span class="grow text-fog">{{ $a['label'] }}</span>
                                            <i class="ri-arrow-right-s-line text-mist"></i>
                                        </a>
                                    </li>
                                @endforeach
                                @if ($dash['trash'])
                                    <li>
                                        <a href="{{ route('admin.exams.index', ['status' => 'sampah']) }}#daftar" class="flex items-center gap-3 rounded-xl px-2 py-2 text-sm hover:bg-white/[0.05]">
                                            <span class="grid h-7 min-w-7 place-items-center rounded-lg bg-white/[0.07] px-1.5 font-display text-xs font-bold text-fog">{{ $dash['trash'] }}</span>
                                            <span class="grow text-fog">Di Tempat sampah<span class="block text-xs text-mist">yang tertua terhapus {{ \Illuminate\Support\Carbon::parse($dash['trashSoon'])->addDays(\App\Models\Exam::TRASH_DAYS)->locale('id')->diffForHumans() }}</span></span>
                                            <i class="ri-arrow-right-s-line text-mist"></i>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        @endif

                        @if ($dash['next']->isNotEmpty())
                            <h3 class="mt-4 border-t border-white/[0.06] pt-4 font-semibold">Berikutnya</h3>
                            <ul class="mt-2 space-y-2">
                                @foreach ($dash['next'] as $e)
                                    <li class="flex items-center gap-3 text-sm">
                                        <span class="w-14 shrink-0 rounded-lg bg-sky-400/10 py-1 text-center leading-tight text-sky-300">
                                            <span class="block font-display text-base font-bold">{{ $e->opens_at->format('j') }}</span>
                                            <span class="block text-[10px] uppercase">{{ $e->opens_at->locale('id')->translatedFormat('M') }}</span>
                                        </span>
                                        <span class="min-w-0">
                                            <a href="{{ route('admin.exams.show', $e) }}" class="block truncate font-semibold hover:text-neon">{{ $e->title }}</a>
                                            <span class="text-xs text-mist">{{ $e->opens_at->format('H:i') }} · {{ $e->audience === 'open' ? 'terbuka' : $e->participants_count.' peserta' }} · {{ $e->opens_at->locale('id')->diffForHumans() }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                {{-- Per kategori --}}
                @if ($dash['categories']->count() > 1 || $dash['categories']->contains(fn ($c) => $c['name'] !== 'Tanpa kategori'))
                    <div class="a-card mt-3 overflow-x-auto">
                        <table class="w-full">
                            <caption class="px-4 pt-4 text-left font-semibold">Per kategori</caption>
                            <thead><tr><th class="a-th">Kategori</th><th class="a-th text-right">Event</th><th class="a-th text-right">Lembar</th><th class="a-th">Rata-rata</th><th class="a-th text-right">Lulus</th></tr></thead>
                            <tbody>
                                @foreach ($dash['categories'] as $c)
                                    <tr>
                                        <td class="a-td">
                                            @if ($c['name'] === 'Tanpa kategori') <a href="{{ route('admin.exams.index', ['kategori' => '-']) }}#daftar" class="text-mist hover:text-neon">Tanpa kategori</a>
                                            @else <a href="{{ route('admin.exams.index', ['kategori' => $c['name']]) }}#daftar" class="font-semibold hover:text-neon">{{ $c['name'] }}</a> @endif
                                        </td>
                                        <td class="a-td text-right font-display">{{ $c['events'] }}</td>
                                        <td class="a-td text-right font-display">{{ $num($c['sheets']) }}</td>
                                        <td class="a-td">
                                            <span class="flex items-center gap-2"><span class="w-9 font-display">{{ $fmt($c['avg']) }}</span>
                                                @if ($c['avg'] !== null)<span class="h-1.5 w-24 max-sm:hidden overflow-hidden rounded-full bg-white/[0.06]"><span class="block h-full rounded-full bg-neon/70" style="width: {{ min(100, $c['avg']) }}%"></span></span>@endif
                                            </span>
                                        </td>
                                        <td class="a-td text-right font-display">{{ $c['sheets'] ? round($c['pass'] / $c['sheets'] * 100).'%' : '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        {{-- ===== Daftar event ===== --}}
        <section id="daftar" class="scroll-mt-20" aria-labelledby="list-title">
            <h2 id="list-title" class="sr-only">Daftar event</h2>
            <nav class="-mx-1 mb-3 flex gap-1.5 overflow-x-auto px-1 pb-1" aria-label="Status event">
                @foreach ($tabs as $k => [$label, $icon])
                    <a href="{{ request()->fullUrlWithQuery(['status' => $k ?: null, 'page' => null]) }}#daftar" @if ($view === $k) aria-current="page" @endif
                       class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-semibold transition {{ $view === $k ? 'bg-neon text-[#04170d]' : 'bg-white/[0.05] text-fog hover:bg-white/10' }}">
                        {{ $label }} <span class="font-display text-xs opacity-70">{{ $counts[$k] }}</span>
                    </a>
                @endforeach
                <span class="mx-1 w-px shrink-0 self-stretch bg-white/10" aria-hidden="true"></span>
                @foreach (['arsip' => ['Arsip', 'ri-archive-line'], 'sampah' => ['Tempat sampah', 'ri-delete-bin-line']] as $k => [$label, $icon])
                    <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}#daftar" @if ($view === $k) aria-current="page" @endif
                       class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-semibold transition {{ $view === $k ? 'bg-white text-pit' : 'bg-white/[0.05] text-mist hover:bg-white/10' }}">
                        <i class="{{ $icon }}"></i>{{ $label }} <span class="font-display text-xs opacity-70">{{ $counts[$k] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Saringan --}}
            <form method="GET" action="{{ route('admin.exams.index') }}#daftar" class="mb-3 flex flex-wrap gap-2" role="search" x-ref="filter">
                @foreach (['status' => $view ?: null, 'periode' => request('periode'), 'cek' => $filters['check']] as $k => $v)
                    @if ($v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                @endforeach
                <div class="relative grow basis-full sm:basis-72">
                    <i class="ri-search-line pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-lg text-mist"></i>
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Cari judul, kode, atau kategori" aria-label="Cari event" autocomplete="off"
                           class="field !py-2.5 pl-10" @keydown.escape="if ($el.value) { $el.value = ''; $refs.filter.submit() }">
                </div>
                <select name="kategori" class="field !w-auto min-w-[150px] flex-1 !py-2.5 sm:flex-none" aria-label="Kategori" @change="$refs.filter.submit()">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $c) <option value="{{ $c }}" @selected($filters['category'] === $c)>{{ $c }}</option> @endforeach
                    @if ($uncategorized) <option value="-" @selected($filters['category'] === '-')>Tanpa kategori</option> @endif
                </select>
                @if ($years->count() > 1)
                    <select name="tahun" class="field !w-auto min-w-[150px] flex-1 !py-2.5 sm:flex-none" aria-label="Tahun" @change="$refs.filter.submit()">
                        <option value="">Semua tahun</option>
                        @foreach ($years as $y) <option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option> @endforeach
                    </select>
                @endif
                <select name="urut" class="field !w-auto min-w-[150px] flex-1 !py-2.5 sm:flex-none" aria-label="Urutkan" @change="$refs.filter.submit()">
                    @foreach (['baru' => $trash ? 'Terakhir dihapus' : 'Terbaru dibuat', 'jadwal' => 'Jadwal terbaru', 'judul' => 'Judul A–Z', 'peserta' => 'Peserta terbanyak', 'nilai' => 'Nilai tertinggi'] as $k => $l)
                        <option value="{{ $k }}" @selected($filters['sort'] === $k)>{{ $l }}</option>
                    @endforeach
                </select>
                <button class="btn-ghost !min-h-[46px] text-sm max-sm:hidden">Cari</button>
                <a href="{{ route('admin.exams.export-list', request()->only(['status', 'q', 'kategori', 'tahun', 'urut', 'cek'])) }}" class="btn-ghost !min-h-[46px] text-sm" title="Unduh daftar sesuai saringan ini"><i class="ri-file-excel-2-line text-lg text-neon"></i> Rekap</a>
            </form>

            @if ($hasFilter)
                <div class="mb-3 flex flex-wrap items-center gap-2 text-sm" aria-live="polite">
                    <span class="text-mist"><b class="text-white">{{ $exams->total() }}</b> event cocok</span>
                    @if ($filters['check'])
                        <a href="{{ request()->fullUrlWithQuery(['cek' => null, 'page' => null]) }}#daftar" class="chip-penting !py-1">{{ $checks[$filters['check']] }} <i class="ri-close-line"></i><span class="sr-only">hapus saringan</span></a>
                    @endif
                    @if ($filters['q'] !== '') <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}#daftar" class="chip bg-white/[0.07] !py-1 text-fog">“{{ $filters['q'] }}” <i class="ri-close-line"></i></a> @endif
                    @if ($filters['category'] !== '') <a href="{{ request()->fullUrlWithQuery(['kategori' => null, 'page' => null]) }}#daftar" class="chip bg-white/[0.07] !py-1 text-fog">{{ $filters['category'] === '-' ? 'Tanpa kategori' : $filters['category'] }} <i class="ri-close-line"></i></a> @endif
                    @if ($filters['year']) <a href="{{ request()->fullUrlWithQuery(['tahun' => null, 'page' => null]) }}#daftar" class="chip bg-white/[0.07] !py-1 text-fog">{{ $filters['year'] }} <i class="ri-close-line"></i></a> @endif
                    <a href="{{ route('admin.exams.index', array_filter(['status' => $view ?: null, 'periode' => request('periode')])) }}#daftar" class="section-link">Hapus semua saringan</a>
                </div>
            @endif

            @if ($trash && $counts['sampah'])
                <div class="mb-3 flex flex-wrap items-center gap-3 rounded-2xl border border-white/[0.08] bg-white/[0.03] p-3.5 text-sm">
                    <i class="ri-information-line text-xl text-mist"></i>
                    <p class="grow text-fog">Event di sini tidak terlihat peserta dan dihapus permanen otomatis {{ \App\Models\Exam::TRASH_DAYS }} hari setelah dipindahkan. Pulihkan bila terhapus tidak sengaja.</p>
                    <button type="button" class="btn-ghost !min-h-10 text-sm text-red-300" @click="ask('empty-trash', [], {{ $counts['sampah'] }})"><i class="ri-delete-bin-2-line text-lg"></i> Kosongkan</button>
                </div>
            @endif

            {{-- Daftar --}}
            <div class="a-card">
                @if ($exams->isNotEmpty())
                    <div class="hidden items-center gap-4 border-b border-white/[0.06] px-4 py-2.5 text-xs font-semibold text-mist lg:grid lg:grid-cols-[28px_minmax(0,1fr)_150px_190px_150px_44px]">
                        <label class="grid place-items-center"><input type="checkbox" class="exam-check" :checked="allChecked" x-effect="$el.indeterminate = someChecked" @change="toggleAll($event.target.checked)" aria-label="Pilih semua di halaman ini"></label>
                        <span>Event</span><span>{{ $trash ? 'Dihapus' : 'Status' }}</span><span>{{ $trash ? 'Terhapus permanen' : 'Peserta' }}</span><span>{{ $trash ? 'Data' : 'Hasil' }}</span><span></span>
                    </div>
                @endif
                <ul>
                    @forelse ($exams as $e)
                        @php
                            $st = $e->stateInfo(); $state = $e->state();
                            $target = $e->audience === 'open' && ! $e->participants_count ? null : $e->participants_count;
                            $pct = $target ? min(100, round($e->done_count / $target * 100)) : null;
                            $passPct = $e->done_count ? round($e->pass_count / $e->done_count * 100) : null;
                        @endphp
                        <li class="relative grid grid-cols-[28px_minmax(0,1fr)_40px] items-center gap-x-3 gap-y-2 border-t border-white/[0.05] px-4 py-3.5 transition first:border-t-0 hover:bg-white/[0.02] lg:grid-cols-[28px_minmax(0,1fr)_150px_190px_150px_44px] lg:gap-x-4"
                            :class="isChecked({{ $e->id }}) && 'bg-neon/[0.04]'">
                            <label class="row-start-1 grid place-items-center self-start pt-1 lg:self-center lg:pt-0">
                                <input type="checkbox" class="exam-check" value="{{ $e->id }}" x-model.number="selected" aria-label="Pilih {{ $e->title }}">
                            </label>

                            {{-- Judul --}}
                            <div class="row-start-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-md border border-white/10 px-1.5 font-display text-xs font-semibold tracking-wider text-hivis">{{ $e->code }}</span>
                                    @if ($e->category) <span class="text-xs text-fog">{{ $e->category }}</span> @endif
                                    @if ($e->isArchived() && ! $trash) <span class="chip bg-white/[0.07] !py-0 text-mist"><i class="ri-archive-line"></i> Arsip</span> @endif
                                </div>
                                @if ($trash)
                                    <p class="mt-1 line-clamp-2 font-semibold text-fog lg:truncate">{{ $e->title }}</p>
                                @else
                                    <a href="{{ route('admin.exams.show', $e) }}" class="mt-1 line-clamp-2 font-semibold hover:text-neon lg:block lg:truncate">{{ $e->title }}</a>
                                @endif
                                <p class="mt-0.5 truncate text-xs text-mist"><i class="ri-calendar-line"></i> {{ $e->scheduleLabel() }} · {{ $e->duration_minutes }} mnt</p>
                            </div>

                            {{-- Menu --}}
                            <div class="relative col-start-3 row-start-1 self-start lg:col-start-6 lg:self-center" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                                <button type="button" class="icon-btn" @click="open = !open" :aria-expanded="open" aria-label="Aksi untuk {{ $e->title }}"><i class="ri-more-2-fill text-lg"></i></button>
                                <div x-show="open" x-cloak x-transition.origin.top.right class="absolute top-10 right-0 z-30 w-60 rounded-xl border border-white/10 bg-ridge p-1 shadow-2xl" role="menu">
                                    @if ($trash)
                                        <button type="button" class="menu-item" role="menuitem" @click="open = false; ask('restore', [{{ $e->id }}])"><i class="ri-arrow-go-back-line text-neon"></i> Pulihkan</button>
                                        <button type="button" class="menu-item text-red-300" role="menuitem" @click="open = false; ask('purge', [{{ $e->id }}])"><i class="ri-delete-bin-2-line"></i> Hapus permanen…</button>
                                    @else
                                        <a href="{{ route('admin.exams.show', $e) }}" class="menu-item" role="menuitem"><i class="ri-pulse-line text-neon"></i> Pantau & hasil</a>
                                        <a href="{{ route('admin.exams.edit', $e) }}" class="menu-item" role="menuitem"><i class="ri-edit-line"></i> Ubah</a>
                                        <a href="{{ route('admin.exams.create', ['salin' => $e->id]) }}" class="menu-item" role="menuitem"><i class="ri-file-copy-line"></i> Salin jadi event baru</a>
                                        @if ($e->is_published) <a href="{{ route('admin.exams.screen', $e) }}" target="_blank" class="menu-item" role="menuitem"><i class="ri-tv-2-line"></i> Layar proyektor</a> @endif
                                        @if ($e->attempts_count) <a href="{{ route('admin.exams.export', $e) }}" class="menu-item" role="menuitem"><i class="ri-file-excel-2-line"></i> Ekspor hasil</a> @endif
                                        <div class="my-1 h-px bg-white/[0.07]"></div>
                                        @if ($e->isArchived())
                                            <button type="button" class="menu-item" role="menuitem" @click="open = false; run('unarchive', [{{ $e->id }}])"><i class="ri-inbox-unarchive-line"></i> Keluarkan dari arsip</button>
                                        @elseif ($e->canArchive())
                                            <button type="button" class="menu-item" role="menuitem" @click="open = false; run('archive', [{{ $e->id }}])"><i class="ri-archive-line"></i> Arsipkan</button>
                                        @endif
                                        <button type="button" class="menu-item text-red-300" role="menuitem" @click="open = false; ask('trash', [{{ $e->id }}])"><i class="ri-delete-bin-line"></i> Hapus…</button>
                                    @endif
                                </div>
                            </div>

                            <div class="col-span-2 col-start-2 flex flex-wrap items-center gap-x-5 gap-y-2 lg:contents">
                            @if ($trash)
                                <div class="col-start-2 text-xs text-mist lg:col-start-3 lg:row-start-1">
                                    <span class="lg:hidden">Dihapus </span>{{ $e->deleted_at->locale('id')->diffForHumans() }}
                                    @if ($e->deleter) <span class="block">oleh {{ $e->deleter->displayName() }}</span> @endif
                                </div>
                                <div class="col-start-2 text-xs lg:col-start-4 lg:row-start-1">
                                    <span class="text-hivis">{{ $e->purgeDate()->locale('id')->translatedFormat('j M Y') }}</span>
                                    <span class="block text-mist">{{ $e->purgeDate()->locale('id')->diffForHumans() }}</span>
                                </div>
                                <div class="col-start-2 text-xs text-mist lg:col-start-5 lg:row-start-1">{{ $e->participants_count }} peserta · {{ $e->attempts_count }} lembar</div>
                            @else
                                {{-- Status --}}
                                <div class="col-start-2 flex flex-wrap items-center gap-1.5 lg:col-start-3 lg:row-start-1">
                                    <span class="{{ $st['chip'] }}"><i class="{{ $st['icon'] }}"></i> {{ $st['label'] }}</span>
                                    @if ($e->live_count) <span class="chip bg-sky-400/15 text-sky-300"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-sky-300"></span>{{ $e->live_count }}</span> @endif
                                </div>
                                {{-- Peserta --}}
                                <div class="min-w-36 lg:col-start-4 lg:row-start-1">
                                    <p class="text-xs text-mist"><b class="font-display text-sm text-ink">{{ $e->done_count }}</b>{{ $target !== null ? ' / '.$target : '' }} selesai{{ $target === null ? ' · terbuka' : '' }}@if ($e->dq_count) · <span class="text-red-300">{{ $e->dq_count }} DQ</span>@endif</p>
                                    @if ($pct !== null)
                                        <div class="mt-1.5 h-1.5 max-w-44 overflow-hidden rounded-full bg-white/[0.06]" role="img" aria-label="{{ $pct }}% peserta selesai"><div class="h-full rounded-full {{ $state === 'open' ? 'bg-hivis' : 'bg-neon/70' }}" style="width: {{ $pct }}%"></div></div>
                                    @endif
                                </div>
                                {{-- Hasil --}}
                                <div class="col-start-2 text-xs text-mist lg:col-start-5 lg:row-start-1">
                                    @if ($e->avg_score !== null)
                                        <span class="font-display text-sm font-bold {{ $e->avg_score >= $e->pass_score ? 'text-neon' : 'text-ink' }}">{{ $fmt($e->avg_score) }}</span> rata-rata
                                        <span class="block">{{ $passPct }}% lulus (≥ {{ $e->pass_score }})</span>
                                    @else
                                        Belum ada nilai
                                    @endif
                                </div>
                            @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-6 py-14 text-center">
                            <i class="{{ $trash ? 'ri-delete-bin-line' : ($view === 'arsip' ? 'ri-archive-line' : 'ri-search-2-line') }} text-4xl text-mist/60"></i>
                            <p class="mt-2 font-semibold">
                                @if ($hasFilter) Tidak ada event yang cocok dengan saringan ini
                                @elseif ($trash) Tempat sampah kosong
                                @elseif ($view === 'arsip') Belum ada event di arsip
                                @else Tidak ada event {{ Str::lower($tabs[$view][0] ?? '') }} @endif
                            </p>
                            <p class="mt-1 text-sm text-mist">
                                @if ($hasFilter) Ubah kata kunci atau <a href="{{ route('admin.exams.index', array_filter(['status' => $view ?: null])) }}#daftar" class="text-neon">hapus saringan</a>.
                                @elseif ($trash) Event yang dihapus muncul di sini selama {{ \App\Models\Exam::TRASH_DAYS }} hari sebelum hilang permanen.
                                @elseif ($view === 'arsip') Arsipkan event yang sudah selesai agar daftar utama tetap ringkas. Hasilnya tetap tersimpan.
                                @else <a href="{{ route('admin.exams.create') }}" class="text-neon">Buat ujian baru</a> atau lihat tab lain. @endif
                            </p>
                        </li>
                    @endforelse
                </ul>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <div class="grow">{{ $exams->links('admin.pagination') }}</div>
                @if ($exams->total() > 20)
                    <label class="flex items-center gap-2 text-sm text-mist">Per halaman
                        <select class="field !w-auto !py-1.5" onchange="location.href = this.value" aria-label="Jumlah per halaman">
                            @foreach ([20, 50, 100] as $n) <option value="{{ request()->fullUrlWithQuery(['per' => $n, 'page' => null]) }}#daftar" @selected($per === $n)>{{ $n }}</option> @endforeach
                        </select>
                    </label>
                @endif
            </div>
        </section>

        {{-- ===== Bilah aksi massal ===== --}}
        <div x-show="selected.length" x-cloak x-transition.opacity.duration.150ms
             class="fixed inset-x-3 bottom-[max(12px,env(safe-area-inset-bottom))] z-40 mx-auto flex max-w-3xl items-center gap-1.5 rounded-2xl sm:gap-2 border border-white/10 bg-ridge/95 p-2.5 pl-4 shadow-2xl backdrop-blur-xl lg:left-[calc(264px+12px)]" role="region" aria-label="Aksi untuk event terpilih">
            <p class="mr-auto text-sm whitespace-nowrap"><b class="font-display text-neon" x-text="selected.length"></b><span class="max-sm:hidden"> event</span> dipilih</p>
            @if ($trash)
                <button type="button" class="btn-ghost !min-h-10 !px-3 text-sm sm:!px-4" @click="ask('restore', selected)"><i class="ri-arrow-go-back-line text-lg text-neon"></i> Pulihkan</button>
                <button type="button" class="btn-ghost !min-h-10 !px-3 text-sm sm:!px-4 text-red-300" @click="ask('purge', selected)"><i class="ri-delete-bin-2-line text-lg"></i> Hapus permanen</button>
            @else
                @if ($view === 'arsip')
                    <button type="button" class="btn-ghost !min-h-10 !px-3 text-sm sm:!px-4" @click="run('unarchive', selected)"><i class="ri-inbox-unarchive-line text-lg"></i> Keluarkan</button>
                @else
                    <button type="button" class="btn-ghost !min-h-10 !px-3 text-sm sm:!px-4" @click="run('archive', selected)"><i class="ri-archive-line text-lg"></i> Arsipkan</button>
                @endif
                <button type="button" class="btn-ghost !min-h-10 !px-3 text-sm sm:!px-4 text-red-300" @click="ask('trash', selected)"><i class="ri-delete-bin-line text-lg"></i> Hapus</button>
            @endif
            <button type="button" class="icon-btn" @click="selected = []" aria-label="Batalkan pilihan"><i class="ri-close-line text-xl"></i></button>
        </div>

        {{-- ===== Formulir aksi + dialog konfirmasi ===== --}}
        <form method="POST" action="{{ route('admin.exams.bulk') }}" x-ref="bulk" class="hidden">
            @csrf
            <input type="hidden" name="action" :value="dialog.action">
            <input type="hidden" name="confirm" :value="dialog.typed">
            <template x-for="id in dialog.ids" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
        </form>

        <div x-show="dialog.open" x-cloak x-transition.opacity class="fixed inset-0 z-[60] flex items-end justify-center bg-black/70 backdrop-blur-sm sm:items-center sm:p-6" @click.self="closeDialog()">
            <div class="w-full max-w-md rounded-t-[26px] border border-white/10 bg-bench p-5 pb-[max(20px,env(safe-area-inset-bottom))] shadow-2xl sm:rounded-[26px] sm:p-6" role="alertdialog" aria-modal="true" aria-labelledby="dlg-title" aria-describedby="dlg-body" x-ref="dlg">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-xl" :class="dialog.danger ? 'bg-alarm/15 text-red-300' : 'bg-neon/15 text-neon'"><i :class="dialog.icon"></i></span>
                    <div class="min-w-0">
                        <h2 id="dlg-title" class="font-display text-lg font-bold" x-text="dialog.title"></h2>
                        <p id="dlg-body" class="mt-1 text-sm text-fog" x-text="dialog.body"></p>
                    </div>
                </div>
                <ul class="mt-4 space-y-1 rounded-xl bg-white/[0.04] p-3 text-sm" x-show="dialog.names.length">
                    <template x-for="n in dialog.names" :key="n"><li class="truncate text-fog" x-text="n"></li></template>
                    <li class="text-xs text-mist" x-show="dialog.more" x-text="'dan ' + dialog.more + ' event lainnya'"></li>
                </ul>
                <p class="mt-3 flex gap-2 rounded-xl border border-hivis/25 bg-hivis/[0.06] p-3 text-sm text-hivis" x-show="dialog.impact"><i class="ri-error-warning-line mt-0.5"></i><span x-text="dialog.impact"></span></p>
                <div class="mt-4" x-show="dialog.needType">
                    <label for="dlg-type" class="label">Ketik <b class="font-display text-red-300">HAPUS</b> untuk melanjutkan</label>
                    <input id="dlg-type" x-ref="typed" x-model="dialog.typed" autocomplete="off" class="field font-display tracking-widest uppercase" @keydown.enter.prevent="ready && submit()">
                </div>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-ghost !min-h-11 text-sm" @click="closeDialog()">Batal</button>
                    <button type="button" x-ref="go" class="!min-h-11 text-sm" :class="dialog.danger ? 'btn bg-alarm text-white hover:brightness-110' : 'btn-neon'" :disabled="!ready || busy" @click="submit()">
                        <i class="ri-loader-4-line animate-spin" x-show="busy"></i><span x-text="dialog.confirm"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</x-layouts.admin>
