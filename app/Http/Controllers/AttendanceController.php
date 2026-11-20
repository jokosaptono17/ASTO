<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Absensi dari sisi karyawan: scan QR tempel (satu QR untuk semua absensi) →
 * cek posisi GPS → pilih kegiatan → isi peran & jam tidur → tercatat.
 *
 * Pengaman titip absen: identitas dari akun, posisi harus di area yang sah,
 * satu HP hanya untuk satu NRP per sesi, dan (mode qr) token layar berganti.
 */
class AttendanceController extends Controller
{
    /** Bukti scan berlaku selama ini untuk menyelesaikan form (detik). */
    private const PROOF_TTL = 900;

    public const DEVICE_COOKIE = 'astoone_device';

    public function scan(Request $request): View
    {
        $this->deviceId($request);

        $user = $request->user();
        $sessions = self::openFor($user);

        return view('portal.attendance.scan', [
            'sessions' => $sessions,
            'next' => AttendanceSession::with('type')->whereNull('closed_at')->where('opens_at', '>', now())
                ->where('opens_at', '<', now()->addDay())->orderBy('opens_at')->get()->first(fn ($s) => $s->targets($user)),
            'mapped' => AttendanceLocation::mapped()->exists(),
        ]);
    }

    /** Posisi karyawan terhadap area absensi + kegiatan yang bisa dipilih di posisi itu. */
    public function locate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'acc' => ['nullable', 'numeric', 'min:0'],
            'code' => ['nullable', 'string', 'max:12'],
        ]);
        [$lat, $lng, $acc] = [isset($data['lat']) ? (float) $data['lat'] : null, isset($data['lng']) ? (float) $data['lng'] : null, isset($data['acc']) ? (float) $data['acc'] : null];

        if (! empty($data['code'])) {
            $session = AttendanceSession::where('code', $data['code'])->firstOrFail();
            $geo = $session->requiresGps() ? AttendanceLocation::locate($lat, $lng, $acc, $session->location_ids) : self::noGeo();

            return response()->json(self::geoPayload($geo));
        }

        $sessions = self::openFor($request->user())->map(function (AttendanceSession $s) use ($lat, $lng, $acc) {
            $geo = $s->requiresGps() ? AttendanceLocation::locate($lat, $lng, $acc, $s->location_ids) : self::noGeo();

            return ['code' => $s->code] + self::geoPayload($geo);
        });

        return response()->json(self::geoPayload(AttendanceLocation::locate($lat, $lng, $acc)) + ['sessions' => $sessions->values()]);
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
        $this->deviceId($request);

        return view('portal.attendance.checkin', [
            'session' => $session,
            'state' => 'form',
            'user' => $user,
            'targeted' => $session->targets($user),
            'checkGps' => $session->requiresGps() && $session->allowedLocations()->isNotEmpty(),
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
        $method = $session->checkin_mode === 'gps' ? 'gps' : 'open';
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
        $pos = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'acc' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Posisi GPS: di luar area → ditolak; tidak pasti → boleh, ditandai untuk diperiksa host
        $geo = self::noGeo();
        if ($session->requiresGps()) {
            $geo = AttendanceLocation::locate(
                isset($pos['lat']) ? (float) $pos['lat'] : null,
                isset($pos['lng']) ? (float) $pos['lng'] : null,
                isset($pos['acc']) ? (float) $pos['acc'] : null,
                $session->location_ids,
            );
            if ($geo['status'] === 'outside') {
                return back()->withInput()->withErrors(['geo' => self::geoPayload($geo)['message'].' Absen hanya bisa dari area kegiatan.']);
            }
        }

        // Satu HP hanya untuk satu NRP di sesi yang sama
        $device = $this->deviceId($request);
        $other = $session->records()->where('device_id', $device)->where('nrp', '!=', $user->nrp)->first();
        if ($other) {
            return back()->withInput()->withErrors(['device' => 'HP ini sudah dipakai absen oleh '.Str::title(Str::lower($other->name)).' di sesi ini. Setiap karyawan absen dari HP masing-masing.']);
        }

        $record = self::record($session, $user, $data + ['method' => $method, 'device_id' => $device] + self::geoRecord($geo, $pos));
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
        AttendanceSchedule::ensureSessions();
        $done = AttendanceRecord::where('nrp', $user->nrp)->where('checked_in_at', '>=', now()->subDay())->pluck('attendance_session_id')->all();

        return AttendanceSession::openNow()->with('type')->orderBy('starts_at')->get()
            ->filter(fn (AttendanceSession $s) => $s->targets($user) || in_array($s->id, $done, true))
            ->map(fn (AttendanceSession $s) => tap($s, fn ($s) => $s->setAttribute('already', in_array($s->id, $done, true))))
            ->values();
    }

    /**
     * Ringkasan absensi untuk kartu di beranda: sesi yang dibuka, linimasa hari ini,
     * sesi berikutnya, dan statistik bulan ini.
     */
    public static function todayFor(User $user): array
    {
        $open = self::openFor($user);
        $mine = AttendanceRecord::with('location')->where('nrp', $user->nrp)->where('checked_in_at', '>=', today()->subDay())->get()->keyBy('attendance_session_id');

        $timeline = AttendanceSession::with('type')->whereNull('closed_at')
            ->where(fn ($q) => $q->whereDate('date', today())->orWhere(fn ($w) => $w->where('opens_at', '<=', now())->where('closes_at', '>=', now())))
            ->orderBy('opens_at')->get()
            ->filter(fn (AttendanceSession $s) => $s->targets($user) || $mine->has($s->id))
            ->map(fn (AttendanceSession $s) => [
                'name' => $s->type->name.($s->title ? ' '.$s->title : ''),
                'short' => $s->isRoutine() ? trim($s->type->name.' '.$s->title) : $s->type->name,
                'color' => $s->type->color,
                'time' => $s->opens_at->format('H:i'),
                'state' => $mine->has($s->id) ? 'done' : ($s->status() === 'selesai' ? 'missed' : ($s->isOpen() ? 'open' : 'later')),
                'at' => $mine->get($s->id)?->checked_in_at->format('H:i'),
            ])->values();

        $next = AttendanceSession::with('type')->whereNull('closed_at')->where('opens_at', '>', now())->where('opens_at', '<', now()->addDay())
            ->orderBy('opens_at')->get()->first(fn ($s) => $s->targets($user));

        $month = AttendanceRecord::where('nrp', $user->nrp)->where('checked_in_at', '>=', now()->startOfMonth());
        $days = AttendanceRecord::where('nrp', $user->nrp)->where('checked_in_at', '>=', today()->subDays(60))->pluck('checked_in_at')
            ->map(fn ($d) => $d->toDateString())->unique()->flip();
        $streak = 0;
        $day = $days->has(today()->toDateString()) ? today() : today()->subDay();
        while ($days->has($day->toDateString())) {
            $streak++;
            $day = $day->copy()->subDay();
        }

        return [
            'open' => $open,
            'pending' => $open->reject(fn ($s) => $s->already)->values(),
            'done' => $open->filter(fn ($s) => $s->already)->map(fn ($s) => tap($s, fn ($s) => $s->setRelation('myRecord', $mine->get($s->id))))->values(),
            'timeline' => $timeline,
            'next' => $next,
            'last' => AttendanceRecord::with('session.type', 'location')->where('nrp', $user->nrp)->latest('checked_in_at')->first(),
            'stats' => [
                'month' => (clone $month)->count(),
                'avgSleep' => (int) round((float) (clone $month)->whereNotNull('sleep_minutes')->avg('sleep_minutes')),
                'streak' => $streak,
            ],
        ];
    }

    /** Catat kehadiran (dipakai karyawan & host). */
    public static function record(AttendanceSession $session, User $user, array $data, ?User $recorder = null): AttendanceRecord
    {
        $sleep = AttendanceRecord::classifySleep($data['sleep_at'] ?? null, $data['wake_at'] ?? null);
        $now = now();

        $record = DB::transaction(fn () => AttendanceRecord::firstOrCreate(
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
                'geo_status' => $data['geo_status'] ?? 'none',
                'attendance_location_id' => $data['attendance_location_id'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'accuracy' => $data['accuracy'] ?? null,
                'distance_m' => $data['distance_m'] ?? null,
                'device_id' => $data['device_id'] ?? null,
            ],
        ));

        // Poin HEBAT otomatis (hanya untuk kehadiran baru)
        if ($record->wasRecentlyCreated) {
            app(\App\Services\Points\PointService::class)->awardAttendance($record);
        }

        return $record;
    }

    /** @return array{status: string, location: null, distance: null, accuracy: null} */
    private static function noGeo(): array
    {
        return ['status' => 'none', 'location' => null, 'distance' => null, 'accuracy' => null];
    }

    /** Ringkasan posisi untuk tampilan (JSON & pesan galat). */
    public static function geoPayload(array $geo): array
    {
        $name = $geo['location']?->name;
        $message = match ($geo['status']) {
            'inside' => 'Anda berada di area '.$name.'.',
            'outside' => 'Anda berada '.self::meters($geo['distance']).' di luar area '.$name.'.',
            'unverified' => $geo['distance'] === null
                ? 'GPS belum aktif. Anda tetap bisa absen, tetapi lokasi ditandai untuk diperiksa GL.'
                : 'Posisi belum pasti (akurasi ±'.$geo['accuracy'].' m). Anda tetap bisa absen, tetapi lokasi ditandai untuk diperiksa GL.',
            default => '',
        };

        return ['status' => $geo['status'], 'location' => $name, 'distance' => $geo['distance'], 'accuracy' => $geo['accuracy'], 'message' => $message];
    }

    private static function meters(?int $m): string
    {
        return $m === null ? '' : ($m >= 1000 ? number_format($m / 1000, 1, ',', '.').' km' : $m.' m');
    }

    private static function geoRecord(array $geo, array $pos): array
    {
        return [
            'geo_status' => $geo['status'],
            'attendance_location_id' => $geo['location']?->id,
            'latitude' => isset($pos['lat']) ? round((float) $pos['lat'], 7) : null,
            'longitude' => isset($pos['lng']) ? round((float) $pos['lng'], 7) : null,
            'accuracy' => $geo['accuracy'] !== null ? min(65000, $geo['accuracy']) : null,
            'distance_m' => $geo['distance'],
        ];
    }

    /** ID acak per HP/browser (cookie 5 tahun) untuk mencegah satu HP absen untuk banyak NRP. */
    private function deviceId(Request $request): string
    {
        $id = (string) $request->cookie(self::DEVICE_COOKIE);
        if (! preg_match('/^[a-f0-9-]{36}$/', $id)) {
            $id = (string) Str::uuid();
        }
        Cookie::queue(Cookie::make(self::DEVICE_COOKIE, $id, 60 * 24 * 365 * 5, null, null, null, true, false, 'lax'));

        return $id;
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
