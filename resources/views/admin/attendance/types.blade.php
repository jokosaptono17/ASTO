<x-layouts.admin title="Jenis kegiatan">
    @include('admin.attendance._tabs')
    <div class="grid max-w-5xl gap-5 lg:grid-cols-[1fr_340px]">
        <section class="a-card overflow-hidden" x-data="{ edit: null }">
            @foreach ($types as $t)
                <div class="border-b border-white/[0.05] px-4 py-3 last:border-0">
                    <div x-show="edit !== {{ $t->id }}" class="flex items-center gap-3 {{ $t->is_active ? '' : 'opacity-50' }}">
                        <span class="grid h-10 w-10 place-items-center rounded-xl" style="background: color-mix(in oklab, {{ $t->color }} 16%, transparent); color: {{ $t->color }}"><i class="{{ $t->icon }} text-xl"></i></span>
                        <div class="grow"><p class="font-semibold">{{ $t->name }}</p><p class="text-xs text-mist">{{ $t->sessions_count }} sesi · {{ $t->track_fatigue ? 'cek fatigue' : 'tanpa cek fatigue' }} · {{ $t->allow_speaker ? 'ada pemateri' : 'tanpa pemateri' }}{{ $t->is_active ? '' : ' · nonaktif' }}</p></div>
                        <button type="button" @click="edit = {{ $t->id }}" class="icon-btn" aria-label="Ubah"><i class="ri-pencil-line text-lg"></i></button>
                    </div>
                    <form x-show="edit === {{ $t->id }}" x-cloak method="POST" action="{{ route('admin.attendance.types.update', $t) }}" class="grid gap-2 sm:grid-cols-[1fr_110px_160px]">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $t->name }}" required maxlength="40" class="field !py-2 text-sm">
                        <input name="color" type="color" value="{{ $t->color }}" class="h-10 w-full rounded-xl border border-white/10 bg-transparent">
                        <input name="icon" value="{{ $t->icon }}" required class="field !py-2 font-mono text-sm">
                        <div class="flex flex-wrap items-center gap-4 text-sm sm:col-span-3">
                            <label class="flex items-center gap-2"><input type="checkbox" name="track_fatigue" value="1" @checked($t->track_fatigue) class="h-4 w-4 accent-[var(--color-neon)]"> Cek fatigue</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="allow_speaker" value="1" @checked($t->allow_speaker) class="h-4 w-4 accent-[var(--color-neon)]"> Ada pemateri</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($t->is_active) class="h-4 w-4 accent-[var(--color-neon)]"> Aktif</label>
                            <span class="grow"></span>
                            <button type="button" @click="edit = null" class="btn-ghost !min-h-9 text-sm">Batal</button>
                            <button form="del-type-{{ $t->id }}" class="btn-ghost !min-h-9 text-sm text-red-300">Hapus</button>
                            <button class="btn-neon !min-h-9 text-sm">Simpan</button>
                        </div>
                    </form>
                    <form id="del-type-{{ $t->id }}" method="POST" action="{{ route('admin.attendance.types.destroy', $t) }}" onsubmit="return confirm('Hapus jenis {{ $t->name }}?')">@csrf @method('DELETE')</form>
                </div>
            @endforeach
        </section>
        <form method="POST" action="{{ route('admin.attendance.types.store') }}" class="a-card space-y-3 self-start p-5">
            @csrf
            <h2 class="font-display font-semibold">Tambah jenis</h2>
            <input name="name" required maxlength="40" placeholder="Contoh: Briefing pagi" class="field !py-2.5">
            <div class="flex gap-2"><input name="color" type="color" value="#38BDF8" class="h-11 w-16 rounded-xl border border-white/10 bg-transparent"><input name="icon" value="ri-megaphone-fill" required class="field !py-2.5 font-mono text-sm"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="track_fatigue" value="1" checked class="h-4 w-4 accent-[var(--color-neon)]"> Tanyakan jam tidur (cek fatigue)</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_speaker" value="1" checked class="h-4 w-4 accent-[var(--color-neon)]"> Ada peran pemateri</label>
            <input type="hidden" name="is_active" value="1">
            <button class="btn-neon w-full !min-h-11 text-sm">Tambah</button>
        </form>
    </div>
</x-layouts.admin>
