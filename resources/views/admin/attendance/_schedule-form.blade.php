{{-- Form jadwal rutin (tambah & ubah) --}}
@php
    $sid = $schedule->id ?? 'new';
    $days = array_map('intval', $schedule->days ?? [0, 1, 2, 3, 4, 5, 6]);
    $areaIds = array_map('intval', $schedule->location_ids ?? []);
    $targets = $schedule->target_departments ?? [];
@endphp
<form method="POST" action="{{ $schedule->exists ? route('admin.attendance.schedules.update', $schedule) : route('admin.attendance.schedules.store') }}" class="space-y-4"
      x-data="{ type: @js((string) ($schedule->attendance_type_id ?? $types->first()?->id)) }">
    @csrf
    @if ($schedule->exists) @method('PUT') @endif
    <div class="grid gap-2 sm:grid-cols-3">
        @foreach ($types as $t)
            <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border p-2.5 transition" :class="type === '{{ $t->id }}' ? 'border-neon/60 bg-neon/[0.07]' : 'border-white/10'">
                <input type="radio" name="attendance_type_id" value="{{ $t->id }}" x-model="type" class="sr-only">
                <i class="{{ $t->icon }} text-lg" style="color: {{ $t->color }}"></i><span class="text-sm font-semibold">{{ $t->name }}</span>
            </label>
        @endforeach
    </div>
    <div class="grid gap-3 sm:grid-cols-[1fr_120px]">
        <div>
            <label class="label" for="title-{{ $sid }}">Nama jadwal</label>
            <input id="title-{{ $sid }}" name="title" value="{{ $schedule->title }}" maxlength="100" class="field" placeholder="Shift 1, Rutin Sabtu, …">
        </div>
        <div>
            <label class="label" for="shift-{{ $sid }}">Shift</label>
            <select id="shift-{{ $sid }}" name="shift" class="field">
                <option value="">–</option>
                @foreach (['1', '2'] as $sh) <option value="{{ $sh }}" @selected($schedule->shift === $sh)>Shift {{ $sh }}</option> @endforeach
            </select>
        </div>
    </div>
    <div>
        <p class="label">Hari</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach (\App\Models\AttendanceSchedule::DAYS as $d => $label)
                <label class="cursor-pointer">
                    <input type="checkbox" name="days[]" value="{{ $d }}" @checked(in_array($d, $days, true)) class="peer sr-only">
                    <span class="grid h-10 w-12 place-items-center rounded-xl border border-white/10 text-sm font-semibold text-mist transition peer-checked:border-neon/60 peer-checked:bg-neon/15 peer-checked:text-neon">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="label" for="open-{{ $sid }}">Dibuka</label>
            <input id="open-{{ $sid }}" name="open_time" type="time" value="{{ substr((string) $schedule->open_time, 0, 5) }}" required class="field font-display">
        </div>
        <div>
            <label class="label" for="close-{{ $sid }}">Ditutup</label>
            <input id="close-{{ $sid }}" name="close_time" type="time" value="{{ substr((string) $schedule->close_time, 0, 5) }}" required class="field font-display">
        </div>
        <div class="col-span-2">
            <label class="label" for="late-{{ $sid }}">Terlambat setelah (menit)</label>
            <input id="late-{{ $sid }}" name="late_after" type="number" min="1" max="600" value="{{ $schedule->late_after }}" class="field" placeholder="–">
        </div>
    </div>
    <p class="-mt-2 text-xs text-mist">Terlambat dihitung dari jam dibuka. Kosongkan bila keterlambatan tidak dicatat.</p>
    <div>
        <p class="label">Area GPS</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($areas as $a)
                <label class="cursor-pointer">
                    <input type="checkbox" name="location_ids[]" value="{{ $a->id }}" @checked(in_array($a->id, $areaIds, true)) class="peer sr-only">
                    <span class="inline-flex items-center gap-1 rounded-full border border-white/10 px-3 py-1.5 text-sm text-fog transition peer-checked:border-neon/60 peer-checked:bg-neon/10 peer-checked:text-neon"><i class="ri-map-pin-2-line"></i>{{ $a->name }}</span>
                </label>
            @endforeach
        </div>
        <p class="mt-1 text-xs text-mist">Tidak dicentang = boleh absen dari semua area.</p>
    </div>
    <details class="group rounded-xl border border-white/[0.07] px-3 py-2">
        <summary class="flex cursor-pointer list-none items-center gap-2 text-sm text-fog"><i class="ri-group-line"></i> Seksi peserta <span class="text-mist">{{ $targets ? count($targets).' seksi' : 'semua karyawan' }}</span><i class="ri-arrow-down-s-line ml-auto transition group-open:rotate-180"></i></summary>
        <p class="mt-2 text-xs text-mist">Dipakai untuk daftar "belum hadir" di halaman live.</p>
        <div class="mt-2 flex flex-wrap gap-1.5 pb-1">
            @foreach ($departments as $d)
                <label class="cursor-pointer">
                    <input type="checkbox" name="target_departments[]" value="{{ $d }}" @checked(in_array($d, $targets, true)) class="peer sr-only">
                    <span class="inline-flex rounded-full border border-white/10 px-2.5 py-1 text-xs text-fog transition peer-checked:border-neon/60 peer-checked:bg-neon/10 peer-checked:text-neon">{{ $d }}</span>
                </label>
            @endforeach
        </div>
    </details>
    <div class="flex flex-wrap items-center gap-3">
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($schedule->is_active ?? true) class="h-4 w-4 accent-[var(--color-neon)]"> Aktif</label>
        <span class="grow"></span>
        {{ $slot ?? '' }}
        <button class="btn-neon !min-h-10 text-sm"><i class="ri-save-3-line"></i> {{ $schedule->exists ? 'Simpan' : 'Tambah jadwal' }}</button>
    </div>
</form>
