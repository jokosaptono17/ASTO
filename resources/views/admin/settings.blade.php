<x-layouts.admin title="Pengaturan">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-3xl space-y-5">
        @csrf @method('PUT')
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
