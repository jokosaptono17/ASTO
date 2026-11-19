{{-- Kaki halaman untuk layar lebar --}}
<footer class="relative mt-20 hidden overflow-hidden border-t border-white/[0.06] bg-bench/60 lg:block">
    <x-contour class="pointer-events-none absolute -bottom-24 -left-20 h-80 w-[640px] opacity-30" />
    <div class="relative mx-auto grid max-w-7xl grid-cols-[1.4fr_1fr_1fr_1.2fr] gap-10 px-8 py-12">
        <div>
            <div class="flex items-center gap-3">
                <img src="/images/plant-asto.png" alt="" class="h-12 w-auto">
                <span class="font-display text-2xl font-bold">{{ $siteName }}</span>
            </div>
            <p class="mt-3 max-w-xs text-sm leading-relaxed text-mist">{{ \App\Models\Setting::get('site_tagline') }} — satu pintu untuk aplikasi, absensi, dan informasi Plant ASTO.</p>
            <p class="mt-4 font-display text-sm font-bold tracking-wide text-neon">{{ \App\Models\Setting::get('slogan') }}</p>
        </div>
        <div>
            <p class="font-display font-semibold">Portal</p>
            <ul class="mt-3 space-y-2 text-sm text-mist">
                <li><a href="{{ route('home') }}" class="hover:text-ink">Beranda</a></li>
                <li><a href="{{ route('apps.index') }}" class="hover:text-ink">Semua aplikasi</a></li>
                <li><a href="{{ route('attendance.scan') }}" class="hover:text-ink">Absen P5M & safety talk</a></li>
                <li><a href="{{ route('info.index') }}" class="hover:text-ink">Info & kampanye</a></li>
            </ul>
        </div>
        <div>
            <p class="font-display font-semibold">Bantuan</p>
            <ul class="mt-3 space-y-2 text-sm text-mist">
                <li><a href="{{ \App\Models\Setting::whatsappLink() }}" target="_blank" rel="noopener" class="hover:text-ink"><i class="ri-whatsapp-line text-neon"></i> WhatsApp admin</a></li>
                <li><button type="button" x-data @click="$dispatch('mentari-open')" class="hover:text-ink"><i class="ri-robot-2-line text-neon"></i> Tanya Mentari</button></li>
                <li><a href="{{ route('profile') }}" class="hover:text-ink"><i class="ri-lock-password-line text-neon"></i> Ganti PIN</a></li>
            </ul>
        </div>
        <div x-data>
            <p class="font-display font-semibold">Pasang di HP & komputer</p>
            <p class="mt-3 text-sm leading-relaxed text-mist">Buka {{ $siteName }} langsung dari layar utama tanpa mengetik alamat.</p>
            <button x-show="$store.install.prompt && !$store.install.installed" x-cloak type="button" @click="$store.install.run()" class="btn-neon mt-3 !min-h-10 text-sm"><i class="ri-download-2-line"></i> Pasang aplikasi</button>
            <span class="mt-4 inline-block rounded-xl bg-white/90 px-3 py-1.5"><img src="/images/kpp-mining.png" alt="KPP Mining" class="h-7 w-auto"></span>
        </div>
    </div>
    <div class="relative border-t border-white/[0.06]">
        <p class="mx-auto max-w-7xl px-8 py-4 text-xs text-mist/70">© {{ now()->year }} Plant ASTO · PT Kalimantan Prima Persada. Untuk karyawan & mitra terdaftar.</p>
    </div>
</footer>
