{{-- Kepala kartu sesi: jenis, judul, waktu, lokasi --}}
<div class="flex items-start gap-3">
    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" style="background: color-mix(in oklab, {{ $session->type->color }} 16%, transparent); color: {{ $session->type->color }}">
        <i class="{{ $session->type->icon }} text-2xl"></i>
    </span>
    <div class="min-w-0 grow">
        <p class="font-display text-[1.15rem] leading-tight font-semibold">{{ $session->type->name }}</p>
        @if ($session->title) <p class="mt-0.5 text-[15px] leading-snug text-fog">{{ $session->title }}</p> @endif
        <p class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[13px] text-mist">
            <span><i class="ri-time-line"></i> {{ $session->isRoutine() ? $session->opens_at->format('H:i').'–'.$session->closes_at->format('H:i') : $session->starts_at->format('H:i') }}{{ $session->shift && ! str_contains((string) $session->title, 'Shift') ? ' · Shift '.$session->shift : '' }}</span>
            @if ($place = $session->locationLabel()) <span><i class="ri-map-pin-2-line"></i> {{ $place }}</span> @endif
            @if ($session->isRoutine()) <span><i class="ri-repeat-line"></i> Rutin</span> @endif
        </p>
    </div>
</div>
