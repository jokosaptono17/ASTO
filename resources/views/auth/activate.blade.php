<x-layouts.guest title="Aktivasi akun">
    <div class="flex items-center justify-between">
        <a href="{{ route('login.forget') }}" class="icon-btn -ml-2" aria-label="Kembali"><i class="ri-arrow-left-line text-2xl"></i></a>
        <img src="/images/plant-asto.png" alt="Plant ASTO" class="h-10 w-auto">
    </div>

    <div class="mt-6">
        <h1 class="font-display text-[1.75rem] leading-tight font-bold">Aktivasi akun</h1>
        <p class="mt-2 text-[15px] leading-relaxed text-fog">NRP <span class="font-display font-semibold text-ink">{{ $user->nrp }}</span> terdaftar tapi belum aktif. Pastikan ini Anda, lalu buat PIN untuk masuk berikutnya.</p>
    </div>

    <form method="POST" action="{{ route('activate.submit') }}" class="mt-7 space-y-6" x-data="{ step: {{ $errors->has('first_name') || ! old('first_name') ? 1 : 2 }}, name: @js(old('first_name', '')) }">
        @csrf
        <div>
            <label for="first_name" class="label">Nama depan Anda</label>
            <input id="first_name" name="first_name" x-model="name" required autocomplete="given-name" autocapitalize="words" placeholder="Ketik nama depan" class="field h-14 text-lg">
            <p class="mt-1.5 text-sm text-mist">Petunjuk: {{ $hint }}</p>
            @error('first_name') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
            <button type="button" x-show="step === 1" @click="if (name.trim()) step = 2" :disabled="!name.trim()" class="btn-neon mt-4 w-full">Lanjut buat PIN</button>
        </div>

        <div x-show="step === 2" x-cloak class="space-y-6">
            <div>
                <p class="mb-3 text-[15px] font-medium text-fog">Buat PIN 6 angka</p>
                @include('auth._pin-input', ['name' => 'pin', 'auto' => false, 'keypad' => false, 'autocomplete' => 'new-password'])
            </div>
            <div>
                <p class="mb-3 text-[15px] font-medium text-fog">Ulangi PIN</p>
                @include('auth._pin-input', ['name' => 'pin_confirmation', 'auto' => false, 'keypad' => false, 'autocomplete' => 'new-password', 'label' => 'Ulangi PIN'])
            </div>
            @error('pin') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
            <p class="text-sm text-mist">Ketuk kotak PIN untuk memunculkan papan angka. Hindari tanggal lahir atau 123456.</p>
            <button class="btn-neon w-full"><i class="ri-shield-check-line text-lg"></i> Aktifkan akun</button>
        </div>
    </form>

    <div class="mt-auto pt-8">
        <x-wa-help class="w-full" :text="'Halo Admin AstoOne, saya kesulitan aktivasi akun. NRP: '.$user->nrp">Kesulitan? Chat admin</x-wa-help>
    </div>
</x-layouts.guest>
