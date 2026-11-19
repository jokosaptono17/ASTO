<x-layouts.admin title="Pengaturan">
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-5">
        @csrf @method('PUT')
        <section class="a-card overflow-hidden" x-data="{ preview: @js($hero['src']), reset: false }">
            <div class="relative aspect-[3/1] bg-pit">
                <img :src="preview" alt="" class="absolute inset-0 h-full w-full object-cover" :class="reset && 'opacity-30'">
                <div class="absolute inset-0 bg-gradient-to-t from-pit via-pit/30 to-pit/10"></div>
                <x-contour class="absolute inset-0 h-full w-full opacity-30 mix-blend-screen" />
                <div class="absolute bottom-4 left-5">
                    <p class="font-display text-xl font-bold">Foto latar</p>
                    <p class="text-sm text-fog">Hero beranda desktop & halaman masuk</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 p-5">
                <label class="btn-ghost !min-h-10 cursor-pointer text-sm"><i class="ri-image-add-line text-lg"></i> Ganti foto
                    <input type="file" name="hero" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); reset = false }">
                </label>
                @if ($heroCustom)
                    <label class="flex items-center gap-2 text-sm text-fog"><input type="checkbox" name="hero_reset" value="1" x-model="reset" class="h-4 w-4 accent-[var(--color-neon)]"> Kembalikan ke foto bawaan</label>
                @endif
                <p class="w-full text-xs text-mist">Disarankan foto lanskap ≥ 1920 px lebar (mis. foto jobsite ASTO). Dipotong otomatis ke rasio 2:1. Pastikan foto milik sendiri atau boleh dipakai.</p>
                @error('hero') <p class="error w-full">{{ $message }}</p> @enderror
            </div>
        </section>
        <section class="a-card grid gap-5 p-5 sm:grid-cols-2">
            <h2 class="font-display font-semibold sm:col-span-2">Identitas portal</h2>
            <div>
                <label class="label" for="site_name">Nama portal</label>
                <input id="site_name" name="site_name" value="{{ old('site_name', $values['site_name']) }}" required maxlength="40" class="field">
            </div>
            <div>
                <label class="label" for="site_tagline">Keterangan</label>
                <input id="site_tagline" name="site_tagline" value="{{ old('site_tagline', $values['site_tagline']) }}" required maxlength="80" class="field">
            </div>
            <div>
                <label class="label" for="slogan">Slogan di header</label>
                <input id="slogan" name="slogan" value="{{ old('slogan', $values['slogan']) }}" required maxlength="80" class="field">
            </div>
            <div>
                <label class="label" for="campaign_section_title">Judul bagian kampanye</label>
                <input id="campaign_section_title" name="campaign_section_title" value="{{ old('campaign_section_title', $values['campaign_section_title']) }}" required maxlength="40" class="field">
            </div>
        </section>
        <section class="a-card grid gap-5 p-5 sm:grid-cols-2">
            <h2 class="font-display font-semibold sm:col-span-2">Bantuan</h2>
            <div>
                <label class="label" for="help_whatsapp">Nomor WhatsApp admin</label>
                <input id="help_whatsapp" name="help_whatsapp" value="{{ old('help_whatsapp', $values['help_whatsapp']) }}" required inputmode="tel" class="field">
                <p class="mt-1 text-xs text-mist">Tujuan tombol "Butuh bantuan?" dan Mentari.</p>
            </div>
            <div>
                <label class="label" for="help_message">Pesan pembuka WhatsApp</label>
                <input id="help_message" name="help_message" value="{{ old('help_message', $values['help_message']) }}" required maxlength="200" class="field">
            </div>
            <div class="sm:col-span-2">
                <label class="label" for="mentari_greeting">Sapaan Mentari di halaman masuk</label>
                <textarea id="mentari_greeting" name="mentari_greeting" rows="2" required maxlength="300" class="field">{{ old('mentari_greeting', $values['mentari_greeting']) }}</textarea>
            </div>
        </section>
        <button class="btn-neon"><i class="ri-save-3-line text-lg"></i> Simpan pengaturan</button>
    </form>
</x-layouts.admin>
