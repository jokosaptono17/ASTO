<x-layouts.portal title="Riwayat absensi">
    @include('portal._topbar', ['heading' => 'Riwayat absensi', 'back' => route('attendance.scan')])

    <div class="space-y-5 px-4">
        <section class="grid grid-cols-4 overflow-hidden rounded-2xl border border-white/[0.07] bg-bench text-center">
            @foreach ([['Bulan ini', $stats['month'], 'text-neon'], ['Terlambat', $stats['late'], $stats['late'] ? 'text-hivis' : ''], ['Pemateri', $stats['speaker'], ''], ['Rata tidur', $stats['avgSleep'] ? intdiv($stats['avgSleep'], 60).'j'.($stats['avgSleep'] % 60 ? ' '.($stats['avgSleep'] % 60).'m' : '') : '–', '']] as $i => [$label, $value, $tone])
                <div class="py-3 {{ $i ? 'border-l border-white/[0.06]' : '' }}">
                    <p class="font-display text-xl font-bold {{ $tone }}">{{ $value }}</p>
                    <p class="text-[11px] text-mist">{{ $label }}</p>
                </div>
            @endforeach
        </section>

        @forelse ($records as $r)
            @php $f = $r->fatigue ? \App\Models\AttendanceRecord::FATIGUE[$r->fatigue] : null; @endphp
            <article class="flex gap-3 rounded-2xl border border-white/[0.06] bg-bench p-3.5" style="box-shadow: inset 3px 0 0 {{ $r->session->type->color }}">
                <div class="w-12 shrink-0 text-center">
                    <p class="font-display text-xl leading-none font-bold">{{ $r->checked_in_at->format('d') }}</p>
                    <p class="mt-0.5 text-[11px] text-mist uppercase">{{ $r->checked_in_at->locale('id')->translatedFormat('M') }}</p>
                </div>
                <div class="min-w-0 grow">
                    <p class="truncate font-semibold">{{ $r->session->type->name }}{{ $r->session->title ? ' — '.$r->session->title : '' }}</p>
                    <p class="mt-0.5 text-sm text-mist">{{ $r->checked_in_at->format('H:i') }}{{ $r->session->location ? ' · '.$r->session->location : '' }}</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @if ($r->role === 'pemateri') <span class="chip bg-hivis/15 text-hivis">⭐ Pemateri</span> @endif
                        @if ($r->is_late) <span class="chip-penting">Terlambat</span> @endif
                        @if ($f) <span class="chip {{ ['neon' => 'bg-neon/15 text-neon', 'hivis' => 'bg-hivis/15 text-hivis', 'alarm' => 'bg-alarm/15 text-red-300'][$f['tone']] }}">{{ $f['label'] }} · {{ $r->sleepLabel() }}</span> @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="flex flex-col items-center rounded-[22px] border border-dashed border-white/10 px-6 py-10 text-center">
                <i class="ri-calendar-check-line text-4xl text-mist/50"></i>
                <p class="mt-3 text-fog">Belum ada absensi. Scan QR di layar P5M atau safety talk untuk absen.</p>
            </div>
        @endforelse

        {{ $records->links('admin.pagination') }}
    </div>
</x-layouts.portal>
