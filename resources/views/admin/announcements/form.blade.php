<x-layouts.admin :title="$announcement->exists ? 'Ubah pengumuman' : 'Pengumuman baru'">
    <form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}" class="max-w-3xl space-y-5" x-data="{ level: @js(old('level', $announcement->level)) }">
        @csrf
        @if ($announcement->exists) @method('PUT') @endif

        <section class="a-card space-y-5 p-5">
            <div>
                <label class="label">Tingkat</label>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (\App\Models\Announcement::LEVELS as $key => $l)
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                               :class="level === '{{ $key }}' ? '{{ ['info' => 'border-neon/60 bg-neon/10 text-neon', 'penting' => 'border-hivis/60 bg-hivis/10 text-hivis', 'darurat' => 'border-alarm/60 bg-alarm/10 text-alarm'][$key] }}' : 'border-white/10 text-fog'">
                            <input type="radio" name="level" value="{{ $key }}" x-model="level" class="sr-only"><i class="{{ $l['icon'] }} text-lg"></i>{{ $l['label'] }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1.5 text-xs text-mist" x-show="level !== 'info'">Pengumuman ini tampil sebagai pita di atas beranda.</p>
            </div>
            <div>
                <label class="label" for="title">Judul</label>
                <input id="title" name="title" value="{{ old('title', $announcement->title) }}" required maxlength="120" class="field">
            </div>
            <div>
                <label class="label" for="body">Isi</label>
                <textarea id="body" name="body" rows="7" required maxlength="5000" class="field leading-relaxed">{{ old('body', $announcement->body) }}</textarea>
            </div>
            <div>
                <label class="label" for="link_url">Tautan (opsional)</label>
                <input id="link_url" name="link_url" value="{{ old('link_url', $announcement->link_url) }}" class="field" placeholder="https://…">
            </div>
        </section>

        <section class="a-card grid gap-5 p-5 sm:grid-cols-2">
            <div>
                <label class="label" for="starts_at">Terbit</label>
                <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}" class="field">
                <p class="mt-1 text-xs text-mist">Kosongkan untuk terbit sekarang.</p>
            </div>
            <div>
                <label class="label" for="ends_at">Berakhir (opsional)</label>
                <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}" class="field">
            </div>
            <label class="flex items-center gap-3 rounded-2xl bg-white/[0.03] p-3.5 text-sm">
                <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned)) class="h-5 w-5 accent-[var(--color-neon)]">
                <span><span class="block font-semibold">Sematkan</span><span class="text-mist">Selalu di urutan teratas</span></span>
            </label>
            <label class="flex items-center gap-3 rounded-2xl bg-white/[0.03] p-3.5 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $announcement->is_active)) class="h-5 w-5 accent-[var(--color-neon)]">
                <span><span class="block font-semibold">Aktif</span><span class="text-mist">Matikan untuk menyembunyikan</span></span>
            </label>
        </section>

        <div class="flex gap-3">
            <button class="btn-neon"><i class="ri-send-plane-line text-lg"></i> {{ $announcement->exists ? 'Simpan' : 'Terbitkan' }}</button>
            <a href="{{ route('admin.announcements.index') }}" class="btn-ghost">Batal</a>
        </div>
    </form>

    @if ($announcement->exists)
        <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" class="mt-8 max-w-3xl border-t border-white/[0.06] pt-5" onsubmit="return confirm('Hapus pengumuman ini?')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-red-300"><i class="ri-delete-bin-line text-lg"></i> Hapus pengumuman</button>
        </form>
    @endif
</x-layouts.admin>
