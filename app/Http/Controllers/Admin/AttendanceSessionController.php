<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceType;
use App\Models\User;
use App\Services\Spreadsheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Host (GL, Admin SHE, Administrator) membuat dan memimpin sesi absensi.
 */
class AttendanceSessionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $base = AttendanceSession::visibleTo($user)->with('type', 'host')->withCount('records');

        return view('admin.attendance.index', [
            'live' => (clone $base)->openNow()->orderBy('starts_at')->get(),
            'upcoming' => (clone $base)->whereNull('closed_at')->where('opens_at', '>', now())->orderBy('opens_at')->limit(10)->get(),
            'recent' => (clone $base)->where(fn ($q) => $q->whereNotNull('closed_at')->orWhere('closes_at', '<', now()))
                ->orderByDesc('starts_at')->paginate(15)->withQueryString(),
            'today' => [
                'sessions' => AttendanceSession::visibleTo($user)->whereDate('date', today())->count(),
                'present' => AttendanceRecord::whereHas('session', fn ($q) => $q->visibleTo($user)->whereDate('date', today()))->count(),
                'late' => AttendanceRecord::where('is_late', true)->whereHas('session', fn ($q) => $q->visibleTo($user)->whereDate('date', today()))->count(),
                'risk' => AttendanceRecord::where('fatigue', 'risk')->whereHas('session', fn ($q) => $q->visibleTo($user)->whereDate('date', today()))->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $now = now();
        $start = $now->copy()->minute($now->minute < 30 ? 30 : 0)->second(0)->addHours($now->minute < 30 ? 0 : 1);
        $last = AttendanceSession::where('created_by', $request->user()->id)->latest('id')->first();

        $session = new AttendanceSession([
            'attendance_type_id' => $last?->attendance_type_id ?? AttendanceType::where('is_active', true)->orderBy('sort_order')->value('id'),
            'date' => $start->copy()->startOfDay(),
            'starts_at' => $start,
            'late_after' => $last?->late_after ?? 10,
            'location' => $last?->location,
            'company' => $last?->company ?? 'PT KPP MINING',
            'department' => $last?->department ?? 'PLANT',
            'target_departments' => $last?->target_departments ?? ($request->user()->department ? [$request->user()->department] : []),
            'checkin_mode' => 'qr',
            'shift' => $start->hour >= 6 && $start->hour < 18 ? '1' : '2',
        ]);

        return view('admin.attendance.form', $this->formData($session) + ['open' => [15, 45]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $session = new AttendanceSession;
        $session->created_by = $request->user()->id;
        $this->save($request, $session);
        ActivityLog::record('absensi', "Membuat sesi {$session->type->name} {$session->starts_at->format('d/m H:i')}");

        return redirect()->route('admin.attendance.show', $session)->with('toast', 'Sesi dibuat. Tampilkan QR ini di lokasi.');
    }

    public function edit(Request $request, AttendanceSession $session): View
    {
        $this->authorizeSession($request, $session);

        return view('admin.attendance.form', $this->formData($session) + [
            'open' => [(int) $session->opens_at->diffInMinutes($session->starts_at), (int) $session->starts_at->diffInMinutes($session->closes_at)],
        ]);
    }

    public function update(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $session);
        $this->save($request, $session);

        return redirect()->route('admin.attendance.show', $session)->with('toast', 'Sesi disimpan.');
    }

    public function show(Request $request, AttendanceSession $session): View
    {
        $this->authorizeSession($request, $session);

        return view('admin.attendance.show', ['session' => $session->load('type', 'host'), 'feed' => $this->feedData($session)]);
    }

    /** Layar penuh untuk TV / proyektor di lokasi. */
    public function screen(Request $request, AttendanceSession $session): View
    {
        $this->authorizeSession($request, $session);

        return view('admin.attendance.screen', ['session' => $session->load('type', 'host'), 'feed' => $this->feedData($session)]);
    }

    public function feed(Request $request, AttendanceSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        return response()->json($this->feedData($session))->header('Cache-Control', 'no-store');
    }

    public function close(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $session);
        $reopen = $request->boolean('reopen');
        $session->closed_at = $reopen ? null : now();
        if ($reopen && $session->closes_at->isPast()) {
            $session->closes_at = now()->addMinutes(15);
        }
        $session->save();
        ActivityLog::record('absensi', ($reopen ? 'Membuka ulang' : 'Menutup').' sesi '.$session->type->name.' '.$session->starts_at->format('d/m H:i'));

        return back()->with('toast', $reopen ? 'Sesi dibuka lagi.' : 'Sesi ditutup. Karyawan tidak bisa absen lagi.');
    }

    public function destroy(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $session);
        abort_unless($request->user()->canManageContent() || $session->records()->doesntExist(), 403, 'Sesi yang sudah punya kehadiran hanya bisa dihapus Admin.');
        $label = $session->type->name.' '.$session->starts_at->format('d/m H:i');
        $session->delete();
        ActivityLog::record('absensi', "Menghapus sesi {$label}");

        return redirect()->route('admin.attendance.index')->with('toast', 'Sesi dihapus.');
    }

    /** Host mencatat kehadiran (karyawan tanpa HP, lupa scan, dll). */
    public function addRecord(Request $request, AttendanceSession $session): RedirectResponse|JsonResponse
    {
        $this->authorizeSession($request, $session);
        $data = $request->validate([
            'nrp' => ['required', 'string', 'max:30'],
            'role' => ['nullable', Rule::in(['audience', 'pemateri'])],
            'sleep_at' => ['nullable', 'date_format:H:i', 'required_with:wake_at'],
            'wake_at' => ['nullable', 'date_format:H:i', 'required_with:sleep_at'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $user = User::where('nrp', User::normalizeNrp($data['nrp']))->first();
        if (! $user) {
            $msg = 'NRP '.User::normalizeNrp($data['nrp']).' tidak terdaftar di AstoOne.';

            return $request->expectsJson() ? response()->json(['message' => $msg], 422) : back()->withErrors(['nrp' => $msg]);
        }
        $record = AttendanceController::record($session, $user, $data + ['method' => 'manual'], $request->user());
        $msg = $record->wasRecentlyCreated ? $user->displayName().' dicatat hadir.' : $user->displayName().' sudah tercatat sebelumnya.';

        return $request->expectsJson() ? response()->json(['message' => $msg]) : back()->with('toast', $msg);
    }

    public function removeRecord(Request $request, AttendanceSession $session, AttendanceRecord $record): RedirectResponse
    {
        $this->authorizeSession($request, $session);
        abort_unless($record->attendance_session_id === $session->id, 404);
        $record->delete();
        ActivityLog::record('absensi', "Menghapus kehadiran {$record->name} ({$record->nrp}) dari sesi {$session->type->name} {$session->starts_at->format('d/m')}");

        return back()->with('toast', 'Kehadiran dihapus.');
    }

    /** Cari karyawan untuk dicatat manual. */
    public function people(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(User::where('is_active', true)
            ->where(fn ($w) => $w->where('nrp', 'like', '%'.Str::upper($q).'%')->orWhere('name', 'like', '%'.Str::upper($q).'%'))
            ->orderBy('name')->limit(8)->get(['nrp', 'name', 'department'])
            ->map(fn ($u) => ['nrp' => $u->nrp, 'name' => $u->displayName(), 'department' => $u->department]));
    }

    public function export(Request $request, AttendanceSession $session): BinaryFileResponse
    {
        $this->authorizeSession($request, $session);
        $path = AttendanceExport::write($session->records()->with('session.type')->orderBy('checked_in_at')->get(), 'Absensi');

        return response()->download($path, 'absensi-'.Str::slug($session->type->name).'-'.$session->starts_at->format('Ymd-Hi').'.xlsx')->deleteFileAfterSend();
    }

    /* ------------------------------------------------------------ bantu */

    private function feedData(AttendanceSession $session): array
    {
        $session->refresh();
        $records = $session->records()->orderByDesc('checked_in_at')->get();
        $expected = $session->expectedUsers();
        $presentNrps = $records->pluck('nrp')->all();
        $missing = $expected->reject(fn ($u) => in_array($u->nrp, $presentNrps, true));

        return [
            'status' => $session->status(),
            'statusLabel' => $session->statusLabel(),
            'url' => $session->isOpen() ? $session->checkinUrl() : null,
            'token' => $session->isOpen() && $session->checkin_mode === 'qr' ? $session->token() : null,
            'next' => $session->secondsToNextToken(),
            'closesIn' => $session->isOpen() ? (int) now()->diffInSeconds($session->closes_at) : 0,
            'counts' => [
                'present' => $records->count(),
                'expected' => $expected->count(),
                'late' => $records->where('is_late', true)->count(),
                'risk' => $records->where('fatigue', 'risk')->count(),
                'coaching' => $records->where('fatigue', 'coaching')->count(),
                'speaker' => $records->where('role', 'pemateri')->count(),
            ],
            'records' => $records->map->toLive()->values()->all(),
            'missing' => $missing->map(fn ($u) => ['nrp' => $u->nrp, 'name' => $u->displayName(), 'department' => $u->department])->values()->all(),
        ];
    }

    private function save(Request $request, AttendanceSession $session): void
    {
        $data = $request->validate([
            'attendance_type_id' => ['required', Rule::exists('attendance_types', 'id')],
            'title' => ['nullable', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'shift' => ['nullable', Rule::in(['1', '2'])],
            'open_before' => ['required', 'integer', 'min:0', 'max:240'],
            'open_after' => ['required', 'integer', 'min:5', 'max:720'],
            'late_after' => ['required', 'integer', 'min:0', 'max:240'],
            'location' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'target_departments' => ['nullable', 'array'],
            'target_departments.*' => ['string', 'max:100'],
            'checkin_mode' => ['required', Rule::in(['qr', 'open'])],
            'speaker_nrp' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'attendance_type_id' => 'jenis kegiatan', 'start_time' => 'jam mulai', 'open_before' => 'dibuka sebelum', 'open_after' => 'ditutup setelah',
        ]);

        $start = Carbon::parse($data['date'].' '.$data['start_time']);
        $speaker = ! empty($data['speaker_nrp']) ? User::firstWhere('nrp', User::normalizeNrp($data['speaker_nrp'])) : null;

        $session->fill([
            'attendance_type_id' => $data['attendance_type_id'],
            'title' => $data['title'] ?? null,
            'date' => $start->copy()->startOfDay(),
            'shift' => $data['shift'] ?? null,
            'starts_at' => $start,
            'opens_at' => $start->copy()->subMinutes((int) $data['open_before']),
            'closes_at' => $start->copy()->addMinutes((int) $data['open_after']),
            'late_after' => $data['late_after'],
            'location' => $data['location'] ?? null,
            'company' => $data['company'] ?? null,
            'department' => $data['department'] ?? null,
            'target_departments' => array_values(array_filter($data['target_departments'] ?? [])) ?: null,
            'checkin_mode' => $data['checkin_mode'],
            'speaker_nrp' => $speaker?->nrp ?? (! empty($data['speaker_nrp']) ? User::normalizeNrp($data['speaker_nrp']) : null),
            'speaker_name' => $speaker?->name,
            'notes' => $data['notes'] ?? null,
        ]);
        $session->host_id ??= $request->user()->id;
        if ($session->isDirty('closes_at') && $session->closes_at->isFuture()) {
            $session->closed_at = null;
        }
        $session->save();
        $session->load('type');
    }

    private function formData(AttendanceSession $session): array
    {
        return [
            'session' => $session,
            'types' => AttendanceType::where('is_active', true)->orderBy('sort_order')->get(),
            'departments' => User::where('is_active', true)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'locations' => AttendanceSession::whereNotNull('location')->distinct()->orderBy('location')->limit(20)->pluck('location'),
        ];
    }

    private function authorizeSession(Request $request, AttendanceSession $session): void
    {
        abort_unless($session->canBeManagedBy($request->user()), 403, 'Sesi ini dikelola host lain.');
    }
}
