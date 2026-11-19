<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Area absensi. Poligon direkam di lokasi: admin berjalan ke tiap sudut area
 * dan menandai titik dari GPS HP-nya.
 */
class AttendanceLocationController extends Controller
{
    public function index(): View
    {
        $locations = AttendanceLocation::orderByDesc('is_active')->orderBy('name')->get();
        $since = now()->subDays(30);
        $usage = AttendanceRecord::where('checked_in_at', '>=', $since)->whereNotNull('attendance_location_id')
            ->selectRaw('attendance_location_id, count(*) as n')->groupBy('attendance_location_id')->pluck('n', 'attendance_location_id');

        return view('admin.attendance.locations', [
            'locations' => $locations,
            'usage' => $usage,
            'unverified' => AttendanceRecord::where('checked_in_at', '>=', $since)->where('geo_status', 'unverified')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.attendance.location-editor', ['location' => new AttendanceLocation(['buffer_m' => 25, 'is_active' => true])]);
    }

    public function edit(AttendanceLocation $location): View
    {
        return view('admin.attendance.location-editor', ['location' => $location]);
    }

    public function store(Request $request): RedirectResponse
    {
        $location = new AttendanceLocation;
        $this->save($request, $location);

        return redirect()->route('admin.attendance.locations')->with('toast', "Area {$location->name} disimpan.");
    }

    public function update(Request $request, AttendanceLocation $location): RedirectResponse
    {
        $this->save($request, $location);

        return redirect()->route('admin.attendance.locations')->with('toast', "Area {$location->name} disimpan.");
    }

    public function destroy(AttendanceLocation $location): RedirectResponse
    {
        if (AttendanceRecord::where('attendance_location_id', $location->id)->exists()) {
            $location->update(['is_active' => false]);
            $msg = "Area {$location->name} dinonaktifkan (masih dipakai di riwayat absensi).";
        } else {
            $location->delete();
            $msg = "Area {$location->name} dihapus.";
        }
        ActivityLog::record('absensi', $msg);

        return redirect()->route('admin.attendance.locations')->with('toast', $msg);
    }

    private function save(Request $request, AttendanceLocation $location): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'buffer_m' => ['required', 'integer', 'min:0', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'polygon' => ['nullable', 'string', 'max:20000'],
        ], ['name.required' => 'Beri nama area.'], ['buffer_m' => 'toleransi']);

        $points = json_decode($data['polygon'] ?? '', true);
        $valid = is_array($points) && count($points) >= 3 && count($points) <= 300
            && collect($points)->every(fn ($p) => is_array($p) && count($p) === 2 && is_numeric($p[0]) && is_numeric($p[1]) && abs($p[0]) <= 90 && abs($p[1]) <= 180);
        if (($data['polygon'] ?? '') !== '' && ! $valid) {
            throw \Illuminate\Validation\ValidationException::withMessages(['polygon' => 'Garis area minimal 3 titik.']);
        }

        $location->fill(['name' => $data['name'], 'buffer_m' => $data['buffer_m'], 'is_active' => $request->boolean('is_active', true)]);
        $changed = false;
        if ($valid && json_encode($points) !== json_encode($location->polygon)) {
            $location->setPolygon($points, $request->user());
            $changed = true;
        }
        $location->save();
        ActivityLog::record('absensi', ($changed ? 'Memetakan area ' : 'Mengubah area ').$location->name.($changed ? ' ('.count($points).' titik, '.$location->areaLabel().')' : ''));
    }
}
