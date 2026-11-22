<x-layouts.admin title="Pengguna">
    <x-slot:actions>
        <a href="{{ route('admin.users.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-user-add-line text-lg"></i> Tambah</a>
        <a href="{{ route('admin.users.export') }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-file-excel-2-line text-lg"></i> Ekspor</a>
    </x-slot:actions>

    <div class="grid gap-5 xl:grid-cols-[1fr_330px]">
        <div class="min-w-0 space-y-4" x-data="userSearch(@js(route('admin.users.index')))">
            {{-- Ringkasan status --}}
            @php
                $tabs = ['' => ['Semua', $counts['all']], 'aktif' => ['Sudah aktivasi', $counts['activated']], 'belum' => ['Belum aktivasi', $counts['pending']], 'nonaktif' => ['Nonaktif', $counts['inactive']]];
            @endphp
            <div class="flex flex-wrap gap-2">
                @foreach ($tabs as $key => [$label, $n])
                    <a href="{{ request()->fullUrlWithQuery(['status' => $key ?: null, 'page' => null]) }}" data-status="{{ $key }}"
                       class="rounded-full px-4 py-1.5 text-sm font-semibold {{ request('status', '') === $key ? 'bg-neon text-[#04170d]' : 'bg-white/[0.05] text-fog hover:bg-white/10' }}">{{ $label }} <span class="opacity-70">{{ $n }}</span></a>
                @endforeach
            </div>

            <form class="flex gap-2" x-ref="form" @submit.prevent="run(0)" role="search">
                @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                <div class="relative grow">
                    <i class="ri-search-line pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-lg text-mist" x-show="!busy"></i>
                    <i class="ri-loader-4-line pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 animate-spin text-lg text-neon" x-show="busy" x-cloak></i>
                    <input type="search" name="q" value="{{ request('q') }}" x-ref="q" @input="run()" @keydown.escape="if ($el.value) { $el.value = ''; run(0) }"
                           placeholder="Ketik NRP, nama, departemen, atau jabatan" autocomplete="off" aria-label="Cari pengguna" class="field !py-2.5 pr-10 pl-10 [&::-webkit-search-cancel-button]:appearance-none">
                    <button type="button" x-show="$refs.q.value" x-cloak @click="$refs.q.value = ''; run(0); $refs.q.focus()" class="absolute top-1/2 right-2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-full text-mist hover:bg-white/10 hover:text-white" aria-label="Hapus pencarian"><i class="ri-close-line text-lg"></i></button>
                </div>
                <select name="role" class="field !w-auto !py-2.5" @change="run(0)" aria-label="Saring peran">
                    <option value="">Semua peran</option>
                    @foreach (\App\Models\User::ROLES as $k => $l) <option value="{{ $k }}" @selected(request('role') === $k)>{{ $l }}</option> @endforeach
                </select>
            </form>

            @fragment('hasil')
            <div id="hasil-pengguna" class="space-y-4">
            @if (request('q') || request('role'))
                <p class="text-sm text-mist" aria-live="polite">
                    <span class="font-semibold text-white">{{ $users->total() }}</span> pengguna cocok
                    @if (request('q')) untuk “<span class="text-neon">{{ request('q') }}</span>”@endif
                </p>
            @endif
            <div class="a-card overflow-x-auto">
                <table class="w-full min-w-[640px]">
                    <thead><tr><th class="a-th">Karyawan</th><th class="a-th">Departemen</th><th class="a-th">Status</th><th class="a-th">Masuk terakhir</th><th class="a-th"></th></tr></thead>
                    <tbody>
                        @forelse ($users as $u)
                            <tr class="{{ $u->is_active ? '' : 'opacity-55' }}">
                                <td class="a-td">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/[0.06] font-display text-xs font-bold">{{ $u->initials() }}</span>
                                        <div class="leading-tight">
                                            <div class="font-semibold">{{ $u->displayName() }}</div>
                                            <div class="font-display text-xs text-mist">{{ $u->nrp }}@if ($u->role !== 'user') <span class="chip-penting ml-1 !py-0">{{ $u->roleLabel() }}</span>@endif</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="a-td text-fog">{{ $u->department ?: '–' }}<div class="text-xs text-mist">{{ $u->position }}{{ $u->grade && ! str_contains((string) $u->position, $u->grade) ? ' · '.$u->grade : '' }}</div></td>
                                <td class="a-td">
                                    @if (! $u->is_active) <span class="chip-nonaktif">Nonaktif</span>
                                    @elseif ($u->isActivated()) <span class="chip-tayang">Aktif</span>
                                    @else <span class="chip-berakhir">Belum aktivasi</span> @endif
                                </td>
                                <td class="a-td text-xs text-mist">{{ $u->last_login_at?->locale('id')->diffForHumans() ?? '–' }}</td>
                                <td class="a-td text-right whitespace-nowrap">
                                    @if ($u->isActivated())
                                        <form method="POST" action="{{ route('admin.users.reset-pin', $u) }}" class="inline" onsubmit="return confirm('Reset PIN {{ $u->displayName() }}? Ia akan membuat PIN baru saat masuk.')">
                                            @csrf <button class="icon-btn inline-grid" title="Reset PIN" aria-label="Reset PIN"><i class="ri-lock-unlock-line text-lg"></i></button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.users.edit', $u) }}" class="icon-btn inline-grid" aria-label="Ubah"><i class="ri-pencil-line text-lg"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="a-td py-10 text-center text-mist">
                                @if (request('q'))
                                    Tidak ada pengguna dengan NRP, nama, atau departemen “{{ request('q') }}”. Coba kata lain, misalnya sebagian NRP.
                                @else
                                    Tidak ada pengguna yang cocok. Impor data karyawan dari Excel di panel samping.
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $users->links('admin.pagination') }}
            </div>
            @endfragment
        </div>

        {{-- Impor Excel --}}
        <aside class="a-card self-start p-5">
            <h2 class="flex items-center gap-2 font-display font-semibold"><i class="ri-file-excel-2-line text-xl text-neon"></i>Impor dari Excel</h2>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-fog">
                <li>Unduh template, isi kolom <b>NRP</b> dan <b>NAMA</b> (Departemen, Jabatan, Grade, GL Wali, Lokasi, Program, No HP opsional).</li>
                <li>Unggah file .xlsx atau .csv. NRP yang sudah ada diperbarui, PIN-nya tetap.</li>
            </ol>
            <a href="{{ route('admin.users.template') }}" class="btn-ghost mt-4 w-full !min-h-10 text-sm"><i class="ri-download-2-line text-lg"></i> Unduh template</a>
            <form method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data" class="mt-3 space-y-3" x-data="{ name: '' }">
                @csrf
                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-white/15 p-3.5 text-sm hover:border-neon/50">
                    <i class="ri-upload-cloud-2-line text-2xl text-neon"></i>
                    <span class="min-w-0 truncate" x-text="name || 'Pilih file Excel / CSV'"></span>
                    <input type="file" name="file" accept=".xlsx,.csv" required class="sr-only" @change="name = $event.target.files[0]?.name">
                </label>
                <label class="flex items-start gap-2.5 text-sm text-fog">
                    <input type="checkbox" name="deactivate_missing" value="1" class="mt-0.5 h-4 w-4 accent-[var(--color-neon)]">
                    Nonaktifkan karyawan yang tidak ada di file (mutasi/resign)
                </label>
                <button class="btn-neon w-full !min-h-11 text-sm">Impor data</button>
            </form>
            @if (session('import'))
                @php $r = session('import'); @endphp
                <div class="mt-4 rounded-xl bg-white/[0.03] p-3 text-sm">
                    <p><span class="text-neon">{{ $r['created'] }}</span> baru · <span class="text-neon">{{ $r['updated'] }}</span> diperbarui</p>
                    @if ($r['skipped'])
                        <ul class="mt-2 max-h-40 space-y-0.5 overflow-y-auto text-xs text-hivis">@foreach ($r['skipped'] as $s)<li>{{ $s }}</li>@endforeach</ul>
                    @endif
                </div>
            @endif
        </aside>
    </div>

    <script>
        // Pencarian langsung: hasil berganti saat mengetik (jeda 250 ms), URL ikut diperbarui.
        function userSearch(url) {
            let timer, ctrl;
            return {
                busy: false,
                run(delay = 250) {
                    clearTimeout(timer);
                    timer = setTimeout(() => this.fetch(), delay);
                },
                async fetch() {
                    const params = new URLSearchParams(new FormData(this.$refs.form));
                    [...params.keys()].forEach((k) => params.get(k) === '' && params.delete(k));
                    const target = url + (params.toString() ? '?' + params : '');
                    ctrl?.abort();
                    ctrl = new AbortController();
                    this.busy = true;
                    try {
                        const res = await fetch(target, { headers: { 'X-Fragment': 'hasil', 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl.signal });
                        if (!res.ok) throw new Error(res.status);
                        document.getElementById('hasil-pengguna').outerHTML = await res.text();
                        history.replaceState(null, '', target);
                        // Tab status ikut membawa kata kunci & peran
                        document.querySelectorAll('a[data-status]').forEach((a) => {
                            const p = new URLSearchParams(params);
                            p.delete('page');
                            a.dataset.status ? p.set('status', a.dataset.status) : p.delete('status');
                            a.href = url + (p.toString() ? '?' + p : '');
                        });
                    } catch (e) {
                        if (e.name !== 'AbortError') location.href = target; // cadangan: muat ulang biasa
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
    </script>
</x-layouts.admin>
