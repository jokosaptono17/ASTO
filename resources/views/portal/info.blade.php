<x-layouts.portal title="Info">
    @include('portal._topbar', ['heading' => 'Info', 'sub' => 'Pengumuman dan kampanye Plant ASTO'])

    <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:items-start lg:gap-10">
    <section class="px-4 lg:px-0" aria-label="Pengumuman">
        <h2 class="section-title mb-4 hidden lg:block">Pengumuman</h2>
        @forelse ($announcements as $a)
            @php $new = ! $seenAt || $a->publishedAt()->gt($seenAt); @endphp
            <a href="{{ route('info.show', $a) }}" class="mb-2.5 flex gap-3 rounded-2xl border p-4 transition hover:border-white/15
                {{ $a->level === 'darurat' ? 'border-alarm/40 bg-alarm/[0.08]' : ($a->level === 'penting' ? 'border-hivis/25 bg-hivis/[0.05]' : 'border-white/[0.06] bg-bench') }}">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl
                    {{ $a->level === 'darurat' ? 'bg-alarm/15 text-alarm' : ($a->level === 'penting' ? 'bg-hivis/15 text-hivis' : 'bg-neon/10 text-neon') }}">
                    <i class="{{ \App\Models\Announcement::LEVELS[$a->level]['icon'] }} text-2xl"></i>
                </span>
                <span class="min-w-0 grow">
                    <span class="flex items-center gap-2">
                        <span class="text-[15px] leading-snug font-semibold">{{ $a->title }}</span>
                        @if ($new) <span class="h-2 w-2 shrink-0 rounded-full bg-neon shadow-[0_0_8px_var(--color-neon)]" title="Baru"></span> @endif
                    </span>
                    <span class="mt-0.5 line-clamp-2 block text-sm leading-relaxed text-fog">{{ \Illuminate\Support\Str::limit(strip_tags($a->body), 140) }}</span>
                    <span class="mt-1.5 flex items-center gap-2 text-xs text-mist">
                        @if ($a->is_pinned) <i class="ri-pushpin-2-fill text-neon"></i> @endif
                        {{ $a->publishedAt()->locale('id')->diffForHumans() }}
                    </span>
                </span>
            </a>
        @empty
            <div class="flex flex-col items-center rounded-[22px] border border-dashed border-white/10 px-6 py-10 text-center">
                <i class="ri-notification-off-line text-4xl text-mist/50"></i>
                <p class="mt-3 text-fog">Belum ada pengumuman. Info baru akan ditandai di lonceng beranda.</p>
            </div>
        @endforelse
    </section>

    <section id="kampanye" class="mt-8 px-4 lg:sticky lg:top-24 lg:mt-0 lg:px-0">
        <h2 class="section-title">{{ \App\Models\Setting::get('campaign_section_title') }}</h2>
        @if ($campaigns->isNotEmpty())
            <div class="mt-3 grid grid-cols-2 gap-3 lg:mt-4 lg:gap-4">
                @foreach ($campaigns as $c)
                    <a href="{{ route('campaigns.open', $c) }}" @if ($c->link_url && ! str_starts_with($c->link_url, '/')) target="_blank" rel="noopener" @endif
                       class="campaign-card {{ ['banner' => 'col-span-2 aspect-[2/1]', 'popup' => 'aspect-[3/4]'][$c->format] ?? 'aspect-[4/5]' }}">
                        <img src="{{ $c->imageUrl() }}" alt="{{ $c->title }}" loading="lazy">
                    </a>
                @endforeach
            </div>
        @else
            <p class="mt-3 text-sm text-mist">Belum ada kampanye yang tayang.</p>
        @endif
    </section>
    </div>
</x-layouts.portal>
