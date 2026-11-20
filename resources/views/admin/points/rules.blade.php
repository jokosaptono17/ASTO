<x-layouts.admin title="Aturan poin" subtitle="Poin otomatis dari kegiatan & batas persetujuan">
    @include('admin.points._tabs')
    <form method="POST" action="{{ route('admin.points.rules.update') }}" class="grid max-w-5xl gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        @csrf @method('PUT')
        <div class="space-y-5">
            <section class="a-card overflow-hidden">
                <div class="border-b border-white/[0.06] p-5">
                    <h2 class="font-display text-lg font-semibold">Poin otomatis</h2>
                    <p class="text-sm text-mist">Diberikan sistem saat karyawan tercatat hadir. Dicabut otomatis bila host menghapus kehadirannya.</p>
                </div>
                @foreach ($rules as $rule)
                    <label class="flex flex-wrap items-center gap-4 border-b border-white/[0.04] px-5 py-4 last:border-0" x-data="{ on: @js($rule->is_active) }">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" :class="on ? 'bg-neon/10 text-neon' : 'bg-white/[0.05] text-mist'"><i class="{{ ['attend' => 'ri-team-line', 'speaker' => 'ri-mic-line', 'streak7' => 'ri-fire-line'][$rule->key] ?? 'ri-flashlight-line' }} text-xl"></i></span>
                        <span class="min-w-0 grow leading-tight"><span class="block font-semibold">{{ $rule->label }}</span><span class="text-sm text-mist">{{ $rule->hint }}</span></span>
                        <span class="flex items-center gap-2"><x-coin :size="18" /><input name="rules[{{ $rule->key }}][points]" type="number" min="0" max="10000" value="{{ $rule->points }}" class="field !w-24 !py-2 text-center font-display text-lg font-bold"></span>
                        <span class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="rules[{{ $rule->key }}][active]" value="1" x-model="on" class="peer sr-only">
                            <span class="h-7 w-12 rounded-full bg-white/10 transition peer-checked:bg-neon"></span>
                            <span class="absolute left-1 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                @endforeach
            </section>
            <section class="a-card p-5">
                <h2 class="font-display text-lg font-semibold">Persetujuan pemberian poin</h2>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label for="threshold" class="text-sm text-fog">Pengajuan Administrator sampai</label>
                    <input id="threshold" name="threshold" type="number" min="0" value="{{ $threshold }}" class="field !w-32 !py-2 text-center font-display font-bold">
                    <span class="text-sm text-fog">poin langsung terkirim.</span>
                </div>
                <p class="mt-2 text-xs text-mist">Di atas batas ini (dan semua pengajuan Admin SHE) harus disetujui Administrator. Pengajuan sendiri disetujui Administrator lain.</p>
            </section>
            <button class="btn-neon"><i class="ri-save-3-line text-lg"></i> Simpan aturan</button>
        </div>

        <aside class="space-y-4">
            <section class="a-card p-5">
                <h2 class="font-display text-lg font-semibold">Level karyawan</h2>
                <p class="text-xs text-mist">Berdasarkan total poin yang pernah diperoleh (tidak berkurang saat ditukar).</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($levels as $lv)
                        <li class="flex items-center gap-3"><i class="ri-vip-crown-2-fill text-lg" style="color: {{ $lv['color'] }}"></i><span class="grow font-semibold">{{ $lv['name'] }}</span><span class="font-display text-sm text-mist">≥ {{ number_format($lv['min'], 0, ',', '.') }}</span></li>
                    @endforeach
                </ul>
            </section>
            <section class="rounded-2xl border border-hivis/30 bg-hivis/[0.05] p-5 text-sm leading-relaxed text-fog">
                <p class="font-semibold text-hivis"><i class="ri-shield-check-line"></i> Prinsip Poin HEBAT</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Poin tidak bisa dibeli, dicairkan, atau ditransfer antar karyawan.</li>
                    <li>Jam tidur tidak memengaruhi poin — agar karyawan tetap jujur soal fatigue.</li>
                    <li>Semua perubahan tercatat di riwayat dan tidak bisa dihapus.</li>
                </ul>
            </section>
        </aside>
    </form>
</x-layouts.admin>
