<x-layouts.portal title="Profil">
    @include('portal._topbar', ['heading' => 'Profil'])

    <div class="space-y-5 px-4">
        {{-- Kartu identitas --}}
        <section class="relative overflow-hidden rounded-[26px] border border-neon/20 bg-gradient-to-br from-ridge via-bench to-pit p-5">
            <x-contour class="absolute -top-6 -right-16 h-48 w-80 opacity-50" />
            <div class="relative flex items-center gap-4">
                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-neon font-display text-2xl font-bold text-[#04170d]">{{ $user->initials() }}</span>
                <div class="min-w-0">
                    <p class="truncate font-display text-xl font-semibold">{{ $user->displayName() }}</p>
                    <p class="font-display text-neon-soft">{{ $user->nrp }}</p>
                </div>
            </div>
            <dl class="relative mt-5 grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-mist">Departemen</dt><dd class="mt-0.5 font-semibold">{{ $user->department ?: '–' }}</dd></div>
                <div><dt class="text-mist">Jabatan</dt><dd class="mt-0.5 font-semibold">{{ $user->position ?: '–' }}</dd></div>
                <div><dt class="text-mist">Aplikasi dibuka</dt><dd class="mt-0.5 font-display text-lg font-semibold">{{ number_format($visitCount, 0, ',', '.') }}</dd></div>
                <div><dt class="text-mist">Favorit</dt><dd class="mt-0.5 font-display text-lg font-semibold">{{ $favoriteCount }}</dd></div>
            </dl>
        </section>

        @can('manage-content')
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-2xl border border-hivis/30 bg-hivis/[0.07] p-4">
                <i class="ri-dashboard-3-line text-2xl text-hivis"></i>
                <span class="grow"><span class="block font-semibold">Panel admin</span><span class="text-sm text-mist">{{ $user->roleLabel() }} · kelola konten portal</span></span>
                <i class="ri-arrow-right-s-line text-xl text-mist"></i>
            </a>
        @endcan

        {{-- Pasang aplikasi --}}
        <section x-data class="rounded-2xl border border-white/[0.06] bg-bench p-4">
            <div class="flex items-center gap-3">
                <i class="ri-smartphone-line text-2xl text-neon"></i>
                <div class="grow">
                    <p class="font-semibold">Aplikasi di HP</p>
                    <p class="text-sm text-mist" x-show="$store.install.installed">Sudah terpasang. Buka dari ikon {{ $siteName }} di layar utama.</p>
                    <p class="text-sm text-mist" x-show="!$store.install.installed && $store.install.ios" x-cloak>Safari → tombol Bagikan → Tambah ke Layar Utama.</p>
                    <p class="text-sm text-mist" x-show="!$store.install.installed && !$store.install.ios" x-cloak>Chrome → menu ⋮ → Instal aplikasi.</p>
                </div>
                <button x-show="$store.install.prompt && !$store.install.installed" x-cloak type="button" @click="$store.install.run()" class="btn-neon !min-h-10 !px-4 text-sm">Pasang</button>
            </div>
        </section>

        {{-- Ganti PIN --}}
        <section x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" class="rounded-2xl border border-white/[0.06] bg-bench">
            <button type="button" @click="open = !open" :aria-expanded="open" class="flex w-full items-center gap-3 p-4 text-left">
                <i class="ri-lock-password-line text-2xl text-neon"></i>
                <span class="grow font-semibold">Ganti PIN</span>
                <i class="ri-arrow-down-s-line text-xl text-mist transition" :class="open && 'rotate-180'"></i>
            </button>
            <form x-show="open" x-cloak method="POST" action="{{ route('profile.pin') }}" class="space-y-4 border-t border-white/[0.06] p-4">
                @csrf @method('PUT')
                @foreach (['current_pin' => 'PIN lama', 'pin' => 'PIN baru', 'pin_confirmation' => 'Ulangi PIN baru'] as $field => $label)
                    <div>
                        <label class="label" for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" required
                               autocomplete="{{ $field === 'current_pin' ? 'current-password' : 'new-password' }}" class="field font-display text-xl tracking-[.5em]">
                        @error($field) <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <button class="btn-neon w-full">Simpan PIN baru</button>
            </form>
        </section>

        <x-wa-help class="w-full" :text="'Halo Admin AstoOne, saya butuh bantuan. Nama: '.$user->name.' NRP: '.$user->nrp">Hubungi admin</x-wa-help>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn-ghost w-full text-red-300"><i class="ri-logout-box-r-line text-lg"></i> Keluar</button>
        </form>

        <p class="pt-2 pb-4 text-center text-xs text-mist/60">{{ $siteName }} · {{ \App\Models\Setting::get('slogan') }}</p>
    </div>
</x-layouts.portal>
