<?php

namespace App\Services\Mentari;

use App\Http\Controllers\AttendanceController;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Mentari soal absensi: "absensi saya", "sudah absen?", dan untuk host
 * "siapa yang belum absen".
 */
class AttendanceDesk
{
    public function answer(string $q, User $viewer): ?array
    {
        if (! preg_match('/\b(absen|absensi|kehadiran|hadir|p5m|safety talk)\b/u', $q)) {
            return null;
        }

        if ($viewer->canHostAttendance() && preg_match('/\b(belum|tidak) (absen|hadir)|siapa.*(belum|tidak)/u', $q)) {
            return $this->missing($viewer);
        }

        if (preg_match('/\b(riwayat|rekap|saya|aku|ku)\b/u', $q) && ! preg_match('/\bcara\b/u', $q)) {
            return $this->mine($viewer);
        }

        // "ada p5m?", "absen sekarang", dll → sesi yang sedang dibuka
        $open = AttendanceController::openFor($viewer);
        if ($open->isEmpty()) {
            return $this->reply('Belum ada sesi absensi yang sedang dibuka untuk Anda. Saat P5M atau safety talk dimulai, scan QR di layar lokasi.', [], [
                ['label' => 'Buka pemindai', 'href' => route('attendance.scan')],
            ]);
        }

        return $this->reply('Sesi yang sedang dibuka:', [[
            'type' => 'list', 'title' => null,
            'items' => $open->map(fn ($s) => [
                'title' => $s->type->name.($s->title ? ' — '.$s->title : ''),
                'subtitle' => $s->starts_at->format('H:i').($s->location ? ' · '.$s->location : '').' · tutup '.$s->closes_at->format('H:i'),
                'meta' => $s->already ? 'Sudah ✓' : null,
            ])->all(),
        ]], [['label' => 'Scan QR absen', 'href' => route('attendance.scan')]]);
    }

    private function mine(User $viewer): array
    {
        $base = AttendanceRecord::where('nrp', $viewer->nrp);
        $month = (clone $base)->where('checked_in_at', '>=', now()->startOfMonth());
        $last = (clone $base)->with('session.type')->latest('checked_in_at')->limit(5)->get();
        if ($last->isEmpty()) {
            return $this->reply('Anda belum punya catatan absensi. Scan QR di layar P5M atau safety talk untuk absen.', [], [['label' => 'Buka pemindai', 'href' => route('attendance.scan')]]);
        }

        return $this->reply('Ringkasan absensi Anda bulan ini:', [
            ['type' => 'stats', 'title' => now()->locale('id')->translatedFormat('F Y'), 'items' => [
                ['label' => 'Hadir', 'value' => (clone $month)->count()],
                ['label' => 'Terlambat', 'value' => (clone $month)->where('is_late', true)->count()],
                ['label' => 'Pemateri', 'value' => (clone $month)->where('role', 'pemateri')->count()],
            ]],
            ['type' => 'list', 'title' => 'Terakhir', 'items' => $last->map(fn ($r) => [
                'title' => $r->session->type->name.($r->session->title ? ' — '.$r->session->title : ''),
                'subtitle' => $r->checked_in_at->locale('id')->translatedFormat('j M, H:i').($r->fatigue ? ' · '.AttendanceRecord::FATIGUE[$r->fatigue]['label'] : ''),
                'meta' => $r->is_late ? 'Telat' : null,
                'tone' => $r->fatigue === 'risk' ? 'alarm' : ($r->is_late ? 'warn' : null),
            ])->all()],
        ], [['label' => 'Riwayat lengkap', 'href' => route('attendance.history')]]);
    }

    private function missing(User $viewer): array
    {
        $session = AttendanceSession::visibleTo($viewer)->with('type')
            ->whereNotNull('target_departments')
            ->where('opens_at', '<=', now())->where('date', '>=', today()->subDay())
            ->orderByDesc('starts_at')->first();
        if (! $session) {
            return $this->reply('Tidak ada sesi hari ini yang punya daftar peserta sasaran. Pilih seksi peserta saat membuat sesi agar saya bisa menghitung siapa yang belum hadir.');
        }
        $present = $session->records()->pluck('nrp')->all();
        $missing = $session->expectedUsers()->reject(fn ($u) => in_array($u->nrp, $present, true));
        $title = $session->type->name.' '.$session->starts_at->format('H:i');

        if ($missing->isEmpty()) {
            return $this->reply("Semua peserta {$title} sudah hadir 🎉", [], [['label' => 'Buka sesi', 'href' => route('admin.attendance.show', $session)]]);
        }

        return $this->reply("Belum hadir di {$title}: {$missing->count()} dari ".($missing->count() + count($present)).' orang.', [[
            'type' => 'list', 'title' => null,
            'items' => $missing->take(15)->map(fn ($u) => ['title' => $u->displayName(), 'subtitle' => $u->nrp.' · '.$u->department, 'ask' => $u->nrp])->values()->all(),
        ]], [['label' => 'Buka daftar hadir', 'href' => route('admin.attendance.show', $session)]]);
    }

    private function reply(string $text, array $cards = [], array $actions = []): array
    {
        return ['reply' => $text, 'cards' => $cards, 'actions' => $actions, 'intent' => 'attendance'];
    }
}
