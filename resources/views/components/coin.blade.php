{{-- Koin Poin HEBAT --}}
@props(['size' => 24])
@php $id = 'c'.substr(md5(uniqid('', true)), 0, 6); @endphp
<svg {{ $attributes->merge(['class' => 'shrink-0']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" aria-hidden="true">
    <defs>
        <linearGradient id="{{ $id }}a" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffe08a"/><stop offset=".55" stop-color="#ffc23d"/><stop offset="1" stop-color="#d98a0b"/></linearGradient>
        <linearGradient id="{{ $id }}b" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#b86e00"/><stop offset="1" stop-color="#ffd76a"/></linearGradient>
    </defs>
    <circle cx="24" cy="24" r="22" fill="url(#{{ $id }}b)"/>
    <circle cx="24" cy="24" r="18.5" fill="url(#{{ $id }}a)"/>
    <circle cx="24" cy="24" r="18.5" fill="none" stroke="#fff3c4" stroke-opacity=".55" stroke-width="1"/>
    <path d="M17 14h4.4v7.8h5.2V14H31v20h-4.4v-8.1h-5.2V34H17z" fill="#5a3600" fill-opacity=".85"/>
    <path d="M12 17a14 14 0 0 1 9-7" stroke="#fffbe6" stroke-opacity=".8" stroke-width="2" stroke-linecap="round" fill="none"/>
</svg>
