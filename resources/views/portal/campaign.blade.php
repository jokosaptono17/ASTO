<x-layouts.portal :title="$campaign->title">
    @include('portal._topbar', ['heading' => $campaign->title, 'back' => route('home')])
    <article class="px-4">
        <img src="{{ $campaign->imageUrl() }}" alt="{{ $campaign->title }}" class="w-full rounded-[22px] border border-white/[0.08]">
        @if ($campaign->subtitle) <p class="mt-4 px-1 text-[16px] leading-relaxed text-fog">{{ $campaign->subtitle }}</p> @endif
        @if ($campaign->ends_at) <p class="mt-2 px-1 text-sm text-mist">Berlaku sampai {{ $campaign->ends_at->locale('id')->translatedFormat('j F Y') }}</p> @endif
    </article>
</x-layouts.portal>
