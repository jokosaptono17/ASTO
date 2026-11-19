<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.attendance.types', ['types' => AttendanceType::withCount('sessions')->orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        AttendanceType::create($this->validated($request) + ['sort_order' => (AttendanceType::max('sort_order') ?? 0) + 10]);

        return back()->with('toast', 'Jenis kegiatan ditambahkan.');
    }

    public function update(Request $request, AttendanceType $type): RedirectResponse
    {
        $type->update($this->validated($request));

        return back()->with('toast', 'Jenis kegiatan disimpan.');
    }

    public function destroy(AttendanceType $type): RedirectResponse
    {
        if ($type->sessions()->exists()) {
            $type->update(['is_active' => false]);

            return back()->with('toast', 'Jenis ini sudah dipakai sesi, jadi dinonaktifkan (tidak dihapus).');
        }
        $type->delete();

        return back()->with('toast', 'Jenis kegiatan dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', 'regex:/^ri-[a-z0-9-]+$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return $data + [
            'track_fatigue' => $request->boolean('track_fatigue'),
            'allow_speaker' => $request->boolean('allow_speaker'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
