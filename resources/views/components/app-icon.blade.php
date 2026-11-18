{{-- Ikon aplikasi: variant orb (bulat besar), chip (kotak kecil), atau tile (daftar). --}}
@props(['app', 'variant' => 'orb', 'size' => null])
@php
    $px = $size ?? match ($variant) { 'orb' => 58, 'chip' => 46, default => 48 };
    $status = $app->health_check ? $app->health_status : 'unknown';
@endphp
<span {{ $attributes->merge(['class' => ($variant === 'orb' ? 'app-orb' : 'app-chip').' shrink-0']) }}
      style="--app: {{ $app->color }}; width: {{ $px }}px; height: {{ $px }}px">
    @if ($app->icon_type === 'image')
        <img src="{{ $app->iconUrl() }}" alt="" class="h-[62%] w-[62%] object-contain" loading="lazy">
    @else
        <i class="{{ $app->icon }}" style="font-size: {{ round($px * ($variant === 'orb' ? .46 : .5)) }}px" aria-hidden="true"></i>
    @endif
    @if ($status !== 'unknown')
        <span class="status-dot {{ $variant === 'orb' ? 'right-0.5 bottom-0.5' : '-right-0.5 -bottom-0.5' }}" data-s="{{ $status }}" title="{{ $status === 'up' ? 'Online' : 'Tidak merespons saat terakhir dicek' }}"></span>
    @endif
</span>
