<x-layouts.admin title="Mentari" subtitle="Otak asisten: data asli, pengetahuan, dan AI gratis">
@php
    $readyCount = collect($providers)->filter(fn ($p) => $p['configured'] && ! $p['cooling'] && $p['used'] < $p['daily'])->count();
    $aiLive = $aiEnabled && $readyCount > 0;
@endphp
<div x-data="{ draft: { question: '', keywords: '', answer: '', normalized: '' },
               use(row) { this.draft = { question: row.q, keywords: row.k, answer: '', normalized: row.n }; this.$nextTick(() => { document.getElementById('faq-baru').scrollIntoView({ behavior: 'smooth', block: 'center' }); document.getElementById('faq-baru-answer').focus(); }); } }"
     class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
    <div class="min-w-0 space-y-5">

        {{-- Alur berpikir --}}
        <section class="a-card relative overflow-hidden p-5 lg:p-6">
            <x-contour class="pointer-events-none absolute -top-10 -right-20 h-56 w-[480px] opacity-25" />
            <div class="relative flex flex-wrap items-center gap-5">
                <x-mentari :size="88" />
                <div class="min-w-0 grow">
                    <h2 class="font-display text-xl font-semibold">Cara Mentari berpikir</h2>
                    <p class="mt-1 text-sm text-fog">Setiap pertanyaan melewati lapis demi lapis. Lapis yang lebih pasti selalu didahulukan, jadi AI hanya dipakai bila perlu.</p>
                </div>
            </div>
            <ol class="relative mt-5 grid gap-2 sm:grid-cols-2 2xl:grid-cols-4">
                @foreach ([
                    ['ri-chat-history-line', 'Ingatan percakapan', '"training dia?" dipahami dari obrolan sebelumnya', true],
                    ['ri-database-2-line', 'Data asli AstoOne', 'Karyawan, poin, absensi, ujian, aplikasi, statistik — selalu akurat', true],
                    ['ri-book-open-line', 'Pengetahuan', $kbCount.' dokumen: panduan bawaan, FAQ, aplikasi, pengumuman', true],
                    ['ri-sparkling-2-line', 'AI gratis', $aiLive ? $readyCount.' penyedia siap' : ($aiConfigured ? ($aiEnabled ? 'Kuota/penyedia sedang jeda' : 'Dimatikan admin') : 'Belum diatur'), $aiLive],
                ] as $i => [$icon, $title, $desc, $on])
                    <li class="flex gap-3 rounded-2xl border {{ $on ? 'border-neon/25 bg-neon/[0.05]' : 'border-white/[0.08] bg-white/[0.02]' }} p-3.5">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $on ? 'bg-neon/15 text-neon' : 'bg-white/[0.06] text-mist' }}"><i class="{{ $icon }} text-xl"></i></span>
                        <span class="min-w-0 leading-snug"><span class="block text-sm font-semibold"><span class="font-display text-mist">{{ $i + 1 }}.</span> {{ $title }}</span><span class="text-xs text-mist">{{ $desc }}</span></span>
                    </li>
                @endforeach
            </ol>
            <dl class="relative mt-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
                @foreach ([['Pertanyaan 7 hari', $week['total']], ['Terjawab', $week['answered'] !== null ? $week['answered'].'%' : '–'], ['Lewat AI', $week['ai']], ['Dari pengetahuan', $week['kb']], ['👍 / 👎', $week['up'].' / '.$week['down']], ['Penanya', $week['users']]] as [$l, $v])
                    <div class="rounded-xl bg-white/[0.04] px-3 py-2.5"><dt class="text-[11px] text-mist">{{ $l }}</dt><dd class="font-display text-xl font-bold">{{ $v }}</dd></div>
                @endforeach
            </dl>
        </section>

        {{-- AI gratis --}}
        <section class="a-card overflow-hidden">
            <header class="flex flex-wrap items-center gap-3 border-b border-white/[0.06] px-5 py-3.5">
                <div class="grow"><h2 class="font-display font-semibold"><i class="ri-sparkling-2-line text-neon"></i> AI gratis</h2>
                    <p class="text-xs text-mist">Dipakai bila pertanyaan tidak terjawab lapis 1–3. Kuota habis/gangguan → penyedia berikutnya → otak bawaan. Tidak ada tagihan.</p></div>
                <form method="POST" action="{{ route('admin.mentari.settings') }}" class="flex flex-wrap items-center gap-3" x-data="{ on: @js($aiEnabled) }">
                    @csrf
                    <input type="hidden" name="enabled" :value="on ? 1 : 0">
                    <label class="flex items-center gap-2 text-sm text-fog">Batas per orang/hari
                        <input type="number" name="per_user" min="0" max="500" value="{{ $perUser }}" class="field !w-20 !py-1.5 text-center font-display">
                    </label>
                    <button type="button" role="switch" :aria-checked="on" @click="on = !on" class="relative h-7 w-12 rounded-full transition" :class="on ? 'bg-neon' : 'bg-white/15'" aria-label="AI aktif">
                        <span class="absolute top-1 left-1 h-5 w-5 rounded-full bg-white shadow transition-transform" :class="on && 'translate-x-5'"></span>
                    </button>
                    <button class="btn-neon !min-h-9 text-sm">Simpan</button>
                </form>
            </header>
            <ul class="divide-y divide-white/[0.05]">
                @foreach ($providers as $p)
                    @php
                        $state = match (true) {
                            ! $p['configured'] => ['Belum diatur', 'bg-white/10 text-mist'],
                            (bool) $p['cooling'] => ['Jeda s/d '.$p['cooling'], 'chip-penting'],
                            $p['used'] >= $p['daily'] => ['Kuota hari ini habis', 'chip-penting'],
                            ! $aiEnabled => ['Dimatikan', 'bg-white/10 text-mist'],
                            default => ['Siap', 'chip-tayang'],
                        };
                    @endphp
                    <li class="grid gap-3 px-5 py-3.5 md:grid-cols-[minmax(0,1fr)_220px] md:items-center">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2"><span class="font-semibold">{{ $p['label'] }}</span><span class="chip {{ $state[1] }}">{{ $state[0] }}</span>
                                <span class="chip {{ $p['personal'] ? 'bg-neon/10 text-neon-soft' : 'bg-white/[0.06] text-mist' }}" title="{{ $p['personal'] ? 'Penyedia tidak memakai data untuk melatih model' : 'Data free tier bisa dipakai penyedia — hanya pengetahuan umum yang dikirim' }}">
                                    <i class="{{ $p['personal'] ? 'ri-shield-user-line' : 'ri-shield-line' }}"></i> {{ $p['personal'] ? 'Boleh data karyawan' : 'Tanpa data karyawan' }}</span></p>
                            <p class="mt-0.5 truncate font-display text-xs text-mist">{{ $p['model'] }}</p>
                            @if ($p['error'])
                                <p class="mt-1 text-xs text-hivis"><i class="ri-error-warning-line"></i> {{ $p['error']['at'] }} — {{ $p['error']['reason'] }}</p>
                            @elseif ($p['ok'])
                                <p class="mt-1 text-xs text-mist"><i class="ri-check-line text-neon"></i> Terakhir berhasil {{ $p['ok'] }}</p>
                            @endif
                        </div>
                        @if ($p['configured'])
                            <div>
                                <div class="flex justify-between text-xs text-mist"><span>Permintaan hari ini (perkiraan)</span><span class="font-display text-fog">{{ $p['used'] }}/{{ $p['daily'] }}</span></div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/[0.07]"><div class="h-full rounded-full {{ $p['used'] / max(1, $p['daily']) > .85 ? 'bg-hivis' : 'bg-neon' }}" style="width: {{ min(100, $p['used'] / max(1, $p['daily']) * 100) }}%"></div></div>
                            </div>
                        @else
                            <p class="text-xs text-mist">Isi <code class="text-neon-soft">{{ strtoupper($p['name']) }}_API_KEY</code> di .env server</p>
                        @endif
                    </li>
                @endforeach
            </ul>
            @unless ($aiConfigured)
                <p class="border-t border-white/[0.06] bg-white/[0.02] px-5 py-3 text-xs leading-relaxed text-mist">
                    Cara mengaktifkan: daftar gratis di console.groq.com → API Keys → buat kunci → tambahkan <code class="text-neon-soft">GROQ_API_KEY=…</code> ke .env AstoOne → <code class="text-neon-soft">php artisan config:cache</code>. Jangan kirim kunci lewat chat.
                </p>
            @endunless
        </section>

        {{-- Pertanyaan belum terjawab --}}
        <section class="a-card overflow-hidden">
            <header class="border-b border-white/[0.06] px-5 py-3.5">
                <h2 class="font-display font-semibold"><i class="ri-question-line text-hivis"></i> Pertanyaan yang belum bisa dijawab <span class="text-mist">· 30 hari</span></h2>
                <p class="text-xs text-mist">Jadikan jawaban siap pakai agar Mentari makin pintar. Diurutkan dari yang paling sering ditanyakan.</p>
            </header>
            <ul class="divide-y divide-white/[0.05]">
                @forelse ($unanswered as $row)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <span class="grid h-9 min-w-9 place-items-center rounded-lg bg-hivis/10 px-2 font-display text-sm font-bold text-hivis">{{ $row->total }}×</span>
                        <span class="min-w-0 grow leading-tight"><span class="block truncate">{{ $row->sample }}</span><span class="text-xs text-mist">terakhir {{ \Illuminate\Support\Carbon::parse($row->last_at)->locale('id')->diffForHumans() }}</span></span>
                        <button type="button" @click="use(@js(['q' => \Illuminate\Support\Str::limit($row->sample, 150, ''), 'k' => $row->keywords, 'n' => $row->normalized]))" class="btn-neon !min-h-9 text-sm"><i class="ri-add-line"></i> Buat jawaban</button>
                        <form method="POST" action="{{ route('admin.mentari.resolve') }}">@csrf<input type="hidden" name="normalized" value="{{ $row->normalized }}"><button class="btn-ghost !min-h-9 text-sm" title="Tandai sudah ditangani">Abaikan</button></form>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-mist"><i class="ri-emotion-happy-line text-2xl text-neon"></i><br>Semua pertanyaan 30 hari terakhir terjawab.</li>
                @endforelse
            </ul>
        </section>

        @if ($unhelpful->isNotEmpty())
            <section class="a-card overflow-hidden">
                <header class="border-b border-white/[0.06] px-5 py-3.5">
                    <h2 class="font-display font-semibold">👎 Jawaban yang dinilai kurang membantu</h2>
                    <p class="text-xs text-mist">Perbaiki dengan jawaban siap pakai, atau perbarui penjelasan aplikasi.</p>
                </header>
                <ul class="divide-y divide-white/[0.05]">
                    @foreach ($unhelpful as $log)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <span class="min-w-0 grow leading-tight"><span class="block truncate">{{ $log->question }}</span>
                                <span class="text-xs text-mist">{{ \App\Models\MentariLog::SOURCES[$log->source] ?? $log->source }}{{ $log->provider ? ' · '.$log->provider : '' }} · {{ $log->created_at->locale('id')->diffForHumans() }}</span></span>
                            <button type="button" @click="use(@js(['q' => \Illuminate\Support\Str::limit($log->question, 150, ''), 'k' => '', 'n' => '']))" class="btn-ghost !min-h-9 text-sm"><i class="ri-add-line"></i> Buat jawaban</button>
                            <form method="POST" action="{{ route('admin.mentari.resolve') }}">@csrf<input type="hidden" name="log_id" value="{{ $log->id }}"><button class="btn-ghost !min-h-9 text-sm">Beres</button></form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Jawaban siap pakai --}}
        <section class="a-card overflow-hidden" x-data="{ edit: null }">
            <header class="border-b border-white/[0.06] px-5 py-3">
                <h2 class="font-display font-semibold">Jawaban siap pakai (FAQ)</h2>
                <p class="text-xs text-mist">Dicari lewat kata kunci dan isi jawabannya. Juga menjadi rujukan AI.</p>
            </header>
            @foreach ($faqs as $faq)
                <div class="border-b border-white/[0.05] px-5 py-4 last:border-0">
                    <div x-show="edit !== {{ $faq->id }}" class="flex gap-3">
                        <div class="min-w-0 grow">
                            <p class="font-semibold {{ $faq->is_active ? '' : 'text-mist line-through' }}">{{ $faq->question }}</p>
                            <p class="mt-1 text-sm text-fog">{{ \Illuminate\Support\Str::limit($faq->answer, 180) }}</p>
                            <p class="mt-1.5 flex flex-wrap gap-1">@foreach ($faq->keywordList() as $k)<span class="chip bg-white/[0.06] text-mist">{{ $k }}</span>@endforeach</p>
                        </div>
                        <span class="shrink-0 text-xs text-mist">{{ $faq->hits }}× dipakai</span>
                        <button type="button" @click="edit = {{ $faq->id }}" class="icon-btn shrink-0" aria-label="Ubah"><i class="ri-pencil-line text-lg"></i></button>
                    </div>
                    <div x-show="edit === {{ $faq->id }}" x-cloak>
                        @include('admin.mentari._form', ['faq' => $faq])
                    </div>
                </div>
            @endforeach
            <div id="faq-baru" class="scroll-mt-24 border-t border-white/[0.06] bg-white/[0.02] px-5 py-4">
                <p class="mb-3 text-sm font-semibold">Tambah jawaban</p>
                <form method="POST" action="{{ route('admin.mentari.store') }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="resolve_normalized" :value="draft.normalized">
                    <input name="question" x-model="draft.question" required maxlength="160" placeholder="Topik / pertanyaan" class="field !py-2.5 text-sm sm:col-span-2">
                    <input name="keywords" x-model="draft.keywords" required maxlength="300" placeholder="Kata kunci, pisahkan dengan koma" class="field !py-2.5 text-sm sm:col-span-2">
                    <textarea id="faq-baru-answer" name="answer" x-model="draft.answer" required maxlength="2000" rows="3" placeholder="Jawaban Mentari" class="field !py-2.5 text-sm sm:col-span-2"></textarea>
                    <input name="link_label" maxlength="40" placeholder="Label tombol (opsional)" class="field !py-2.5 text-sm">
                    <input name="link_url" maxlength="500" placeholder="https://… atau /aplikasi" class="field !py-2.5 text-sm">
                    <div class="flex items-center gap-3 sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 accent-[var(--color-neon)]"> Aktif</label>
                        <span class="grow"></span>
                        <button class="btn-neon !min-h-9 text-sm">Tambah</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    {{-- Uji coba --}}
    <aside class="a-card self-start p-5 xl:sticky xl:top-24" x-data="{ q: '', ai: false, answer: null, busy: false,
            async ask() { if (!this.q.trim()) return; this.busy = true; try { this.answer = await postJson(@js(route('admin.mentari.test')), { message: this.q, ai: this.ai }); } finally { this.busy = false; } } }">
        <h2 class="font-display font-semibold">Uji jawaban</h2>
        <p class="mt-1 text-xs text-mist">Coba pertanyaan seperti yang diketik mekanik — boleh pakai singkatan & salah ketik.</p>
        <form @submit.prevent="ask()" class="mt-3 flex gap-2">
            <input x-model="q" class="field !py-2.5" placeholder="Contoh: brp poin sy">
            <button class="btn-neon !min-h-11 shrink-0 !px-4" :disabled="busy" aria-label="Tanya"><i :class="busy ? 'ri-loader-4-line animate-spin' : 'ri-send-plane-2-fill'"></i></button>
        </form>
        <label class="mt-2 flex items-center gap-2 text-xs text-fog"><input type="checkbox" x-model="ai" class="h-4 w-4 accent-[var(--color-neon)]" {{ $aiLive ? '' : 'disabled' }}> Izinkan memakai AI (memotong kuota)</label>
        <template x-if="answer">
            <div class="mt-4 rounded-2xl border border-white/[0.07] bg-white/[0.03] p-3.5 text-sm">
                <p class="mb-2 text-[11px] font-semibold text-mist" x-text="{ guide: 'Otak bawaan', knowledge: 'Pengetahuan', ai: 'AI · ' + (answer.provider_label || '') }[answer.source] + ' · ' + answer.intent"></p>
                <p class="whitespace-pre-line" x-text="answer.reply"></p>
                <template x-for="c in (answer.cards || [])"><p class="mt-2 text-xs text-fog"><i class="ri-layout-2-line text-neon"></i> kartu <span x-text="c.type"></span><span x-show="c.name" x-text="': ' + c.name"></span></p></template>
                <template x-for="a in answer.actions"><p class="mt-2 text-xs text-neon-soft"><i class="ri-link"></i> <span x-text="a.label"></span></p></template>
            </div>
        </template>
    </aside>
</div>
</x-layouts.admin>
