<x-layouts.admin :title="$session->type->name" :heading="$session->type->name.($session->title ? ' — '.$session->title : '')">
    <x-slot:actions>
        <a href="{{ route('admin.attendance.screen', $session) }}" target="_blank" class="btn-neon !min-h-11 text-sm"><i class="ri-tv-2-line text-lg"></i> Layar QR</a>
        <a href="{{ route('admin.attendance.export', $session) }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-file-excel-2-line text-lg"></i> Ekspor</a>
        <a href="{{ route('admin.attendance.edit', $session) }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-pencil-line text-lg"></i> Ubah</a>
    </x-slot:actions>
    @include('admin.attendance._tabs')

    <div x-data="liveSession(@js(route('admin.attendance.feed', $session)), @js($feed))" class="grid gap-5 xl:grid-cols-[360px_1fr]">
        {{-- Kolom kiri: QR & kontrol --}}
        <div class="space-y-4">
            <section class="a-card overflow-hidden" style="box-shadow: inset 0 3px 0 {{ $session->type->color }}">
                <div class="flex items-center justify-between px-4 pt-4">
                    <span class="text-sm text-mist">{{ $session->starts_at->locale('id')->translatedFormat('l, j M · H:i') }}</span>
                    <span :class="{ 'chip-tayang': feed.status === 'dibuka', 'chip-terjadwal': feed.status === 'terjadwal', 'chip-berakhir': feed.status === 'selesai' }">
                        <span x-show="feed.status === 'dibuka'" class="h-1.5 w-1.5 animate-pulse rounded-full bg-neon"></span><span x-text="feed.statusLabel"></span>
                    </span>
                </div>
                <template x-if="feed.url">
                    <div class="p-4 text-center">
                        <div class="relative mx-auto w-fit rounded-3xl bg-white p-3"><canvas data-qr data-size="260" class="block"></canvas></div>
                        @if ($session->checkin_mode === 'qr')
                            <div class="mt-4 flex items-center justify-center gap-3">
                                <p class="font-display text-4xl font-bold tracking-[.25em] text-neon" x-text="feed.token"></p>
                                <svg viewBox="0 0 36 36" class="h-9 w-9 -rotate-90"><circle cx="18" cy="18" r="15" fill="none" stroke="rgb(255 255 255 / .1)" stroke-width="3"/><circle cx="18" cy="18" r="15" fill="none" stroke="var(--color-neon)" stroke-width="3" stroke-linecap="round" pathLength="100" :stroke-dasharray="`${ring} 100`" class="transition-all duration-1000 ease-linear"/></svg>
                            </div>
                            <p class="mt-1 text-xs text-mist">QR & kode berganti tiap 30 detik</p>
                        @else
                            <p class="mt-3 text-sm text-mist">Mode terbuka: peserta bisa absen dari beranda AstoOne.</p>
                        @endif
                    </div>
                </template>
                <div x-show="!feed.url" class="px-4 py-10 text-center">
                    <i class="text-4xl" :class="feed.status === 'terjadwal' ? 'ri-hourglass-2-line text-hivis' : 'ri-lock-line text-mist'"></i>
                    <p class="mt-2 font-semibold" x-text="feed.status === 'terjadwal' ? 'Absen dibuka {{ $session->opens_at->format('H:i') }}' : 'Sesi sudah ditutup'"></p>
                </div>
                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 border-t border-white/[0.06] px-4 py-3 text-sm">
                    <div><dt class="text-xs text-mist">Lokasi</dt><dd>{{ $session->location ?: '–' }}</dd></div>
                    <div><dt class="text-xs text-mist">Host</dt><dd class="truncate">{{ $session->host?->displayName() ?? '–' }}</dd></div>
                    <div><dt class="text-xs text-mist">Absen dibuka</dt><dd>{{ $session->opens_at->format('H:i') }} – {{ $session->closes_at->format('H:i') }}</dd></div>
                    <div><dt class="text-xs text-mist">Terlambat setelah</dt><dd>{{ $session->starts_at->copy()->addMinutes($session->late_after)->format('H:i') }}</dd></div>
                    @if ($session->speaker_nrp)<div class="col-span-2"><dt class="text-xs text-mist">Pemateri</dt><dd>{{ $session->speaker_name ? \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($session->speaker_name)) : $session->speaker_nrp }}</dd></div>@endif
                </dl>
                <form method="POST" action="{{ route('admin.attendance.close', $session) }}" class="border-t border-white/[0.06] p-3">
                    @csrf
                    <template x-if="feed.status === 'dibuka'"><button class="btn-ghost w-full !min-h-10 text-sm text-red-300"><i class="ri-lock-line"></i> Tutup absen sekarang</button></template>
                    <template x-if="feed.status === 'selesai'"><span><input type="hidden" name="reopen" value="1"><button class="btn-ghost w-full !min-h-10 text-sm"><i class="ri-lock-unlock-line"></i> Buka lagi 15 menit</button></span></template>
                </form>
            </section>

            {{-- Catat manual --}}
            <section class="a-card p-4" x-data="{ q: '', results: [], busy: false, msg: '',
                    async search() { if (this.q.trim().length < 2) { this.results = []; return; } const r = await fetch(@js(route('admin.attendance.people')) + '?q=' + encodeURIComponent(this.q)); this.results = await r.json(); },
                    async add(nrp) { this.busy = true; this.msg = ''; try { const d = await postJson(@js(route('admin.attendance.records.store', $session)), { nrp }); $store.toast.show(d.message); this.q = ''; this.results = []; refresh(); } catch { this.msg = 'Gagal mencatat. NRP mungkin belum terdaftar.'; } finally { this.busy = false; } } }">
                <h2 class="font-display font-semibold">Catat kehadiran manual</h2>
                <p class="text-xs text-mist">Untuk peserta tanpa HP atau yang lupa scan.</p>
                <div class="relative mt-3">
                    <i class="ri-search-line pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-mist"></i>
                    <input x-model="q" @input.debounce.250ms="search()" @keydown.enter.prevent="results[0] && add(results[0].nrp)" placeholder="Ketik NRP atau nama" class="field !py-2.5 pl-10">
                </div>
                <ul class="mt-2 space-y-1" x-show="results.length">
                    <template x-for="p in results" :key="p.nrp">
                        <li><button type="button" @click="add(p.nrp)" :disabled="busy" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left hover:bg-white/[0.05]">
                            <span class="min-w-0 grow"><span class="block truncate text-sm font-medium" x-text="p.name"></span><span class="text-xs text-mist" x-text="p.nrp + ' · ' + (p.department || '–')"></span></span>
                            <i class="ri-user-add-line text-neon"></i></button></li>
                    </template>
                </ul>
                <p x-show="msg" class="error" x-text="msg"></p>
            </section>
        </div>

        {{-- Kolom kanan: angka & daftar --}}
        <div class="min-w-0 space-y-4">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="a-card p-4">
                    <p class="text-xs text-mist">Hadir</p>
                    <p class="mt-1 font-display text-3xl font-bold text-neon"><span x-text="feed.counts.present"></span><span x-show="feed.counts.expected" class="text-lg text-mist" x-text="'/' + feed.counts.expected"></span></p>
                    <div x-show="feed.counts.expected" class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/[0.06]"><div class="h-full rounded-full bg-neon transition-all" :style="`width: ${Math.min(100, feed.counts.present / Math.max(1, feed.counts.expected) * 100)}%`"></div></div>
                </div>
                <div class="a-card p-4"><p class="text-xs text-mist">Terlambat</p><p class="mt-1 font-display text-3xl font-bold" :class="feed.counts.late && 'text-hivis'" x-text="feed.counts.late"></p></div>
                <div class="a-card p-4"><p class="text-xs text-mist">Coaching atasan</p><p class="mt-1 font-display text-3xl font-bold" :class="feed.counts.coaching && 'text-hivis'" x-text="feed.counts.coaching"></p></div>
                <div class="a-card p-4"><p class="text-xs text-mist">Fatigue risk</p><p class="mt-1 font-display text-3xl font-bold" :class="feed.counts.risk && 'text-alarm'" x-text="feed.counts.risk"></p></div>
            </div>

            <template x-if="feed.counts.risk">
                <div class="flex items-start gap-3 rounded-2xl border border-alarm/40 bg-alarm/10 p-4 text-sm">
                    <i class="ri-alarm-warning-fill text-2xl text-alarm"></i>
                    <div><p class="font-semibold">Ada peserta dengan waktu tidur &lt; 5 jam</p><p class="text-fog">Lakukan penilaian fatigue sebelum mereka mulai bekerja atau mengoperasikan unit.</p></div>
                </div>
            </template>

            <section class="a-card overflow-hidden">
                <header class="flex flex-wrap items-center gap-2 border-b border-white/[0.06] px-4 py-3">
                    <h2 class="grow font-display font-semibold">Daftar hadir</h2>
                    @foreach (['semua' => 'Semua', 'terlambat' => 'Terlambat', 'fatigue' => 'Perlu perhatian', 'pemateri' => 'Pemateri'] as $k => $l)
                        <button type="button" @click="filter = '{{ $k }}'" :class="filter === '{{ $k }}' ? 'bg-neon text-[#04170d]' : 'bg-white/[0.05] text-fog'" class="rounded-full px-3 py-1 text-xs font-semibold">{{ $l }}</button>
                    @endforeach
                </header>
                <ul class="max-h-[560px] overflow-y-auto">
                    <template x-for="r in records" :key="r.id">
                        <li class="group flex items-center gap-3 border-b border-white/[0.04] px-4 py-2.5 last:border-0" :class="fresh.has(r.id) && 'arrive'">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/[0.06] font-display text-xs font-bold" x-text="r.initials"></span>
                            <div class="min-w-0 grow">
                                <p class="truncate text-sm font-semibold"><span x-text="r.name"></span> <span x-show="r.role === 'pemateri'" class="text-hivis">⭐</span></p>
                                <p class="truncate text-xs text-mist" x-text="r.nrp + ' · ' + (r.department || '–') + ' · ' + r.method"></p>
                            </div>
                            <span x-show="r.fatigue" class="chip hidden sm:inline-flex" :class="{ 'bg-neon/15 text-neon': r.fatigue === 'fit', 'bg-hivis/15 text-hivis': r.fatigue === 'coaching', 'bg-alarm/15 text-red-300': r.fatigue === 'risk' }" x-text="r.sleep"></span>
                            <span class="w-12 shrink-0 text-right font-display text-sm" :class="r.late ? 'text-hivis' : 'text-fog'" x-text="r.time"></span>
                            <form method="POST" :action="@js(url('admin/absensi/'.$session->id.'/hadir')) + '/' + r.id" onsubmit="return confirm('Hapus kehadiran ini?')" class="opacity-0 transition group-hover:opacity-100">
                                @csrf @method('DELETE')
                                <button class="icon-btn h-8 w-8" aria-label="Hapus"><i class="ri-close-line"></i></button>
                            </form>
                        </li>
                    </template>
                    <li x-show="!records.length" class="px-4 py-10 text-center text-sm text-mist">Belum ada yang absen di kategori ini.</li>
                </ul>
            </section>

            <section x-show="feed.counts.expected" class="a-card overflow-hidden">
                <header class="flex items-center border-b border-white/[0.06] px-4 py-3">
                    <h2 class="grow font-display font-semibold">Belum hadir <span class="text-mist" x-text="'(' + feed.missing.length + ')'"></span></h2>
                    <button type="button" x-show="feed.missing.length" @click="navigator.clipboard?.writeText('Belum absen {{ $session->type->name }} {{ $session->starts_at->format('d/m H:i') }}:\n' + feed.missing.map((m, i) => (i + 1) + '. ' + m.name + ' (' + m.nrp + ')').join('\n')); $store.toast.show('Daftar disalin — tempel di grup WhatsApp')" class="text-xs font-semibold text-neon-soft"><i class="ri-file-copy-line"></i> Salin untuk WA</button>
                </header>
                <ul class="grid max-h-[420px] overflow-y-auto sm:grid-cols-2">
                    <template x-for="m in feed.missing" :key="m.nrp">
                        <li class="flex items-center gap-2 border-b border-white/[0.04] px-4 py-2">
                            <div class="min-w-0 grow"><p class="truncate text-sm" x-text="m.name"></p><p class="text-xs text-mist" x-text="m.nrp + ' · ' + m.department"></p></div>
                            <button type="button" x-show="feed.status !== 'terjadwal'" @click="postJson(@js(route('admin.attendance.records.store', $session)), { nrp: m.nrp }).then(d => { $store.toast.show(d.message); refresh(); })" class="shrink-0 rounded-lg bg-neon/10 px-2.5 py-1 text-xs font-semibold text-neon hover:bg-neon/20">Hadir</button>
                        </li>
                    </template>
                    <li x-show="!feed.missing.length" class="col-span-2 px-4 py-6 text-center text-sm text-neon">Semua peserta sasaran sudah hadir 🎉</li>
                </ul>
            </section>
        </div>
    </div>
</x-layouts.admin>
