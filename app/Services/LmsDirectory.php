<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Membaca data LMS MekanikPintar dan database sertifikat lewat koneksi
 * read-only (`lms`, `sertifikat`). Bila tidak diatur atau gagal, semua
 * metode mengembalikan null / daftar kosong — portal tetap jalan.
 */
class LmsDirectory
{
    private const TTL = 600;

    public function enabled(): bool
    {
        return (bool) config('database.connections.lms.database') && (bool) config('database.connections.lms.username');
    }

    public function certificatesEnabled(): bool
    {
        return (bool) config('database.connections.sertifikat.database') && (bool) config('database.connections.sertifikat.username');
    }

    /**
     * Ringkasan LMS untuk satu NRP.
     *
     * @return array{found:bool, name:?string, grade:?string, points:int, achievements:list<string>, trainings:list<array>, training_count:int, certificates:list<array>, certificate_count:int}|null
     */
    public function summary(string $nrp): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        return Cache::remember('lms:summary:'.$nrp, self::TTL, function () use ($nrp) {
            try {
                $user = DB::connection('lms')->table('users')->where('nrp', $nrp)
                    ->first(['id', 'name', 'nrp', 'grade', 'points']);
                if (! $user) {
                    return ['found' => false];
                }

                $trainings = $this->trainings((int) $user->id);
                $certificates = $this->certificates($nrp);

                return [
                    'found' => true,
                    'name' => $user->name ?? null,
                    'grade' => $user->grade ?? null,
                    'points' => (int) ($user->points ?? 0),
                    'achievements' => $this->achievements((int) $user->id),
                    'trainings' => array_slice($trainings, 0, 10),
                    'training_count' => count($trainings),
                    'certificates' => array_slice($certificates, 0, 10),
                    'certificate_count' => count($certificates),
                ];
            } catch (Throwable $e) {
                Log::warning('LMS tidak bisa dibaca: '.$e->getMessage());

                return null;
            }
        }) ?: null;
    }

    /** Training yang sudah diikuti: hasil LMS + riwayat yang diimpor. */
    private function trainings(int $userId): array
    {
        $db = DB::connection('lms');

        $attended = $db->table('training_registrations as r')
            ->join('trainings as t', 't.id', '=', 'r.training_id')
            ->leftJoin('training_names as n', 'n.id', '=', 't.training_name_id')
            ->where('r.user_id', $userId)
            ->where('r.status', 'attended')
            ->get(['t.title', 'n.name as catalog', 't.start_date', 'r.is_passed'])
            ->map(fn ($r) => [
                'name' => $r->catalog ?: $r->title ?: 'Training',
                'date' => $r->start_date ? Carbon::parse($r->start_date)->toDateString() : null,
                'passed' => $r->is_passed === null ? null : (bool) $r->is_passed,
                'source' => 'lms',
            ]);

        $history = $db->table('user_historical_trainings as h')
            ->join('training_names as n', 'n.id', '=', 'h.training_name_id')
            ->where('h.user_id', $userId)
            ->get(['n.name', 'h.training_date'])
            ->map(fn ($r) => [
                'name' => $r->name,
                'date' => $r->training_date ? Carbon::parse($r->training_date)->toDateString() : null,
                'passed' => true,
                'source' => 'riwayat',
            ]);

        return $attended->concat($history)
            ->unique(fn ($t) => mb_strtolower($t['name']).'|'.$t['date'])
            ->sortByDesc('date')->values()->all();
    }

    private function achievements(int $userId): array
    {
        try {
            return DB::connection('lms')->table('achievement_user as au')
                ->join('achievements as a', 'a.id', '=', 'au.achievement_id')
                ->where('au.user_id', $userId)->orderBy('a.name')->pluck('a.name')->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function certificates(string $nrp): array
    {
        if (! $this->certificatesEnabled()) {
            return [];
        }
        try {
            return DB::connection('sertifikat')->table('sertifikat')
                ->where('nrp', $nrp)->where('is_valid', true)
                ->orderByDesc('issued_at')
                ->get(['no_seri', 'nama_training', 'issued_at'])
                ->map(fn ($c) => [
                    'number' => $c->no_seri,
                    'name' => $c->nama_training,
                    'date' => $c->issued_at ? Carbon::parse($c->issued_at)->toDateString() : null,
                ])->all();
        } catch (Throwable $e) {
            Log::warning('Database sertifikat tidak bisa dibaca: '.$e->getMessage());

            return [];
        }
    }
}
