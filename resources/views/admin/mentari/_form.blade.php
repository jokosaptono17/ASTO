<form method="POST" action="{{ $faq->exists ? route('admin.mentari.update', $faq) : route('admin.mentari.store') }}" class="grid gap-3 sm:grid-cols-2">
    @csrf
    @if ($faq->exists) @method('PUT') @endif
    <input name="question" value="{{ $faq->question }}" required maxlength="160" placeholder="Topik / pertanyaan" class="field !py-2.5 text-sm sm:col-span-2">
    <input name="keywords" value="{{ $faq->keywords }}" required maxlength="300" placeholder="Kata kunci, pisahkan dengan koma" class="field !py-2.5 text-sm sm:col-span-2">
    <textarea name="answer" required maxlength="2000" rows="3" placeholder="Jawaban Mentari" class="field !py-2.5 text-sm sm:col-span-2">{{ $faq->answer }}</textarea>
    <input name="link_label" value="{{ $faq->link_label }}" maxlength="40" placeholder="Label tombol (opsional)" class="field !py-2.5 text-sm">
    <input name="link_url" value="{{ $faq->link_url }}" maxlength="500" placeholder="https://… atau /aplikasi" class="field !py-2.5 text-sm">
    <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($faq->is_active) class="h-4 w-4 accent-[var(--color-neon)]"> Aktif</label>
        <span class="grow"></span>
        @if ($faq->exists)
            <button type="button" @click="edit = null" class="btn-ghost !min-h-9 text-sm">Batal</button>
            <button form="del-faq-{{ $faq->id }}" class="btn-ghost !min-h-9 text-sm text-red-300">Hapus</button>
        @endif
        <button class="btn-neon !min-h-9 text-sm">{{ $faq->exists ? 'Simpan' : 'Tambah' }}</button>
    </div>
</form>
@if ($faq->exists)
    <form id="del-faq-{{ $faq->id }}" method="POST" action="{{ route('admin.mentari.destroy', $faq) }}" onsubmit="return confirm('Hapus jawaban ini?')">@csrf @method('DELETE')</form>
@endif
