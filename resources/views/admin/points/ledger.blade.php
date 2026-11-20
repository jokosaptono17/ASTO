<x-layouts.admin title="Riwayat poin" subtitle="Buku besar semua poin masuk & keluar — tidak bisa diubah">
    <x-slot:actions>
        <a href="{{ route('admin.points.rules') }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-settings-3-line text-lg"></i> Aturan poin</a>
        <a href="{{ route('admin.points.ledger.export', request()->query()) }}" class="btn-ghost !min-h-11 text-sm"><i class="ri-file-excel-2-line text-lg text-neon"></i> Ekspor Excel</a>
    </x-slot:actions>
    @include('admin.points._tabs')

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0">
            <form class="a-card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto]">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="NRP, nama, atau keterangan" class="field !py-2">
                <select name="kind" class="field !py-2"><option value="">Semua jenis</option>@foreach (\App\Models\PointTransaction::KINDS as $k => $v)<option value="{{ $k }}" @selected(($filters['kind'] ?? '') === $k)>{{ $v['label'] }}</option>@endforeach</select>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="field !py-2" aria-label="Dari tanggal">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="field !py-2" aria-label="Sampai tanggal">
                <button class="btn-neon !min-h-10 text-sm"><i class="ri-filter-3-line"></i> Terapkan</button>
            </form>
            <div class="mb-3 flex gap-4 text-sm"><span class="text-mist">Total sesuai filter:</span><span class="font-display font-bold text-neon">+{{ number_format($sum['in'], 0, ',', '.') }}</span><span class="font-display font-bold text-fog">−{{ number_format($sum['out'], 0, ',', '.') }}</span></div>
            <div class="a-card overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead><tr><th class="a-th">Waktu</th><th class="a-th">Karyawan</th><th class="a-th">Keterangan</th><th class="a-th text-right">Poin</th><th class="a-th text-right">Saldo</th></tr></thead>
                    <tbody>
                        @forelse ($transactions as $t)
                            <tr>
                                <td class="a-td whitespace-nowrap text-mist">{{ $t->created_at->format('d/m/Y') }}<br><span class="text-xs">{{ $t->created_at->format('H:i') }}</span></td>
                                <td class="a-td"><span class="block font-medium">{{ $t->user?->displayName() }}</span><span class="font-display text-xs text-mist">{{ $t->user?->nrp }}</span></td>
                                <td class="a-td"><span class="flex items-center gap-2"><i class="{{ $t->icon() }} text-mist"></i><span>{{ $t->description }}</span></span><span class="text-xs text-mist">{{ \App\Models\PointTransaction::KINDS[$t->kind]['label'] }}{{ $t->creator ? ' · oleh '.$t->creator->displayName() : '' }}</span></td>
                                <td class="a-td text-right font-display font-bold {{ $t->amount > 0 ? 'text-neon' : 'text-fog' }}">{{ $t->amount > 0 ? '+' : '−' }}{{ number_format(abs($t->amount), 0, ',', '.') }}</td>
                                <td class="a-td text-right font-display text-mist">{{ number_format($t->balance_after, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="a-td py-12 text-center text-mist">Tidak ada transaksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $transactions->links('admin.pagination') }}</div>
        </div>

        @can('admin')
            <aside class="self-start xl:sticky xl:top-24">
                <form method="POST" action="{{ route('admin.points.adjust') }}" class="a-card space-y-3 p-5" onsubmit="return confirm('Simpan penyesuaian poin ini?')">
                    @csrf
                    <h2 class="font-display text-lg font-semibold">Penyesuaian manual</h2>
                    <p class="text-xs text-mist">Untuk koreksi. Tercatat di riwayat dengan nama Anda dan tidak bisa dihapus.</p>
                    <div><label class="label" for="adj-nrp">NRP</label><input id="adj-nrp" name="nrp" value="{{ old('nrp') }}" required class="field uppercase" placeholder="KT16042">@error('nrp')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="adj-amount">Jumlah (+ tambah / − kurangi)</label><input id="adj-amount" name="amount" type="number" value="{{ old('amount') }}" required class="field font-display" placeholder="-20">@error('amount')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="adj-reason">Alasan</label><input id="adj-reason" name="reason" value="{{ old('reason') }}" required maxlength="150" class="field" placeholder="Koreksi absen ganda 12/11">@error('reason')<p class="error">{{ $message }}</p>@enderror</div>
                    <button class="btn-neon w-full !min-h-11 text-sm"><i class="ri-equalizer-line"></i> Simpan penyesuaian</button>
                </form>
            </aside>
        @endcan
    </div>
</x-layouts.admin>
