<x-layouts.guest title="Masuk ke MekanikPintar">
    <div class="flex items-center justify-between">
        <img src="/images/plant-asto.png" alt="Plant ASTO" class="h-10 w-auto">
        <span class="rounded-full bg-neon/10 px-3 py-1 text-xs font-semibold text-neon ring-1 ring-neon/30">AstoOne</span>
    </div>

    <div class="mt-8 text-center">
        <div class="mx-auto flex items-center justify-center gap-3">
            <div class="grid h-16 w-16 place-items-center rounded-full bg-gradient-to-b from-neon/30 to-neon/5 font-display text-2xl font-bold text-neon ring-1 ring-neon/40">{{ $user->initials() }}</div>
            <i class="ri-arrow-right-line text-2xl text-mist"></i>
            <div class="grid h-16 w-16 place-items-center rounded-full ring-1 ring-white/10" style="background:#fbbf24;color:#1a1203" aria-label="MekanikPintar"><i class="ri-tools-fill text-3xl"></i></div>
        </div>
        <h1 class="mt-5 font-display text-2xl font-semibold">Masuk ke MekanikPintar?</h1>
        <p class="mt-2 text-[15px] text-fog">Anda akan masuk ke aplikasi training <b class="text-white">MekanikPintar</b> sebagai:</p>
    </div>

    <div class="mt-5 rounded-2xl border border-white/10 bg-black/30 p-4 text-center">
        <p class="font-display text-lg font-semibold">{{ $user->name }}</p>
        <p class="mt-0.5 text-sm text-mist">NRP {{ $user->nrp }}</p>
    </div>

    <form method="POST" action="{{ route('sso.mekanikpintar.approve') }}" class="mt-6" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        <button class="btn-neon w-full" :disabled="busy"><span x-text="busy ? 'Membuka MekanikPintar…' : 'Lanjutkan'">Lanjutkan</span></button>
    </form>
    <div class="mt-3 grid grid-cols-2 gap-3">
        <form method="POST" action="{{ route('sso.mekanikpintar.switch') }}">@csrf<button class="w-full rounded-2xl border border-white/10 py-3 text-sm font-semibold text-fog hover:text-white">Bukan saya</button></form>
        <form method="POST" action="{{ route('sso.mekanikpintar.cancel') }}">@csrf<button class="w-full rounded-2xl border border-white/10 py-3 text-sm font-semibold text-fog hover:text-white">Batal</button></form>
    </div>

    <p class="mt-auto pt-6 text-center text-xs leading-relaxed text-mist"><i class="ri-shield-check-line text-neon"></i> AstoOne hanya mengirim NRP & nama Anda. PIN tidak pernah dibagikan.</p>
</x-layouts.guest>
