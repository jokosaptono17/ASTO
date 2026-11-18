<x-layouts.admin title="Pengumuman">
    <x-slot:actions>
        <a href="{{ route('admin.announcements.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Pengumuman baru</a>
    </x-slot:actions>

    <div class="a-card overflow-hidden">
        @forelse ($announcements as $a)
            <a href="{{ route('admin.announcements.edit', $a) }}" class="flex items-start gap-4 border-b border-white/[0.05] p-4 last:border-0 hover:bg-white/[0.02]">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $a->level === 'darurat' ? 'bg-alarm/15 text-alarm' : ($a->level === 'penting' ? 'bg-hivis/15 text-hivis' : 'bg-neon/10 text-neon') }}">
                    <i class="{{ \App\Models\Announcement::LEVELS[$a->level]['icon'] }} text-xl"></i>
                </span>
                <span class="min-w-0 grow">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ $a->title }}</span>
                        @if ($a->is_pinned) <i class="ri-pushpin-2-fill text-neon" title="Disematkan"></i> @endif
                        <span class="chip-{{ $a->status() }}">{{ ucfirst($a->status()) }}</span>
                    </span>
                    <span class="mt-0.5 line-clamp-1 block text-sm text-mist">{{ \Illuminate\Support\Str::limit(strip_tags($a->body), 140) }}</span>
                    <span class="mt-1 block text-xs text-mist">{{ $a->creator?->displayName() ?? '–' }} · {{ $a->publishedAt()->locale('id')->translatedFormat('j M Y, H:i') }}{{ $a->ends_at ? ' – '.$a->ends_at->locale('id')->translatedFormat('j M Y') : '' }}</span>
                </span>
                <i class="ri-arrow-right-s-line mt-2 text-xl text-mist"></i>
            </a>
        @empty
            <div class="px-6 py-14 text-center">
                <i class="ri-megaphone-line text-4xl text-mist/50"></i>
                <p class="mt-3 font-semibold">Belum ada pengumuman</p>
                <p class="mt-1 text-sm text-mist">Pengumuman muncul di menu Info dan lonceng beranda. Tingkat Penting/Darurat tampil sebagai pita di beranda.</p>
            </div>
        @endforelse
    </div>
</x-layouts.admin>
