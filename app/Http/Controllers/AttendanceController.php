<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Absensi dari sisi karyawan: scan QR / ketik kode layar → isi peran & jam
 * tidur → tercatat. Identitas diambil dari akun, jadi tidak bisa diisi atas
 * nama orang lain.
 */
class AttendanceController extends Controller
{
    /** Bukti scan berlaku selama ini untuk menyelesaikan form (detik). */
    private const PROOF_TTL = 900;

    public function scan(Request $request): View
    {
        $user = $request->user();

        return view('portal.attendance.scan', [
            'sessions' => self::openFor($user),
        ]);
    }

    /** Ketik 6 angka yang tampil di layar sesi. */
    public function enterCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'digits:6']], ['token.digits' => 'Kode terdiri dari 6 angka.']);
        $session = AttendanceSession::openNow()->get()->first(fn (AttendanceSession $s) => $s->tokenValid($data['token']));
        if (! $session) {
            return back()->withErrors(['token' => 'Kode tidak cocok atau sudah berganti. Ketik kode yang sedang tampil di layar.'])->withInput();
        }
        $this->grantProof($request, $session, 'kode');

        return redirect()->route('attendance.checkin', $session->code);
    }

    public function show(Request $request, string $code): View|RedirectResponse
    {
        $session = AttendanceSession::with('type', 'host')->where('code', $code)->firstOrFail();
        $user = $request->user();

        // Belum masuk: simpan bukti scan dulu (tetap berlaku setelah login / aktivasi), lalu ke halaman masuk
        if (! $user) {
            if ($session->checkin_mode === 'qr' && $session->tokenValid($request->query('t'))) {
                $this->grantProof($request, $session, 'qr');
            }

            return redirect()->guest(route('login'))->with('toast', 'Masuk dulu dengan NRP untuk absen '.$session->type->name.'.');
        }
        $record = $session->records()->where('nrp', $user->nrp)->first();

        if ($record) {
            return view('portal.attendance.checkin', ['session' => $session, 'state' => 'done', 'record' => $record]);
        }
        if (! $session->isOpen()) {
            return view('portal.attendance.checkin', ['session' => $session, 'state' => $session->status()]);
        }
        if ($session->checkin_mode === 'qr') {
            if ($session->tokenValid($request->query('t'))) {
                $this->grantProof($request, $session, 'qr');
            }
            if (! $this->hasProof($request, $session)) {
                return view('portal.attendance.checkin', ['session' => $session, 'state' => 'proof']);
            }
        }

        $last = AttendanceRecord::where('nrp', $user->nrp)->whereNotNull('sleep_at')->latest('checked_in_at')->first();

        return view('portal.attendance.checkin', [
            'session' => $session,
            'state' => 'form',
            'user' => $user,
            'targeted' => $session->targets($user),
            'lastSleep' => $last ? [substr($last->sleep_at, 0, 5), substr($last->wake_at, 0, 5)] : null,
        ]);
    }

    public function store(Request $request, string $code): RedirectResponse
    {
        $session = AttendanceSession::with('type')->where('code', $code)->firstOrFail();
        $user = $request->user();

        if ($session->records()->where('nrp', $user->nrp)->exists()) {
            return redirect()->route('attendance.checkin', $code);
        }
        if (! $session->isOpen()) {
            return redirect()->route('attendance.checkin', $code)->with('toast', 'Sesi sudah ditutup.');
        }
        $method = 'open';
        if ($session->checkin_mode === 'qr') {
            if (! $this->hasProof($request, $session)) {
                return redirect()->route('attendance.checkin', $code)->withErrors(['token' => 'Scan ulang QR di layar sesi.']);
            }
            $method = $request->session()->get("attendance.proof.{$session->id}.method", 'qr');
        }

        $type = $session->type;
        $data = $request->validate([
            'role' => [$type->allow_speaker ? 'required' : 'nullable', Rule::in(['audience', 'pemateri'])],
            'sleep_at' => [$type->track_fatigue ? 'required' : 'nullable', 'date_format:H:i'],
            'wake_at' => [$type->track_fatigue ? 'required' : 'nullable', 'date_format:H:i'],
        ], [
            'role.required' => 'Pilih peran Anda.',
            'sleep_at.required' => 'Isi jam mulai tidur.',
            'wake_at.required' => 'Isi jam bangun.',
        ]);

        $record = self::record($session, $user, $data + ['method' => $method]);
        $request->session()->forget("attendance.proof.{$session->id}");

        return redirect()->route('attendance.checkin', $code)->with('justCheckedIn', $record->id);
    }

    public function history(Request $request): View
    {
        $user = $request->user();
        $records = AttendanceRecord::with('session.type')->where('nrp', $user->nrp)->latest('checked_in_at')->paginate(20);
        $month = AttendanceRecord::where('nrp', $user->nrp)->where('checked_in_at', '>=', now()->startOfMonth());

        return view('portal.attendance.history', [
            'records' => $records,
            'stats' => [
                'month' => (clone $month)->count(),
                'late' => (clone $month)->where('is_late', true)->count(),
                'speaker' => (clone $month)->where('role', 'pemateri')->count(),
                'avgSleep' => (int) round((float) (clone $month)->whereNotNull('sleep_minutes')->avg('sleep_minutes')),
            ],
        ]);
    }

    /* ----------------------------------------------------------- bantu */

    /** Sesi yang sedang dibuka & relevan untuk karyawan ini, plus status sudah/belum absen. */
    public static function openFor(User $user)
    {
        $done = AttendanceRecord::where('nrp', $user->nrp)->where('checked_in_at', '>=', now()->subDay())->pluck('attendance_session_id')->all();

        return AttendanceSession::openNow()->with('type')->orderBy('starts_at')->get()
            ->filter(fn (AttendanceSession $s) => $s->targets($user) || in_array($s->id, $done, true))
            ->map(fn (AttendanceSession $s) => tap($s, fn ($s) => $s->setAttribute('already', in_array($s->id, $done, true))))
            ->values();
    }

    /** Catat kehadiran (dipakai karyawan & host). */
    public static function record(AttendanceSession $session, User $user, array $data, ?User $recorder = null): AttendanceRecord
    {
        $sleep = AttendanceRecord::classifySleep($data['sleep_at'] ?? null, $data['wake_at'] ?? null);
        $now = now();

        return DB::transaction(fn () => AttendanceRecord::firstOrCreate(
            ['attendance_session_id' => $session->id, 'nrp' => $user->nrp],
            [
                'user_id' => $user->id,
                'name' => $user->name,
                'position' => $user->position,
                'department' => $user->department,
                'company' => $session->company,
                'role' => $data['role'] ?? 'audience',
                'sleep_at' => $data['sleep_at'] ?? null,
                'wake_at' => $data['wake_at'] ?? null,
                'sleep_minutes' => $sleep[0] ?? null,
                'fatigue' => $sleep[1] ?? null,
                'checked_in_at' => $now,
                'is_late' => $session->isLateAt($now),
                'method' => $data['method'] ?? 'manual',
                'recorded_by' => $recorder?->id,
                'note' => $data['note'] ?? null,
            ],
        ));
    }

    private function grantProof(Request $request, AttendanceSession $session, string $method): void
    {
        $request->session()->put("attendance.proof.{$session->id}", ['at' => now()->timestamp, 'method' => $method]);
    }

    private function hasProof(Request $request, AttendanceSession $session): bool
    {
        $at = $request->session()->get("attendance.proof.{$session->id}.at");

        return $at && now()->timestamp - $at <= self::PROOF_TTL;
    }
}
