<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceLocation;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Jadwal rutin: P5M per shift, Safety Talk mingguan, dst. */
class AttendanceScheduleController extends Controller
{
    public function index(): View
    {
        return view('admin.attendance.schedules', [
            'schedules' => AttendanceSchedule::with('type')->withCount('sessions')->orderByDesc('is_active')->orderBy('attendance_type_id')->orderBy('open_time')->get(),
            'types' => AttendanceType::where('is_active', true)->orderBy('sort_order')->get(),
            'areas' => AttendanceLocation::options(),
            'departments' => User::where('is_active', true)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $schedule = new AttendanceSchedule;
        $this->save($request, $schedule);

        return back()->with('toast', 'Jadwal ditambahkan. Absen dibuka otomatis sesuai jadwal.');
    }

    public function update(Request $request, AttendanceSchedule $schedule): RedirectResponse
    {
        $this->save($request, $schedule);

        return back()->with('toast', 'Jadwal disimpan.');
    }

    public function destroy(AttendanceSchedule $schedule): RedirectResponse
    {
        $schedule->update(['is_active' => false]);
        $schedule->syncUpcoming();
        $schedule->delete();
        ActivityLog::record('absensi', 'Menghapus jadwal rutin '.$schedule->type->name.' '.$schedule->title);

        return back()->with('toast', 'Jadwal dihapus. Riwayat absensinya tetap tersimpan.');
    }

    private function save(Request $request, AttendanceSchedule $schedule): void
    {
        $data = $request->validate([
            'attendance_type_id' => ['required', Rule::exists('attendance_types', 'id')],
            'title' => ['nullable', 'string', 'max:100'],
            'shift' => ['nullable', Rule::in(['1', '2'])],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:0,6'],
            'open_time' => ['required', 'date_format:H:i'],
            'close_time' => ['required', 'date_format:H:i', 'different:open_time'],
            'late_after' => ['nullable', 'integer', 'min:1', 'max:600'],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['integer', Rule::exists('attendance_locations', 'id')],
            'target_departments' => ['nullable', 'array'],
            'target_departments.*' => ['string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], ['days.required' => 'Pilih minimal satu hari.'], ['open_time' => 'jam buka', 'close_time' => 'jam tutup']);

        $schedule->fill([
            'attendance_type_id' => $data['attendance_type_id'],
            'title' => $data['title'] ?? null,
            'shift' => $data['shift'] ?? null,
            'days' => array_values(array_unique(array_map('intval', $data['days']))),
            'open_time' => $data['open_time'],
            'close_time' => $data['close_time'],
            'late_after' => $data['late_after'] ?? null,
            'location_ids' => array_values(array_map('intval', $data['location_ids'] ?? [])) ?: null,
            'target_departments' => array_values(array_filter($data['target_departments'] ?? [])) ?: null,
            'company' => $schedule->company ?? 'PT KPP MINING',
            'is_active' => $request->boolean('is_active', true),
        ]);
        $schedule->save();
        $schedule->load('type');
        $schedule->syncUpcoming();
        ActivityLog::record('absensi', 'Menyimpan jadwal rutin '.$schedule->type->name.' '.$schedule->title.' ('.$schedule->daysLabel().' '.$schedule->window().')');
    }
}
