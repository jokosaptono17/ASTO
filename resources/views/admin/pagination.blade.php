@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 text-sm" aria-label="Halaman">
        <span class="text-mist">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}</span>
        <span class="flex gap-2">
            @if ($paginator->onFirstPage()) <span class="btn-ghost !min-h-9 opacity-40">Sebelumnya</span>
            @else <a href="{{ $paginator->previousPageUrl() }}" class="btn-ghost !min-h-9">Sebelumnya</a> @endif
            @if ($paginator->hasMorePages()) <a href="{{ $paginator->nextPageUrl() }}" class="btn-ghost !min-h-9">Berikutnya</a>
            @else <span class="btn-ghost !min-h-9 opacity-40">Berikutnya</span> @endif
        </span>
    </nav>
@endif
