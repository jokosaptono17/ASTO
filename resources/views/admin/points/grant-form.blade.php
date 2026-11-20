<x-layouts.admin :title="$grant->exists ? $grant->title : 'Pemberian poin baru'">
    @include('admin.points._tabs')
    @php
        $editable = ! $grant->exists || $grant->isEditable();
        $st = \App\Models\PointGrant::STATUSES[$grant->status ?? 'draft'];
        $fmt = fn ($n) => number_format($n, 0, ',', '.');
    @endphp

    {{-- Status & persetujuan --}}
    @if ($grant->exists)
        <section class="mb-5 flex flex-wrap items-center gap-4 rounded-2xl border p-4 {{ ['pending' => 'border-hivis/40 bg-hivis/[0.06]', 'approved' => 'border-neon/30 bg-neon/[0.05]', 'rejected' => 'border-alarm/40 bg-alarm/[0.06]'][$grant->status] ?? 'border-white/10 bg-bench' }}">
            <span class="{{ $st['chip'] }} !px-3 !py-1 !text-sm">{{ $st['label'] }}</span>
            <p class="grow text-sm text-fog">
                Dibuat {{ $grant->creator?->displayName() ?? '–' }}
                @if ($grant->submitted_at) · diajukan {{ $grant->submitted_at->locale('id')->translatedFormat('j M Y H:i') }} @endif
                @if ($grant->reviewer) · ditinjau {{ $grant->reviewer->displayName() }} {{ $grant->reviewed_at?->locale('id')->diffForHumans() }} @endif
                @if ($grant->review_note) <br><span class="text-mist">Catatan: {{ $grant->review_note }}</span> @endif
            </p>
            @if ($grant->status === 'pending')
                @can('admin')
                    @if (! $approveBlock)
                        <div x-data="{ reject: false }" class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.points.grants.approve', $grant) }}" onsubmit="return confirm('Kirim {{ $fmt($grant->total_points) }} poin ke {{ $grant->recipients->count() }} karyawan sekarang?')">
                                @csrf
                                <button class="btn-neon !min-h-11 text-sm"><i class="ri-check-double-line text-lg"></i> Setujui & kirim poin</button>
                            </form>
                            <button type="button" @click="reject = !reject" class="btn-ghost !min-h-11 text-sm text-red-300"><i class="ri-close-line"></i> Tolak</button>
                            <form x-show="reject" x-cloak method="POST" action="{{ route('admin.points.grants.reject', $grant) }}" class="flex w-full gap-2">
                                @csrf
                                <input name="note" required maxlength="200" placeholder="Alasan penolakan" class="field !py-2">
                                <button class="btn-ghost !min-h-10 shrink-0 text-sm text-red-300">Kirim penolakan</button>
                            </form>
                        </div>
                    @else
                        <p class="text-sm text-hivis"><i class="ri-information-line"></i> {{ $approveBlock }}</p>
                    @endif
                @else
                    <p class="text-sm text-mist"><i class="ri-time-line"></i> Menunggu Administrator</p>
                @endcan
            @endif
            @error('review') <p class="error w-full">{{ $message }}</p> @enderror
        </section>
    @endif

    <form method="POST" action="{{ $grant->exists ? route('admin.points.grants.update', $grant) : route('admin.points.grants.store') }}"
          x-data="grantEditor(@js($recipients), @js(route('admin.points.people')), @js($editable))" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        @if ($grant->exists) @method('PUT') @endif

        <div class="min-w-0 space-y-5">
            <section class="a-card space-y-4 p-5">
                <div>
                    <label class="label" for="title">Nama event / alasan</label>
                    <input id="title" name="title" value="{{ old('title', $grant->title) }}" required maxlength="120" @disabled(! $editable) class="field" placeholder="Contoh: Juara ASTO CUP Season 8">
                    @error('title') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="description">Keterangan (opsional)</label>
                    <textarea id="description" name="description" rows="2" maxlength="1000" @disabled(! $editable) class="field" placeholder="Tampil di riwayat poin penerima">{{ old('description', $grant->description) }}</textarea>
                </div>
            </section>

            {{-- Penerima --}}
            <section class="a-card overflow-hidden">
                <div class="flex flex-wrap items-center gap-3 border-b border-white/[0.06] p-5">
                    <h2 class="grow font-display text-lg font-semibold">Penerima <span class="text-mist" x-text="`(${list.length})`"></span></h2>
                    <template x-if="editable">
                        <label class="flex items-center gap-2 text-sm text-fog">Poin bawaan
                            <input type="number" min="1" max="100000" x-model.number="defaultPoints" class="field !w-24 !py-1.5 text-center font-display">
                        </label>
                    </template>
                </div>

                <template x-if="editable">
                    <div class="grid gap-3 border-b border-white/[0.06] p-5 md:grid-cols-[1fr_auto_auto]">
                        <div class="relative">
                            <i class="ri-user-search-line pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-lg text-mist"></i>
                            <input x-model="q" @input.debounce.250ms="search()" @keydown.enter.prevent="results[0] && add(results[0])" type="search" placeholder="Cari nama atau NRP…" class="field !pl-11" autocomplete="off">
                            <ul x-show="results.length" x-cloak class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-xl border border-white/10 bg-ridge shadow-2xl">
                                <template x-for="p in results" :key="p.id">
                                    <li><button type="button" @click="add(p)" class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-white/[0.06]">
                                        <i class="ri-add-circle-line text-neon"></i><span class="grow"><span class="font-medium" x-text="p.name"></span> <span class="font-display text-xs text-mist" x-text="p.nrp"></span></span><span class="text-xs text-mist" x-text="p.department"></span>
                                    </button></li>
                                </template>
                            </ul>
                        </div>
                        <select @change="addDept($event.target.value); $event.target.value = ''" class="field !w-auto">
                            <option value="">+ Satu seksi…</option>
                            @foreach ($departments as $d) <option value="{{ $d }}">{{ $d }}</option> @endforeach
                        </select>
                        <button type="button" @click="paste = !paste" class="btn-ghost !min-h-[46px] text-sm"><i class="ri-file-excel-2-line text-neon"></i> Tempel dari Excel</button>
                        <div x-show="paste" x-cloak class="md:col-span-3">
                            <textarea x-model="pasteText" rows="4" class="field font-display text-sm" placeholder="KT16042	50	Juara 1&#10;KT17163	30	Juara 2"></textarea>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <button type="button" @click="importPaste()" class="btn-neon !min-h-10 text-sm"><i class="ri-download-2-line"></i> Tambahkan</button>
                                <p class="text-xs text-mist">Salin kolom <b>NRP</b>, <b>poin</b> (opsional), <b>catatan</b> (opsional) dari Excel lalu tempel di sini.</p>
                                <p x-show="pasteMsg" class="w-full text-sm text-hivis" x-text="pasteMsg"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <ul class="max-h-[520px] divide-y divide-white/[0.05] overflow-y-auto">
                    <template x-for="(r, i) in list" :key="r.id">
                        <li class="flex flex-wrap items-center gap-3 px-5 py-2.5">
                            <input type="hidden" :name="`recipients[${i}][user_id]`" :value="r.id">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/[0.06] font-display text-xs font-bold" x-text="r.name.split(' ').map((w) => w[0]).slice(0, 2).join('')"></span>
                            <span class="min-w-0 grow leading-tight"><span class="block truncate text-sm font-medium" x-text="r.name"></span><span class="block truncate text-xs text-mist" x-text="`${r.nrp} · ${r.department || '–'}`"></span></span>
                            <input :name="`recipients[${i}][note]`" x-model="r.note" :readonly="!editable" maxlength="120" placeholder="Catatan (mis. Juara 1)" class="field !w-44 !py-1.5 text-sm">
                            <span class="flex items-center gap-1.5"><x-coin :size="16" /><input :name="`recipients[${i}][points]`" x-model.number="r.points" :readonly="!editable" type="number" min="1" max="100000" class="field !w-24 !py-1.5 text-right font-display font-bold"></span>
                            <button type="button" x-show="editable" @click="list.splice(i, 1)" class="icon-btn h-8 w-8 text-mist hover:text-red-300" aria-label="Hapus"><i class="ri-close-line"></i></button>
                        </li>
                    </template>
                    <li x-show="!list.length" class="px-5 py-12 text-center text-sm text-mist"><i class="ri-team-line text-4xl"></i><p class="mt-2">Belum ada penerima. Cari nama/NRP, pilih satu seksi, atau tempel dari Excel.</p></li>
                </ul>
                @error('recipients') <p class="error px-5 pb-4">{{ $message }}</p> @enderror
                @error('recipients.*') <p class="error px-5 pb-4">{{ $message }}</p> @enderror
            </section>
        </div>

        {{-- Ringkasan --}}
        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <section class="relative overflow-hidden rounded-2xl border border-hivis/30 bg-[radial-gradient(120%_120%_at_100%_0%,rgba(255,194,61,.18),transparent_60%)] bg-bench p-5">
                <p class="text-sm text-mist">Total poin dikirim</p>
                <p class="mt-1 flex items-center gap-2 font-display text-4xl font-bold text-[#ffd76a]"><x-coin :size="34" /><span x-text="fmt(total)"></span></p>
                <p class="mt-1 text-sm text-fog"><span x-text="list.length"></span> karyawan · rata-rata <span x-text="list.length ? fmt(Math.round(total / list.length)) : 0"></span></p>
                @if ($editable)
                    <p class="mt-3 rounded-xl bg-black/25 px-3 py-2 text-xs text-fog" x-show="total > {{ $threshold }}">
                        <i class="ri-shield-check-line text-hivis"></i> Lebih dari {{ $fmt($threshold) }} poin — perlu persetujuan Administrator{{ auth()->user()->isAdmin() ? ' lain' : '' }}.
                    </p>
                @endif
            </section>
            @if ($editable)
                <div class="grid gap-2">
                    <button name="action" value="submit" class="btn-neon !min-h-12" :disabled="!list.length"><i class="ri-send-plane-2-line text-lg"></i>
                        <span x-text="{{ auth()->user()->isAdmin() ? 'true' : 'false' }} && total <= {{ $threshold }} ? 'Kirim poin sekarang' : 'Ajukan untuk disetujui'"></span></button>
                    <button name="action" value="draft" class="btn-ghost"><i class="ri-draft-line"></i> Simpan draf</button>
                </div>
            @endif
            <a href="{{ route('admin.points.grants.index') }}" class="block text-center text-sm text-mist hover:text-fog">← Kembali ke daftar</a>
        </aside>
    </form>

    @if ($grant->exists && $grant->isEditable())
        <form method="POST" action="{{ route('admin.points.grants.destroy', $grant) }}" class="mt-6 border-t border-white/[0.06] pt-4" onsubmit="return confirm('Hapus draf ini?')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-red-300"><i class="ri-delete-bin-line"></i> Hapus draf</button>
        </form>
    @endif
</x-layouts.admin>
