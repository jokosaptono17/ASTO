<x-layouts.admin title="Mentari">
    <div class="grid gap-5 xl:grid-cols-[1fr_380px]">
        <div class="min-w-0 space-y-5">
            <section class="a-card flex flex-wrap items-center gap-5 p-5">
                <x-mentari :size="96" />
                <div class="min-w-0 grow">
                    <h2 class="font-display text-lg font-semibold">Otak Mentari</h2>
                    @if ($aiConnected)
                        <p class="mt-1 text-sm text-fog"><span class="chip-tayang">AI tersambung</span> Pertanyaan diteruskan ke layanan AI (MENTARI_ENDPOINT). Jawaban di bawah dipakai bila AI tidak merespons.</p>
                    @else
                        <p class="mt-1 text-sm text-fog"><span class="chip-berakhir">Mode bawaan</span> Mentari mencari aplikasi, pengumuman, kampanye, dan jawaban di bawah. Sambungkan AI lewat <code class="text-neon-soft">MENTARI_ENDPOINT</code> di .env — kontraknya sama dengan Mentari di MekanikPintar.</p>
                    @endif
                </div>
            </section>

            <section class="a-card overflow-hidden" x-data="{ edit: null }">
                <header class="border-b border-white/[0.06] px-5 py-3">
                    <h2 class="font-display font-semibold">Jawaban siap pakai</h2>
                    <p class="text-xs text-mist">Bila pertanyaan mengandung salah satu kata kunci, Mentari memakai jawaban ini.</p>
                </header>
                @foreach ($faqs as $faq)
                    <div class="border-b border-white/[0.05] px-5 py-4 last:border-0">
                        <div x-show="edit !== {{ $faq->id }}" class="flex gap-3">
                            <div class="min-w-0 grow">
                                <p class="font-semibold {{ $faq->is_active ? '' : 'text-mist line-through' }}">{{ $faq->question }}</p>
                                <p class="mt-1 text-sm text-fog">{{ \Illuminate\Support\Str::limit($faq->answer, 180) }}</p>
                                <p class="mt-1.5 flex flex-wrap gap-1">@foreach ($faq->keywordList() as $k)<span class="chip bg-white/[0.06] text-mist">{{ $k }}</span>@endforeach</p>
                            </div>
                            <span class="shrink-0 text-xs text-mist">{{ $faq->hits }}× dipakai</span>
                            <button type="button" @click="edit = {{ $faq->id }}" class="icon-btn shrink-0" aria-label="Ubah"><i class="ri-pencil-line text-lg"></i></button>
                        </div>
                        <div x-show="edit === {{ $faq->id }}" x-cloak>
                            @include('admin.mentari._form', ['faq' => $faq])
                        </div>
                    </div>
                @endforeach
                <div class="border-t border-white/[0.06] bg-white/[0.02] px-5 py-4">
                    <p class="mb-3 text-sm font-semibold">Tambah jawaban</p>
                    @include('admin.mentari._form', ['faq' => new \App\Models\MentariFaq(['is_active' => true])])
                </div>
            </section>
        </div>

        {{-- Uji coba --}}
        <aside class="a-card self-start p-5" x-data="{ q: '', answer: null, busy: false,
                async ask() { if (!this.q.trim()) return; this.busy = true; try { this.answer = await postJson(@js(route('admin.mentari.test')), { message: this.q }); } finally { this.busy = false; } } }">
            <h2 class="font-display font-semibold">Uji jawaban</h2>
            <p class="mt-1 text-xs text-mist">Coba pertanyaan seperti yang diketik mekanik.</p>
            <form @submit.prevent="ask()" class="mt-3 flex gap-2">
                <input x-model="q" class="field !py-2.5" placeholder="Contoh: lupa pin">
                <button class="btn-neon !min-h-11 shrink-0 !px-4" :disabled="busy" aria-label="Tanya"><i class="ri-send-plane-2-fill"></i></button>
            </form>
            <template x-if="answer">
                <div class="mt-4 rounded-2xl border border-white/[0.07] bg-white/[0.03] p-3.5 text-sm">
                    <p class="whitespace-pre-line" x-text="answer.reply"></p>
                    <template x-for="a in answer.actions"><p class="mt-2 text-xs text-neon-soft"><i class="ri-link"></i> <span x-text="a.label"></span></p></template>
                </div>
            </template>
        </aside>
    </div>
</x-layouts.admin>
