{{-- Kolom samping beranda desktop --}}
<aside class="hidden space-y-6 lg:block">
    {{-- Pengumuman --}}
    <section class="a-card overflow-hidden">
        <div class="flex items-center justify-between px-5 pt-4 pb-2">
            <h2 class="font-display text-lg font-semibold">Pengumuman</h2>
            <a href="{{ route('info.index') }}" class="section-link">Semua <i class="ri-arrow-right-s-line"></i></a>
        </div>
        @forelse ($announcements as $a)
            @php $lv = \App\Models\Announcement::LEVELS[$a->level]; @endphp
            <a href="{{ route('info.show', $a) }}" class="group flex gap-3 border-t border-white/[0.05] px-5 py-3.5 transition hover:bg-white/[0.03]">
                <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $a->level === 'darurat' ? 'bg-alarm/15 text-alarm' : ($a->level === 'penting' ? 'bg-hivis/15 text-hivis' : 'bg-neon/10 text-neon') }}"><i class="{{ $lv['icon'] }} text-lg"></i></span>
                <span class="min-w-0">
                    <span class="line-clamp-2 text-[14.5px] leading-snug font-semibold group-hover:text-neon">{{ $a->title }}</span>
                    <span class="mt-0.5 block text-xs text-mist">{{ $a->publishedAt()->locale('id')->diffForHumans() }}</span>
                </span>
                @if ($user->announcements_seen_at === null || $a->publishedAt()->gt($user->announcements_seen_at))
                    <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-neon shadow-[0_0_8px_var(--color-neon)]" title="Belum dibaca"></span>
                @endif
            </a>
        @empty
            <p class="border-t border-white/[0.05] px-5 py-6 text-sm text-mist">Belum ada pengumuman.</p>
        @endforelse
    </section>

    {{-- Favorit --}}
    <section class="a-card p-5">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold">Favorit saya</h2>
            <a href="{{ route('apps.index', ['tab' => 'favorit']) }}" class="section-link">Atur <i class="ri-arrow-right-s-line"></i></a>
        </div>
        @if ($favorites->isNotEmpty())
            <div class="mt-4 grid grid-cols-4 gap-2">
                @foreach ($favorites->take(8) as $app)
                    <a href="{{ route('apps.go', $app) }}" @if ($app->new_tab) target="_blank" rel="noopener" @endif class="flex flex-col items-center gap-1.5 rounded-xl p-2 text-center transition hover:bg-white/[0.05]" title="{{ $app->name }}">
                        <x-app-icon :app="$app" variant="chip" :size="42" />
                        <span class="line-clamp-1 w-full text-[11.5px] text-fog">{{ $app->name }}</span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="mt-3 flex items-start gap-2 text-sm text-mist"><i class="ri-star-line mt-0.5 text-hivis"></i> Tandai bintang pada aplikasi yang sering Anda pakai di halaman Aplikasi.</p>
        @endif
    </section>

    {{-- Bantuan --}}
    <section class="relative overflow-hidden rounded-2xl border border-neon/20 bg-gradient-to-br from-neon/[0.10] to-bench p-5">
        <x-contour class="pointer-events-none absolute -top-10 -right-16 h-40 w-72 opacity-30" />
        <div class="relative flex items-center gap-3">
            <x-mentari :size="64" class="m-still shrink-0" />
            <div>
                <p class="font-display text-lg font-semibold">Butuh bantuan?</p>
                <p class="text-sm text-fog">Tanya Mentari soal aplikasi, data training, atau absensi.</p>
            </div>
        </div>
        <div class="relative mt-4 grid grid-cols-2 gap-2">
            <button type="button" @click="$dispatch('mentari-open')" class="btn-neon !min-h-10 text-sm"><i class="ri-chat-smile-3-line"></i> Mentari</button>
            <a href="{{ \App\Models\Setting::whatsappLink() }}" target="_blank" rel="noopener" class="btn-ghost !min-h-10 text-sm"><i class="ri-whatsapp-line text-neon"></i> Admin</a>
        </div>
    </section>
</aside>
