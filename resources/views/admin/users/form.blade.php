<x-layouts.admin :title="$user->exists ? 'Ubah pengguna' : 'Tambah pengguna'">
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="max-w-2xl space-y-5">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <section class="a-card grid gap-5 p-5 sm:grid-cols-2">
            <div>
                <label class="label" for="nrp">NRP</label>
                <input id="nrp" name="nrp" value="{{ old('nrp', $user->nrp) }}" required maxlength="30" class="field font-display uppercase">
            </div>
            <div>
                <label class="label" for="name">Nama lengkap</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="field">
                <p class="mt-1 text-xs text-mist">Nama depan dipakai untuk verifikasi saat aktivasi.</p>
            </div>
            <div>
                <label class="label" for="department">Departemen</label>
                <input id="department" name="department" value="{{ old('department', $user->department) }}" maxlength="100" class="field">
            </div>
            <div>
                <label class="label" for="position">Jabatan</label>
                <input id="position" name="position" value="{{ old('position', $user->position) }}" maxlength="100" class="field">
            </div>
            <div>
                <label class="label" for="phone">No HP</label>
                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" inputmode="tel" class="field">
            </div>
            <div>
                <label class="label" for="role">Peran</label>
                <select id="role" name="role" class="field">
                    @foreach (\App\Models\User::ROLES as $k => $l) <option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $l }}</option> @endforeach
                </select>
                <p class="mt-1 text-xs text-mist">Admin SHE: kelola kampanye & pengumuman. Administrator: semua menu.</p>
            </div>
            <label class="flex items-center gap-3 rounded-2xl bg-white/[0.03] p-3.5 text-sm sm:col-span-2">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="h-5 w-5 accent-[var(--color-neon)]">
                <span><span class="block font-semibold">Aktif</span><span class="text-mist">Matikan untuk karyawan mutasi/resign — tidak bisa masuk lagi</span></span>
            </label>
        </section>
        @if ($user->exists)
            <p class="text-sm text-mist">Status: {{ $user->isActivated() ? 'sudah aktivasi '.$user->activated_at?->locale('id')->translatedFormat('j M Y') : 'belum aktivasi' }} · masuk terakhir {{ $user->last_login_at?->locale('id')->diffForHumans() ?? '–' }}</p>
        @endif
        <div class="flex gap-3">
            <button class="btn-neon"><i class="ri-save-3-line text-lg"></i> Simpan</button>
            <a href="{{ route('admin.users.index') }}" class="btn-ghost">Batal</a>
        </div>
    </form>
    @if ($user->exists && ! $user->is(auth()->user()))
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-8 max-w-2xl border-t border-white/[0.06] pt-5" onsubmit="return confirm('Hapus {{ $user->displayName() }}? Riwayat kunjungannya ikut dianonimkan.')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-red-300"><i class="ri-delete-bin-line text-lg"></i> Hapus pengguna</button>
        </form>
    @endif
</x-layouts.admin>
