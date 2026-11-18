<x-layouts.portal :title="$announcement->title">
    @include('portal._topbar', ['heading' => 'Pengumuman', 'back' => route('info.index')])
    <article class="px-5">
        <span class="chip-{{ $announcement->level }}"><i class="{{ \App\Models\Announcement::LEVELS[$announcement->level]['icon'] }}"></i>{{ \App\Models\Announcement::LEVELS[$announcement->level]['label'] }}</span>
        <h2 class="mt-3 font-display text-2xl leading-tight font-bold text-balance">{{ $announcement->title }}</h2>
        <p class="mt-2 text-sm text-mist">{{ $announcement->publishedAt()->locale('id')->translatedFormat('l, j F Y · H:i') }}</p>
        <div class="mt-5 text-[16px] leading-relaxed whitespace-pre-line text-fog">{{ $announcement->body }}</div>
        @if ($announcement->link_url)
            <a href="{{ $announcement->link_url }}" @if (! str_starts_with($announcement->link_url, '/')) target="_blank" rel="noopener" @endif class="btn-neon mt-6 w-full"><i class="ri-external-link-line text-lg"></i> Buka tautan</a>
        @endif
    </article>
</x-layouts.portal>
