{{-- Pop-up kampanye saat aplikasi dibuka --}}
@if ($popup)
    @php
        $canDismiss = ! $popup->popup_ack && in_array($popup->popup_frequency, ['daily', 'session'], true);
        $external = $popup->link_url && ! str_starts_with($popup->link_url, '/');
    @endphp
    <div x-data="campaignPopup(@js([
            'id' => $popup->id,
            'seconds' => (int) $popup->popup_seconds,
            'ack' => $popup->popup_ack,
            'frequency' => $popup->popup_frequency,
            'preview' => $popupPreview,
            'seenUrl' => route('popup.seen', $popup),
            'closeUrl' => route('popup.close', $popup),
        ]))"
         x-show="open" x-cloak @keydown.escape.window="tryClose()"
         class="fixed inset-0 z-[70] flex items-center justify-center p-6" role="dialog" aria-modal="true" aria-labelledby="popup-title">
        <div x-show="open" x-transition.opacity.duration.300ms @click="tryClose()" class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>

        <div x-show="open" x-transition:enter="transition duration-500 ease-[cubic-bezier(.2,1.3,.4,1)]" x-transition:enter-start="opacity-0 scale-75 translate-y-6" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="opacity-0 scale-90"
             class="relative w-[min(86vw,380px,calc(56svh*.75))] overflow-hidden rounded-[28px] border {{ $popup->popup_ack ? 'border-hivis/50 shadow-[0_0_70px_-12px_rgba(255,194,61,.55)]' : 'border-neon/30 shadow-[0_0_70px_-14px_rgba(61,255,142,.5)]' }} bg-bench lg:w-[min(420px,calc(60svh*.75))]">
            {{-- Gambar --}}
            <div class="relative aspect-[3/4] bg-ridge">
                @if ($popup->link_url)
                    <a href="{{ route('campaigns.open', $popup) }}" @click="close('close', false)" @if ($external) target="_blank" rel="noopener" @endif aria-label="{{ $popup->title }}">
                        <img src="{{ $popup->imageUrl() }}" alt="{{ $popup->title }}" class="h-full w-full object-cover">
                    </a>
                @else
                    <img src="{{ $popup->imageUrl() }}" alt="{{ $popup->title }}" class="h-full w-full object-cover">
                @endif

                @if ($popupPreview)
                    <span class="absolute bottom-3 left-3 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold text-fog">Pratinjau admin</span>
                @endif

                {{-- Tombol tutup dengan hitung mundur --}}
                <button type="button" x-show="!cfg.ack" @click="tryClose()" :disabled="left > 0" :aria-label="left > 0 ? `Bisa ditutup dalam ${left} detik` : 'Tutup'"
                        class="absolute top-3 right-3 grid h-10 w-10 place-items-center rounded-full bg-black/60 text-white backdrop-blur-md transition disabled:cursor-wait">
                    <svg x-show="left > 0" viewBox="0 0 40 40" class="absolute inset-0 h-10 w-10 -rotate-90"><circle cx="20" cy="20" r="17" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="3"/><circle cx="20" cy="20" r="17" fill="none" stroke="#3dff8e" stroke-width="3" stroke-linecap="round" stroke-dasharray="106.8" :stroke-dashoffset="106.8 * (left / Math.max(1, cfg.seconds))" class="transition-[stroke-dashoffset] duration-1000 ease-linear"/></svg>
                    <span x-show="left > 0" class="font-display text-sm font-bold" x-text="left"></span>
                    <i x-show="left <= 0" class="ri-close-line text-2xl"></i>
                </button>
            </div>

            {{-- Isi & tombol --}}
            <div class="space-y-3 p-4">
                <div>
                    @if ($popup->popup_ack)
                        <span class="mb-1.5 inline-flex items-center gap-1.5 rounded-full bg-hivis/15 px-2.5 py-0.5 text-xs font-bold text-hivis"><i class="ri-error-warning-fill"></i> Wajib dibaca</span>
                    @endif
                    <p id="popup-title" class="font-display text-lg leading-snug font-semibold">{{ $popup->title }}</p>
                    @if ($popup->subtitle) <p class="mt-0.5 text-sm leading-relaxed text-fog">{{ $popup->subtitle }}</p> @endif
                </div>

                @if ($popup->popup_ack)
                    <button type="button" @click="close('ack')" :disabled="left > 0" class="btn-neon w-full !min-h-12 disabled:opacity-50">
                        <i class="ri-checkbox-circle-line text-xl"></i>
                        <span x-text="left > 0 ? `Baca dulu… ${left}` : 'Saya sudah membaca'">Saya sudah membaca</span>
                    </button>
                    @if ($popup->link_url)
                        <a href="{{ route('campaigns.open', $popup) }}" @if ($external) target="_blank" rel="noopener" @endif class="block text-center text-sm font-semibold text-neon">{{ $popup->link_label ?: 'Lihat detail' }} <i class="ri-arrow-right-line"></i></a>
                    @endif
                @elseif ($popup->link_url)
                    <a href="{{ route('campaigns.open', $popup) }}" @click="close('close', false)" @if ($external) target="_blank" rel="noopener" @endif class="btn-neon w-full !min-h-12">{{ $popup->link_label ?: 'Lihat detail' }} <i class="ri-arrow-right-line text-lg"></i></a>
                @endif

                @unless ($popup->popup_ack)
                    <div class="flex items-center justify-between text-sm">
                        @if ($canDismiss)
                            <button type="button" @click="close('dismiss')" :disabled="left > 0" class="text-mist hover:text-fog disabled:opacity-40">Jangan tampilkan lagi</button>
                        @else
                            <span></span>
                        @endif
                        <button type="button" @click="tryClose()" :disabled="left > 0" class="font-semibold text-fog hover:text-ink disabled:opacity-40" x-text="left > 0 ? `Tutup (${left})` : 'Tutup'">Tutup</button>
                    </div>
                @endunless
            </div>
        </div>
    </div>
@endif
