<x-layouts.admin title="Pemberian poin" subtitle="Reward poin untuk juara, improvement, temuan hazard, dan event lain">
    <x-slot:actions>
        <a href="{{ route('admin.points.grants.create') }}" class="btn-neon !min-h-11 text-sm"><i class="ri-add-line text-lg"></i> Pemberian baru</a>
    </x-slot:actions>
    @include('admin.points._tabs')

    <div class="mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.points.grants.index') }}" class="rounded-full px-3.5 py-1.5 text-sm font-semibold {{ ! $status ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog' }}">Semua</a>
        @foreach (\App\Models\PointGrant::STATUSES as $k => $s)
            <a href="{{ route('admin.points.grants.index', ['status' => $k]) }}" class="rounded-full px-3.5 py-1.5 text-sm font-semibold {{ $status === $k ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog' }}">{{ explode(' ·', $s['label'])[0] }} <span class="opacity-70">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
        <p class="ml-auto text-xs text-mist"><i class="ri-shield-check-line text-hivis"></i> Pengajuan Administrator ≤ {{ number_format($threshold, 0, ',', '.') }} poin langsung terkirim; selebihnya perlu persetujuan Administrator lain.</p>
    </div>

    <div class="a-card overflow-hidden">
        <table class="w-full">
            <thead class="hidden md:table-header-group"><tr><th class="a-th">Pemberian</th><th class="a-th">Penerima</th><th class="a-th text-right">Total poin</th><th class="a-th">Status</th><th class="a-th">Diajukan</th></tr></thead>
            <tbody>
                @forelse ($grants as $g)
                    <tr class="cursor-pointer transition hover:bg-white/[0.02]" onclick="location.href='{{ route('admin.points.grants.show', $g) }}'">
                        <td class="a-td">
                            <a href="{{ route('admin.points.grants.show', $g) }}" class="flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $g->status === 'approved' ? 'bg-neon/10 text-neon' : 'bg-hivis/10 text-hivis' }}"><i class="ri-trophy-line text-lg"></i></span>
                                <span class="min-w-0 leading-tight"><span class="block truncate font-semibold">{{ $g->title }}</span><span class="text-xs text-mist">oleh {{ $g->creator?->displayName() ?? '–' }}</span></span>
                            </a>
                        </td>
                        <td class="a-td hidden md:table-cell">{{ $g->recipients_count }} karyawan</td>
                        <td class="a-td text-right font-display text-lg font-bold text-hivis">{{ number_format($g->total_points, 0, ',', '.') }}</td>
                        <td class="a-td"><span class="{{ \App\Models\PointGrant::STATUSES[$g->status]['chip'] }}">{{ \App\Models\PointGrant::STATUSES[$g->status]['label'] }}</span></td>
                        <td class="a-td hidden text-sm text-mist md:table-cell">{{ ($g->submitted_at ?? $g->created_at)->locale('id')->translatedFormat('j M Y') }}@if ($g->reviewer)<br><span class="text-xs">ditinjau {{ $g->reviewer->displayName() }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="a-td py-14 text-center text-mist"><i class="ri-trophy-line text-4xl"></i><p class="mt-2">Belum ada pemberian poin.</p><a href="{{ route('admin.points.grants.create') }}" class="btn-neon mt-3 !min-h-10 text-sm">Buat pemberian pertama</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $grants->links('admin.pagination') }}</div>
</x-layouts.admin>
