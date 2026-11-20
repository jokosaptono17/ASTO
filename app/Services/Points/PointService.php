<?php

namespace App\Services\Points;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\PointGrant;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Semua perubahan saldo Poin HEBAT lewat sini: baris user dikunci, saldo tidak
 * pernah minus, dan kunci idempoten mencegah poin yang sama tercatat dua kali.
 */
class PointService
{
    /** Level berdasarkan total poin yang pernah diperoleh. */
    public const LEVELS = [
        ['name' => 'Rookie', 'min' => 0, 'color' => '#9fb5aa'],
        ['name' => 'Skilled', 'min' => 250, 'color' => '#3dff8e'],
        ['name' => 'Expert', 'min' => 1000, 'color' => '#5ab8ff'],
        ['name' => 'Master', 'min' => 3000, 'color' => '#ffc23d'],
        ['name' => 'Legend', 'min' => 7500, 'color' => '#ff7ad9'],
    ];

    /**
     * Tambah / kurangi poin. Mengembalikan null bila kunci idempoten sudah pernah dipakai.
     *
     * @throws PointException bila saldo tidak cukup
     */
    public function post(User $user, int $amount, string $kind, string $description, ?Model $source = null, ?string $rule = null, ?string $idemKey = null, ?User $by = null): ?PointTransaction
    {
        if ($amount === 0) {
            return null;
        }
        if ($idemKey && PointTransaction::where('idem_key', $idemKey)->exists()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($user, $amount, $kind, $description, $source, $rule, $idemKey, $by) {
                $locked = User::whereKey($user->id)->lockForUpdate()->first(['id', 'points_balance', 'points_earned']);
                $balance = $locked->points_balance + $amount;
                if ($balance < 0 && $kind === 'redeem') {
                    throw new PointException('Poin Anda tidak cukup.');
                }
                $balance = max(0, $balance);   // penyesuaian/pencabutan tidak membuat saldo minus
                $applied = $balance - $locked->points_balance;
                if ($applied === 0) {
                    return null;
                }

                $earned = in_array($kind, ['earn', 'grant'], true) ? max(0, $applied) : ($kind === 'adjust' && $applied > 0 ? $applied : 0);
                DB::table('users')->where('id', $user->id)->update([
                    'points_balance' => $balance,
                    'points_earned' => DB::raw('points_earned + '.(int) $earned),
                ]);
                $user->points_balance = $balance;
                $user->points_earned = $locked->points_earned + $earned;

                return PointTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $applied,
                    'balance_after' => $balance,
                    'kind' => $kind,
                    'rule' => $rule,
                    'description' => Str::limit($description, 197),
                    'source_type' => $source?->getMorphClass(),
                    'source_id' => $source?->getKey(),
                    'idem_key' => $idemKey,
                    'created_by' => $by?->id,
                ]);
            });
        } catch (QueryException $e) {
            // Kunci idempoten bentrok (permintaan bersamaan) → anggap sudah tercatat
            if ($idemKey && str_contains(strtolower($e->getMessage()), 'unique')) {
                return null;
            }
            throw $e;
        }
    }

    /* ------------------------------------------------------------- absensi */

    /** Poin otomatis saat karyawan tercatat hadir. Tidak bergantung pada jam tidur (agar jujur). */
    public function awardAttendance(AttendanceRecord $record): void
    {
        $user = $record->user_id ? User::find($record->user_id) : null;
        if (! $user) {
            return;
        }
        $session = $record->session()->with('type')->first();
        $label = $session ? $session->displayTitle() : 'kegiatan';

        if ($p = PointRule::value('attend')) {
            $this->post($user, $p, 'earn', "Hadir {$label}", $record, 'attend', "att:{$record->id}");
        }
        if ($record->role === 'pemateri' && ($p = PointRule::value('speaker'))) {
            $this->post($user, $p, 'earn', "Pemateri {$label}", $record, 'speaker', "spk:{$record->id}");
        }

        // Bonus beruntun: tiap kelipatan 7 hari berturut-turut
        if ($p = PointRule::value('streak7')) {
            $streak = $this->streak($user);
            if ($streak > 0 && $streak % 7 === 0) {
                $this->post($user, $p, 'earn', "Hadir {$streak} hari beruntun", $record, 'streak7', 'streak:'.$user->id.':'.today()->toDateString());
            }
        }
    }

    /** Kehadiran dihapus host → poin yang berasal darinya dicabut. */
    public function revokeAttendance(AttendanceRecord $record, ?User $by = null): void
    {
        PointTransaction::where('source_type', $record->getMorphClass())->where('source_id', $record->id)
            ->where('amount', '>', 0)->get()
            ->each(fn (PointTransaction $tx) => $this->post(
                $tx->user, -$tx->amount, 'adjust', 'Dicabut: '.$tx->description.' (kehadiran dihapus host)', $record, $tx->rule, "revoke:{$tx->id}", $by,
            ));
    }

    public function streak(User $user): int
    {
        $days = AttendanceRecord::where('nrp', $user->nrp)->where('checked_in_at', '>=', today()->subDays(400))
            ->pluck('checked_in_at')->map(fn ($d) => $d->toDateString())->unique()->flip();
        $n = 0;
        $day = $days->has(today()->toDateString()) ? today() : today()->subDay();
        while ($days->has($day->toDateString())) {
            $n++;
            $day = $day->copy()->subDay();
        }

        return $n;
    }

    /* ------------------------------------------------------------ penukaran */

    /** @throws PointException */
    public function redeem(User $user, Reward $reward): Redemption
    {
        return DB::transaction(function () use ($user, $reward) {
            $reward = Reward::whereKey($reward->id)->lockForUpdate()->first();
            if (! $reward || ! $reward->is_active) {
                throw new PointException('Hadiah ini sudah tidak tersedia.');
            }
            if (! $reward->inStock()) {
                throw new PointException('Stok hadiah habis.');
            }
            if ($reward->per_user_limit && $reward->redeemedBy($user) >= $reward->per_user_limit) {
                throw new PointException("Batas penukaran hadiah ini {$reward->per_user_limit}× per karyawan.");
            }
            $balance = (int) User::whereKey($user->id)->lockForUpdate()->value('points_balance');
            if ($balance < $reward->cost) {
                throw new PointException('Poin Anda kurang '.number_format($reward->cost - $balance, 0, ',', '.').' lagi.');
            }

            $redemption = new Redemption;
            $redemption->code = $this->newCode();
            $redemption->user_id = $user->id;
            $redemption->reward_id = $reward->id;
            $redemption->cost = $reward->cost;
            $redemption->save();
            if ($reward->stock !== null) {
                $reward->decrement('stock');
            }
            $this->post($user, -$reward->cost, 'redeem', "Tukar {$reward->name}", $redemption, null, "redeem:{$redemption->id}");

            return $redemption;
        });
    }

    public function fulfill(Redemption $redemption, User $by): void
    {
        if ($redemption->status !== 'pending') {
            throw new PointException('Kupon ini '.mb_strtolower(Redemption::STATUSES[$redemption->status]['label']).'.');
        }
        $redemption->forceFill(['status' => 'fulfilled', 'fulfilled_at' => now(), 'handled_by' => $by->id])->save();
        ActivityLog::record('poin', "Menyerahkan {$redemption->reward->name} ke {$redemption->user->displayName()} (kupon {$redemption->code})");
    }

    /** Batalkan kupon: poin dikembalikan, stok ditambah lagi. */
    public function cancel(Redemption $redemption, User $by, string $reason): void
    {
        if ($redemption->status !== 'pending') {
            throw new PointException('Hanya kupon yang belum diambil yang bisa dibatalkan.');
        }
        DB::transaction(function () use ($redemption, $by, $reason) {
            $redemption->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'handled_by' => $by->id, 'cancel_reason' => $reason])->save();
            $reward = $redemption->reward;
            if ($reward->stock !== null) {
                $reward->increment('stock');
            }
            $this->post($redemption->user, $redemption->cost, 'refund', "Pengembalian: {$reward->name} dibatalkan", $redemption, null, "refund:{$redemption->id}", $by);
        });
        ActivityLog::record('poin', "Membatalkan kupon {$redemption->code} ({$reason})");
    }

    /* --------------------------------------------------- pemberian poin event */

    public function approveGrant(PointGrant $grant, User $by, ?string $note = null): void
    {
        if ($grant->status !== 'pending') {
            throw new PointException('Pemberian poin ini tidak sedang menunggu persetujuan.');
        }
        DB::transaction(function () use ($grant, $by, $note) {
            $grant->forceFill(['status' => 'approved', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            $grant->recipients()->with('user')->get()->each(fn ($r) => $this->post(
                $r->user, $r->points, 'grant', $grant->title.($r->note ? ' — '.$r->note : ''), $grant, null, "grant:{$grant->id}:{$r->user_id}", $by,
            ));
        });
        ActivityLog::record('poin', "Menyetujui pemberian poin \"{$grant->title}\" ({$grant->total_points} poin, {$grant->recipients()->count()} karyawan)");
    }

    /* ------------------------------------------------------------- tampilan */

    /** @return array{current: array, next: ?array, progress: int} */
    public static function level(User $user): array
    {
        $earned = (int) $user->points_earned;
        $current = self::LEVELS[0];
        $next = null;
        foreach (self::LEVELS as $i => $lv) {
            if ($earned >= $lv['min']) {
                $current = $lv;
                $next = self::LEVELS[$i + 1] ?? null;
            }
        }
        $progress = $next ? (int) round(($earned - $current['min']) / max(1, $next['min'] - $current['min']) * 100) : 100;

        return ['current' => $current, 'next' => $next, 'progress' => $progress, 'earned' => $earned];
    }

    private function newCode(): string
    {
        do {
            $code = 'HB'.Str::upper(Str::random(6));
            $code = strtr($code, ['0' => 'X', 'O' => 'Y', '1' => 'Z', 'I' => 'W', 'L' => 'V']);
        } while (Redemption::where('code', $code)->exists());

        return $code;
    }
}
