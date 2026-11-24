<x-layouts.admin :title="$question->exists ? 'Ubah soal' : 'Soal baru'" :subtitle="$set->name">
    <x-slot:actions>
        <a href="{{ route('admin.exams.bank.set', [$set, 'page' => request('page')]) }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-arrow-left-line text-lg"></i> {{ $set->name }}</a>
    </x-slot:actions>
    @php
        $opts = array_values(old('options') ? array_values(array_filter(old('options'), fn ($v) => $v !== null)) : array_values($question->options ?? []));
        while (count($opts) < 2) { $opts[] = ''; }
    @endphp

    <form method="POST" enctype="multipart/form-data" action="{{ $question->exists ? route('admin.exams.bank.update', $question) : route('admin.exams.bank.question.store', $set) }}"
          x-data="questionEditor(@js(['options' => $opts, 'answer' => old('answer', $question->answer ?? 'A'), 'image' => $question->imageUrl()]))"
          class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        @if ($question->exists) @method('PUT') <input type="hidden" name="page" value="{{ request('page') }}"> @endif

        <div class="min-w-0 space-y-5">
            @if (($used ?? 0) > 0)
                <p class="flex gap-2 rounded-2xl border border-hivis/30 bg-hivis/[0.07] px-4 py-3 text-sm text-hivis"><i class="ri-information-line text-lg"></i> Soal ini sudah dijawab {{ $used }}×. Bila kunci diubah, tekan “Hitung ulang nilai” di halaman ujian terkait.</p>
            @endif
            <section class="a-card space-y-4 p-5">
                <div>
                    <label class="label" for="body">Pertanyaan</label>
                    <textarea id="body" name="body" x-ref="body" rows="4" required maxlength="5000" @paste="pasteAll($event)" class="field leading-relaxed" placeholder="Tulis pertanyaan… (bisa tempel satu soal lengkap berisi baris A. B. C. D. dan Kunci: B)">{{ old('body', $question->body) }}</textarea>
                </div>

                <fieldset>
                    <legend class="label">Pilihan jawaban — klik huruf untuk menandai kunci</legend>
                    <div class="space-y-2">
                        <template x-for="(opt, i) in options" :key="i">
                            <div class="flex items-start gap-2">
                                <label class="grid h-[50px] w-[50px] shrink-0 cursor-pointer place-items-center rounded-2xl border font-display text-lg font-bold transition"
                                       :class="answer === letters[i] ? 'border-neon bg-neon text-[#04170d] shadow-[0_8px_24px_-10px_rgba(61,255,142,.8)]' : 'border-white/10 text-mist hover:border-white/25'" :title="`Jadikan ${letters[i]} kunci jawaban`">
                                    <input type="radio" name="answer" :value="letters[i]" x-model="answer" class="sr-only"><span x-text="letters[i]"></span>
                                </label>
                                <textarea :name="`options[${letters[i]}]`" x-model="options[i]" rows="1" maxlength="1000" class="field min-h-[50px] grow resize-y" :placeholder="`Opsi ${letters[i]}`"></textarea>
                                <button type="button" @click="removeOption(i)" x-show="options.length > 2" class="icon-btn mt-1.5 hover:!text-alarm" aria-label="Hapus opsi"><i class="ri-close-line text-xl"></i></button>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addOption()" x-show="options.length < 6" class="mt-2 text-sm font-semibold text-neon"><i class="ri-add-line"></i> Tambah opsi</button>
                    @error('options') <p class="error">{{ $message }}</p> @enderror
                    @error('answer') <p class="error">{{ $message }}</p> @enderror
                </fieldset>

                <div>
                    <label class="label" for="explanation">Pembahasan (opsional)</label>
                    <textarea id="explanation" name="explanation" rows="2" maxlength="3000" class="field" placeholder="Tampil ke peserta bila mode hasil 'Nilai + pembahasan'">{{ old('explanation', $question->explanation) }}</textarea>
                </div>
            </section>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <section class="a-card p-5">
                <p class="label">Gambar (opsional)</p>
                <div class="overflow-hidden rounded-2xl border border-dashed border-white/15 bg-white/[0.02]">
                    <template x-if="preview && !removeImage"><img :src="preview" alt="" class="max-h-64 w-full bg-white object-contain"></template>
                    <label for="q-image" x-show="!preview || removeImage" class="flex cursor-pointer flex-col items-center gap-2 px-4 py-8 text-center text-sm text-mist hover:text-fog">
                        <i class="ri-image-add-line text-3xl"></i> Pilih foto/diagram<span class="text-xs">JPG, PNG, WebP · maks 8 MB</span>
                    </label>
                </div>
                <div x-show="preview && !removeImage" class="mt-2 flex gap-3 text-sm">
                    <label for="q-image" class="cursor-pointer font-semibold text-neon">Ganti</label>
                    <button type="button" @click="removeImage = true" class="font-semibold text-red-300">Hapus gambar</button>
                </div>
 <input id="q-image" type="file" name="image" accept="image/*" class="sr-only" @change="pick($event)">
                <input type="hidden" name="remove_image" :value="removeImage ? 1 : 0">
                <label class="label mt-4" for="image_url">atau tautan gambar</label>
                <input id="image_url" name="image_url" type="url" maxlength="500" class="field text-sm" placeholder="https://raw.githubusercontent.com/…">
                @error('image') <p class="error">{{ $message }}</p> @enderror
            </section>
            <div class="a-card grid gap-2 p-5">
                <button class="btn-neon w-full"><i class="ri-save-3-line text-lg"></i> Simpan soal</button>
                @unless ($question->exists)
                    <button name="next" value="lagi" class="btn-ghost w-full text-sm"><i class="ri-add-line"></i> Simpan & tambah lagi</button>
                @endunless
            </div>
        </aside>
    </form>
</x-layouts.admin>
