{{-- Baris sesi di daftar --}}
@php $st = $s->status(); @endphp
<a href="{{ route('admin.attendance.show', $s) }}" class="flex items-center gap-4 border-b border-white/[0.05] px-4 py-3.5 last:border-0 hover:bg-white/[0.02]">
    <div class="w-14 shrink-0 text-center">
        <p class="font-display text-xl leading-none font-bold">{{ $s->starts_at->format('H:i') }}</p>
        <p class="mt-1 text-[11px] text-mist">{{ $s->starts_at->locale('id')->translatedFormat('j M') }}</p>
    </div>
    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" style="background: color-mix(in oklab, {{ $s->type->color }} 16%, transparent); color: {{ $s->type->color }}"><i class="{{ $s->type->icon }} text-xl"></i></span>
    <div class="min-w-0 grow">
        <p class="truncate font-semibold">{{ $s->type->name }}{{ $s->title ? ' — '.$s->title : '' }}</p>
        <p class="truncate text-xs text-mist">{{ $s->location ?: 'Lokasi belum diisi' }}{{ $s->shift ? ' · Shift '.$s->shift : '' }} · Host {{ $s->host?->displayName() ?? '–' }}{{ $s->target_departments ? ' · '.implode(', ', $s->target_departments) : '' }}</p>
    </div>
    <div class="shrink-0 text-right">
        <p class="font-display text-lg font-bold">{{ $s->records_count }}</p>
        <p class="text-[11px] text-mist">hadir</p>
    </div>
    <span class="{{ ['dibuka' => 'chip-tayang', 'terjadwal' => 'chip-terjadwal', 'selesai' => 'chip-berakhir'][$st] }} hidden shrink-0 sm:inline-flex">
        @if ($st === 'dibuka') <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-neon"></span> @endif{{ $s->statusLabel() }}
    </span>
</a>
