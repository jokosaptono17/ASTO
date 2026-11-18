{{-- Mentari: maskot robot mekanik (SVG animasi). Sama dengan Mentari di MekanikPintar; mata neon mengikuti pointer (resources/js/mentari.js). --}}
@props(['size' => 220, 'label' => 'Mentari, asisten Plant ASTO', 'eye' => '#3dff8e'])
@php $id = 'm'.substr(md5(uniqid('', true)), 0, 6); @endphp
<svg {{ $attributes->merge(['class' => 'mentari']) }} width="{{ $size }}" height="{{ round($size * 260 / 220) }}" viewBox="0 0 220 260" role="img" aria-label="{{ $label }}" data-mentari>
    <defs>
        <linearGradient id="{{ $id }}-helm" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fde68a"/><stop offset=".55" stop-color="#fbbf24"/><stop offset="1" stop-color="#d97706"/></linearGradient>
        <linearGradient id="{{ $id }}-shell" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f1f5f9"/><stop offset=".6" stop-color="#cbd5e1"/><stop offset="1" stop-color="#94a3b8"/></linearGradient>
        <linearGradient id="{{ $id }}-suit" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1e3a8a"/><stop offset="1" stop-color="#0f172a"/></linearGradient>
        <linearGradient id="{{ $id }}-visor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0b1220"/><stop offset="1" stop-color="#111c33"/></linearGradient>
        <linearGradient id="{{ $id }}-flame" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{{ $eye }}"/><stop offset=".45" stop-color="#22d3ee" stop-opacity=".8"/><stop offset="1" stop-color="#22d3ee" stop-opacity="0"/></linearGradient>
        <radialGradient id="{{ $id }}-glow"><stop offset="0" stop-color="#fde68a" stop-opacity=".9"/><stop offset="1" stop-color="#fbbf24" stop-opacity="0"/></radialGradient>
        <filter id="{{ $id }}-neon" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="2.2" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
    </defs>

    <ellipse class="m-shadow" cx="110" cy="250" rx="44" ry="6" fill="#000" opacity=".4"/>

    {{-- Partikel orbit --}}
    <g class="m-orbit" opacity=".9">
        <circle cx="110" cy="22" r="2.4" fill="#fbbf24"/>
        <circle cx="200" cy="140" r="1.8" fill="{{ $eye }}"/>
        <circle cx="22" cy="170" r="2" fill="#fde68a"/>
    </g>

    <g class="m-float">
        {{-- Pendorong + api --}}
        <path class="m-flame" d="M97 220 Q110 258 123 220 Z" fill="url(#{{ $id }}-flame)"/>
        <rect x="91" y="208" width="38" height="14" rx="7" fill="#475569"/>
        <rect x="97" y="217" width="26" height="4" rx="2" fill="{{ $eye }}" opacity=".9" filter="url(#{{ $id }}-neon)"/>

        {{-- Lengan kanan + tablet --}}
        <g class="m-arm-r">
            <path d="M150 156 L176 180" stroke="url(#{{ $id }}-shell)" stroke-width="13" stroke-linecap="round"/>
            <rect x="168" y="166" width="28" height="36" rx="5" fill="#0f172a" stroke="#22d3ee" stroke-width="1.5"/>
            <g class="m-screen" stroke="{{ $eye }}" stroke-width="2" stroke-linecap="round" opacity=".85">
                <path d="M174 176 h16"/><path d="M174 183 h10"/><path d="M174 190 h13"/>
            </g>
            <circle cx="176" cy="196" r="7" fill="#fbbf24"/>
        </g>

        {{-- Badan: coverall + rompi reflektor --}}
        <rect x="64" y="138" width="92" height="76" rx="26" fill="url(#{{ $id }}-suit)"/>
        <path d="M70 152 Q72 141 88 139 L98 139 L98 212 L80 212 Q70 206 68 194 Z" fill="#f59e0b"/>
        <path d="M150 152 Q148 141 132 139 L122 139 L122 212 L140 212 Q150 206 152 194 Z" fill="#f59e0b"/>
        <rect x="66" y="180" width="32" height="7" fill="#e2e8f0" opacity=".9"/>
        <rect x="122" y="180" width="32" height="7" fill="#e2e8f0" opacity=".9"/>
        <circle cx="110" cy="164" r="12" fill="#0b1220" stroke="#fbbf24" stroke-width="1.5"/>
        <g class="m-badge" stroke="#fbbf24" stroke-width="1.6" stroke-linecap="round">
            <circle cx="110" cy="164" r="3.6" fill="#fbbf24" stroke="none"/>
            <path d="M110 155.5v2.6M110 169.9v2.6M101.5 164h2.6M115.9 164h2.6M104 158l1.8 1.8M114.2 168.2l1.8 1.8M104 170l1.8-1.8M114.2 159.8l1.8-1.8"/>
        </g>
        <text x="110" y="203" text-anchor="middle" font-family="ui-monospace,monospace" font-size="7.5" font-weight="700" letter-spacing="1.6" fill="#fde68a">MENTARI</text>

        {{-- Lengan kiri melambai + kunci pas --}}
        <g class="m-arm-wave">
            <path d="M70 156 L40 122" stroke="url(#{{ $id }}-shell)" stroke-width="13" stroke-linecap="round"/>
            <g transform="rotate(-18 36 116)">
                <rect x="32" y="80" width="7" height="36" rx="3.5" fill="#cbd5e1"/>
                <path d="M27 72 a9 9 0 1 0 17 0 l-4.5 6 h-8 z" fill="#cbd5e1"/>
            </g>
            <circle cx="37" cy="118" r="9" fill="#fbbf24"/>
        </g>

        {{-- Leher & kepala --}}
        <rect x="99" y="128" width="22" height="14" rx="4" fill="#64748b"/>
        <g class="m-head">
            <circle cx="50" cy="96" r="10" fill="#f59e0b"/><circle cx="50" cy="96" r="4.5" fill="#0b1220"/>
            <circle cx="170" cy="96" r="10" fill="#f59e0b"/><circle cx="170" cy="96" r="4.5" fill="#0b1220"/>
            <rect x="52" y="58" width="116" height="76" rx="30" fill="url(#{{ $id }}-shell)"/>
            <rect x="64" y="74" width="92" height="48" rx="20" fill="url(#{{ $id }}-visor)" stroke="#1e293b" stroke-width="2"/>
            <path d="M72 80 Q90 75 104 78" stroke="#fff" stroke-opacity=".12" stroke-width="3" fill="none" stroke-linecap="round"/>
            <g data-eyes filter="url(#{{ $id }}-neon)">
                <rect class="m-eye" x="85" y="86" width="14" height="20" rx="7" fill="{{ $eye }}"/>
                <rect class="m-eye" x="121" y="86" width="14" height="20" rx="7" fill="{{ $eye }}"/>
            </g>
            <circle cx="78" cy="110" r="3.5" fill="#fb923c" opacity=".55"/>
            <circle cx="142" cy="110" r="3.5" fill="#fb923c" opacity=".55"/>
            <path class="m-mouth" d="M101 112 Q110 118 119 112" stroke="{{ $eye }}" stroke-width="2.6" fill="none" stroke-linecap="round"/>

            {{-- Helm safety + lampu matahari --}}
            <path d="M48 72 C48 22 172 22 172 72 Z" fill="url(#{{ $id }}-helm)"/>
            <path d="M104 39 C104 36 116 36 116 39 L116 70 L104 70 Z" fill="#fde68a" opacity=".5"/>
            <rect x="40" y="66" width="140" height="11" rx="5.5" fill="#d97706"/>
            <circle class="m-lamp-glow" cx="110" cy="46" r="20" fill="url(#{{ $id }}-glow)"/>
            <g class="m-rays" stroke="#b45309" stroke-width="2" stroke-linecap="round">
                <path d="M110 31v-4M110 61v4M95 46h-4M125 46h4M99.4 35.4l-2.8-2.8M120.6 56.6l2.8 2.8M99.4 56.6l-2.8 2.8M120.6 35.4l2.8-2.8"/>
            </g>
            <circle cx="110" cy="46" r="8.5" fill="#fffbeb" stroke="#b45309" stroke-width="2"/>
        </g>
    </g>
</svg>
