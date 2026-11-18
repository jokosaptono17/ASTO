<x-layouts.guest title="Masukkan PIN">
    <div class="flex items-center justify-between">
        <a href="{{ route('login.forget') }}" class="icon-btn -ml-2" aria-label="Ganti NRP"><i class="ri-arrow-left-line text-2xl"></i></a>
        <img src="/images/plant-asto.png" alt="Plant ASTO" class="h-10 w-auto">
    </div>

    <div class="mt-8 text-center">
        <div class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-gradient-to-b from-neon/30 to-neon/5 font-display text-3xl font-bold text-neon ring-1 ring-neon/40">{{ $user->initials() }}</div>
        <h1 class="mt-4 font-display text-2xl font-semibold">Halo, {{ $user->firstName() }}</h1>
        <p class="mt-1 text-sm text-mist">NRP {{ $user->nrp }} · <a href="{{ route('login.forget') }}" class="text-neon-soft underline-offset-2 hover:underline">bukan Anda?</a></p>
    </div>

    <form method="POST" action="{{ route('login.pin.submit') }}" class="mt-8">
        @csrf
        <input type="hidden" name="remember" value="1">
        <p class="mb-4 text-center text-[15px] text-fog">Masukkan PIN 6 angka</p>
        @include('auth._pin-input', ['name' => 'pin', 'focus' => true])
        @error('pin') <p class="error justify-center text-center"><i class="ri-error-warning-line mt-0.5"></i>{{ $message }}</p> @enderror
        <noscript><button class="btn-neon mt-6 w-full">Masuk</button></noscript>
    </form>

    <div class="mt-auto pt-6 text-center">
        <a href="{{ \App\Models\Setting::whatsappLink('Halo Admin AstoOne, saya lupa PIN. Mohon reset. Nama: '.$user->name.' NRP: '.$user->nrp) }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-neon-soft"><i class="ri-whatsapp-line text-lg"></i> Lupa PIN? Minta reset ke admin</a>
    </div>
</x-layouts.guest>
