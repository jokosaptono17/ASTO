<x-layouts.guest title="Masuk">
    @include('auth._brand')

    <div class="mt-auto pt-10 lg:mt-12 lg:pt-0">
        <x-mentari :size="132" class="mb-2 -ml-3" />
        <h1 class="font-display text-[2rem] leading-[1.05] font-bold text-balance lg:hidden">Satu pintu untuk semua aplikasi Plant ASTO</h1>
        <h1 class="hidden font-display text-[2.2rem] leading-[1.05] font-bold lg:block">Masuk</h1>
        <p class="mt-3 max-w-[34ch] text-[15px] leading-relaxed text-fog">Masuk dengan NRP Anda. Pertama kali? Anda akan diminta membuat PIN 6 angka.</p>
    </div>

    <form method="POST" action="{{ route('login.check') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label for="nrp" class="label">NRP</label>
            <div class="relative">
                <i class="ri-id-card-line pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-xl text-mist"></i>
                <input id="nrp" name="nrp" value="{{ $nrp }}" required autofocus autocomplete="username" autocapitalize="characters" spellcheck="false"
                       placeholder="Contoh: KT17163" class="field h-14 pl-12 font-display text-lg tracking-wider uppercase placeholder:normal-case placeholder:tracking-normal">
            </div>
            @error('nrp') <p class="error"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
        </div>

        @if (session('unregistered'))
            <div class="rounded-2xl border border-hivis/30 bg-hivis/[0.07] p-4">
                <div class="flex gap-3">
                    <i class="ri-user-search-line text-2xl text-hivis"></i>
                    <div class="text-sm leading-relaxed">
                        <p class="font-semibold text-ink">NRP {{ session('unregistered') }} belum terdaftar.</p>
                        <p class="mt-0.5 text-fog">Periksa lagi penulisannya. Kalau sudah benar, minta admin mendaftarkan NRP Anda.</p>
                    </div>
                </div>
                <x-wa-help class="mt-3 w-full" :text="'Halo Admin AstoOne, NRP saya belum terdaftar. NRP: '.session('unregistered').' Nama: … Departemen: …'">Daftarkan NRP saya</x-wa-help>
            </div>
        @endif

        <button class="btn-neon w-full">Lanjut <i class="ri-arrow-right-line text-lg"></i></button>
    </form>

    <div class="mt-5 flex items-center justify-between gap-3 text-sm">
        <a href="{{ \App\Models\Setting::whatsappLink('Halo Admin AstoOne, saya butuh bantuan masuk. Nama: … NRP: …') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-semibold text-neon-soft">
            <i class="ri-customer-service-2-line text-lg"></i> Butuh bantuan?
        </a>
        <button type="button" x-data @click="$dispatch('mentari-open')" class="inline-flex items-center gap-1.5 text-mist hover:text-ink">
            <i class="ri-chat-smile-3-line text-lg"></i> Tanya Mentari
        </button>
    </div>

    <p class="mt-8 text-center text-xs text-mist/70">Khusus karyawan PT Kalimantan Prima Persada jobsite ASTO</p>
</x-layouts.guest>
