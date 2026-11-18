{{-- Peluncur (opsional) + panel chat Mentari. Buka dari mana saja: window.dispatchEvent(new CustomEvent('mentari-open')) --}}
@props(['page' => 'home', 'launcher' => false, 'tips' => []])
@php
    $viewer = auth()->user();
    $config = [
        'url' => route('mentari.chat'),
        'whatsapp' => \App\Models\Setting::whatsappNumber(),
        'page' => $page,
        'storeKey' => 'mentari:'.($viewer?->id ?? 'guest'),
        'chips' => \App\Services\Mentari\MentariGuide::chipsFor($viewer),
        'greeting' => $viewer
            ? 'Halo, '.$viewer->firstName().'! Saya Mentari 👋 Mau buka aplikasi apa, atau ada yang bisa saya bantu?'
            : \App\Models\Setting::get('mentari_greeting'),
        'tips' => $tips,
        'placeholder' => $viewer ? 'Tanya Mentari atau ketik NRP…' : 'Tanya Mentari…',
    ];
@endphp
<div x-data="mentariChat(@js($config))" class="contents">
    @if ($launcher)
        <div x-show="!open" x-transition.opacity class="fixed right-4 bottom-[max(1rem,env(safe-area-inset-bottom))] z-40 flex items-end gap-2">
            <div x-show="bubble" x-cloak x-transition.origin.bottom.right @click="show()"
                 class="mb-4 max-w-[230px] cursor-pointer rounded-2xl rounded-br-md border border-neon/25 bg-bench/95 px-3.5 py-2.5 text-[13px] leading-snug text-ink shadow-xl shadow-black/40 backdrop-blur-md" x-text="bubble"></div>
            <button type="button" @click="show()" aria-label="Chat dengan Mentari"
                    class="relative grid h-[68px] w-[68px] place-items-center rounded-full border border-neon/40 bg-gradient-to-b from-ridge to-pit shadow-[0_0_34px_rgba(61,255,142,.3)] transition hover:scale-105 active:scale-95">
                <x-mentari :size="54" label="Mentari" class="-mt-1.5" />
            </button>
        </div>
    @else
        <template x-teleport="body">
            <div x-show="bubble && !open" x-cloak x-transition.origin.bottom @click="show()"
                 class="fixed bottom-[calc(76px+env(safe-area-inset-bottom))] left-1/2 z-40 w-max max-w-[260px] -translate-x-1/2 cursor-pointer rounded-2xl border border-neon/25 bg-bench/95 px-3.5 py-2.5 text-center text-[13px] leading-snug text-ink shadow-xl shadow-black/50 backdrop-blur-md" x-text="bubble"></div>
        </template>
    @endif

    <template x-teleport="body">
        <div x-show="open" x-cloak @keydown.escape.window="hide()" class="fixed inset-0 z-50 sm:flex sm:items-end sm:justify-center sm:pb-6">
            <div x-show="open" x-transition.opacity @click="hide()" class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
            <section x-show="open"
                     x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-10 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                     x-transition:leave="transition duration-150" x-transition:leave-end="translate-y-8 opacity-0"
                     role="dialog" aria-label="Chat dengan Mentari"
                     class="absolute inset-x-0 bottom-0 flex h-[86svh] max-h-[720px] flex-col overflow-hidden rounded-t-[30px] border border-white/10 bg-bench/95 shadow-2xl shadow-black/60 backdrop-blur-xl sm:relative sm:h-[640px] sm:w-[420px] sm:rounded-[30px]">
                <x-contour class="absolute -top-6 right-0 h-48 w-full opacity-40" />
                <header class="relative flex items-center gap-3 border-b border-white/[0.06] px-4 pt-4 pb-3">
                    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-b from-ridge to-pit ring-1 ring-neon/30"><x-mentari :size="46" label="Mentari" /></span>
                    <div class="min-w-0 grow">
                        <div class="font-display text-lg font-semibold">Mentari</div>
                        <div class="flex items-center gap-1.5 text-xs text-mist">
                            <span class="h-1.5 w-1.5 rounded-full bg-neon shadow-[0_0_8px_var(--color-neon)]"></span>
                            <span>{{ config('services.mentari.endpoint') ? 'Asisten AI Plant ASTO' : 'Asisten Plant ASTO' }}</span>
                        </div>
                    </div>
                    <button type="button" @click="clear()" x-show="messages.length > 1" class="icon-btn" aria-label="Mulai percakapan baru" title="Mulai percakapan baru"><i class="ri-refresh-line text-xl"></i></button>
                    <button type="button" @click="hide()" class="icon-btn" aria-label="Tutup"><i class="ri-close-line text-2xl"></i></button>
                </header>

                <div x-ref="log" class="relative grow space-y-3 overflow-y-auto overscroll-contain px-4 py-4" aria-live="polite">
                    <template x-for="(m, i) in messages" :key="i">
                        <div :class="m.role === 'user' ? 'flex justify-end' : 'flex'">
                            <div :class="m.role === 'user'
                                    ? 'max-w-[82%] rounded-2xl rounded-br-md bg-neon px-3.5 py-2.5 text-[15px] text-[#04170d]'
                                    : ((m.cards || []).length ? 'w-full' : 'max-w-[88%]') + ' rounded-2xl rounded-bl-md border border-white/[0.07] bg-white/[0.04] px-3.5 py-2.5 text-[15px] leading-relaxed text-ink'">
                                <p class="whitespace-pre-line" x-text="m.text"></p>

                                {{-- Kartu: orang, angka, daftar --}}
                                <template x-for="(card, ci) in (m.cards || [])" :key="ci">
                                    <div class="mt-2.5">
                                        <template x-if="card.type === 'person'">
                                            <div class="overflow-hidden rounded-2xl border border-neon/20 bg-gradient-to-br from-ridge to-pit">
                                                <div class="flex items-center gap-3 p-3">
                                                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-neon font-display text-sm font-bold text-[#04170d]" x-text="card.initials"></span>
                                                    <div class="min-w-0 grow leading-tight">
                                                        <p class="truncate font-semibold" x-text="card.name"></p>
                                                        <p class="font-display text-[13px] text-neon-soft">
                                                            <span x-text="card.nrp"></span>
                                                            <span x-show="card.role" class="ml-1 rounded-full bg-hivis/15 px-1.5 text-[11px] text-hivis" x-text="card.role"></span>
                                                        </p>
                                                    </div>
                                                    <span x-show="card.status" class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                          :class="{ 'bg-neon/15 text-neon': card.status === 'aktif', 'bg-white/10 text-mist': card.status === 'belum', 'bg-alarm/15 text-red-300': card.status === 'nonaktif' }"
                                                          x-text="{ aktif: 'Aktif', belum: 'Belum aktivasi', nonaktif: 'Nonaktif' }[card.status]"></span>
                                                </div>
                                                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 border-t border-white/[0.06] px-3 py-2.5 text-[13px]">
                                                    <template x-for="f in [...(card.fields || []), ...(card.admin || [])]" :key="f.label">
                                                        <div class="min-w-0"><dt class="text-[11px] text-mist" x-text="f.label"></dt><dd class="truncate font-medium" x-text="f.value"></dd></div>
                                                    </template>
                                                </dl>
                                                <template x-if="card.lms">
                                                    <div class="grid grid-cols-3 border-t border-white/[0.06] text-center">
                                                        <div class="py-2"><p class="font-display text-lg font-bold text-neon" x-text="card.lms.points"></p><p class="text-[11px] text-mist">Poin</p></div>
                                                        <div class="border-x border-white/[0.06] py-2"><p class="font-display text-lg font-bold" x-text="card.lms.trainings"></p><p class="text-[11px] text-mist">Training</p></div>
                                                        <div class="py-2"><p class="font-display text-lg font-bold" x-text="card.lms.certificates"></p><p class="text-[11px] text-mist">Sertifikat</p></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="card.type === 'stats'">
                                            <div class="rounded-2xl border border-white/[0.07] bg-pit/60 p-3">
                                                <p x-show="card.title" class="mb-2 text-[12px] font-semibold text-fog" x-text="card.title"></p>
                                                <div class="grid gap-2" :class="card.items.length > 3 ? 'grid-cols-2' : 'grid-cols-3'">
                                                    <template x-for="it in card.items" :key="it.label">
                                                        <div class="rounded-xl bg-white/[0.04] px-2 py-2 text-center">
                                                            <p class="font-display text-xl font-bold text-neon" x-text="Number(it.value).toLocaleString('id-ID')"></p>
                                                            <p class="text-[11px] leading-tight text-mist" x-text="it.label"></p>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="card.type === 'list'">
                                            <div class="overflow-hidden rounded-2xl border border-white/[0.07] bg-pit/60">
                                                <p x-show="card.title" class="border-b border-white/[0.06] px-3 py-2 text-[12px] font-semibold text-fog" x-text="card.title"></p>
                                                <template x-for="(it, ii) in card.items" :key="ii">
                                                    <button type="button" @click="it.ask && send(it.ask)" :disabled="!it.ask"
                                                            class="flex w-full items-center gap-2 border-b border-white/[0.04] px-3 py-2 text-left last:border-0 enabled:hover:bg-white/[0.04]">
                                                        <span class="min-w-0 grow">
                                                            <span class="block truncate text-[14px] font-medium" :class="{ 'text-red-300': it.tone === 'alarm', 'text-hivis': it.tone === 'warn' }" x-text="it.title"></span>
                                                            <span x-show="it.subtitle" class="block truncate text-[12px] text-mist" x-text="it.subtitle"></span>
                                                        </span>
                                                        <span x-show="it.meta" class="shrink-0 font-display text-[12px] text-fog" x-text="it.meta"></span>
                                                        <i x-show="it.ask" class="ri-arrow-right-s-line shrink-0 text-mist"></i>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="(m.actions || []).length">
                                    <div class="mt-2.5 grid gap-1.5">
                                        <template x-for="a in m.actions" :key="(a.href || a.ask) + a.label">
                                            <div>
                                                <template x-if="a.ask">
                                                    <button type="button" @click="send(a.ask)" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-white/[0.06] px-3 py-2 text-center text-sm font-semibold text-ink ring-1 ring-white/10 hover:bg-white/10">
                                                        <i class="ri-question-answer-line text-base text-neon"></i><span x-text="a.label"></span>
                                                    </button>
                                                </template>
                                                <template x-if="!a.ask">
                                                    <a :href="a.href" :target="a.external ? '_blank' : null" :rel="a.external ? 'noopener' : null"
                                                       :class="a.external ? 'bg-neon/15 text-neon-soft ring-1 ring-neon/30' : 'bg-white/[0.06] text-ink ring-1 ring-white/10 hover:bg-white/10'"
                                                       class="flex min-h-11 items-center justify-center gap-2 rounded-xl px-3 py-2 text-center text-sm font-semibold">
                                                        <i :class="a.external ? 'ri-whatsapp-line' : 'ri-external-link-line'" class="text-base"></i>
                                                        <span x-text="a.label"></span>
                                                    </a>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div x-show="typing" x-cloak class="flex w-16 items-center justify-center gap-1 rounded-2xl rounded-bl-md border border-white/[0.07] bg-white/[0.04] py-3.5">
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-neon"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-neon [animation-delay:.15s]"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-neon [animation-delay:.3s]"></span>
                    </div>
                </div>

                <div class="scroller pb-2">
                    <template x-for="chip in chips" :key="chip">
                        <button type="button" @click="send(chip)" class="shrink-0 rounded-full border border-neon/25 bg-neon/[0.06] px-3.5 py-2 text-[13px] text-neon-soft hover:bg-neon/15" x-text="chip"></button>
                    </template>
                </div>
                <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-white/[0.06] p-3 pb-[max(.75rem,env(safe-area-inset-bottom))]">
                    <input x-ref="input" x-model="input" type="text" maxlength="1000" :placeholder="placeholder" autocomplete="off" enterkeyhint="send"
                           class="h-12 grow rounded-2xl border-0 bg-white/[0.05] px-4 text-[15px] text-ink ring-1 ring-white/10 placeholder:text-mist/60 focus:ring-2 focus:ring-neon focus:outline-none">
                    <button type="submit" :disabled="!input.trim() || typing" class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-neon text-[#04170d] transition disabled:opacity-40" aria-label="Kirim">
                        <i class="ri-send-plane-2-fill text-xl"></i>
                    </button>
                </form>
            </section>
        </div>
    </template>
</div>
