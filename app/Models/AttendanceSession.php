<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Satu sesi absensi (P5M, safety talk, …).
 *
 * Mode absen:
 * - gps  : QR tempel / tombol di aplikasi + posisi GPS harus di area yang sah (default, dipakai jadwal rutin)
 * - qr   : + token 6 angka di layar sesi yang berganti tiap 30 detik (paling ketat, untuk event)
 * - open : tanpa cek lokasi
 */
class AttendanceSession extends Model
{
    public const TOKEN_PERIOD = 30;      // detik per token

    public const TOKEN_GRACE = 3;        // token lama yang masih diterima (≈ 90 detik)

    protected $fillable = [
        'attendance_type_id', 'attendance_schedule_id', 'location_ids', 'title', 'date', 'shift', 'starts_at', 'opens_at', 'closes_at', 'late_after',
        'location', 'company', 'department', 'target_departments', 'checkin_mode', 'host_id',
        'speaker_nrp', 'speaker_name', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starts_at' => 'datetime',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
            'target_departments' => 'array',
            'location_ids' => 'array',
            'late_after' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->secret ??= Str::random(48);
            do {
                $s->code = Str::upper(Str::random(8));
            } while (static::where('code', $s->code)->exists());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /* ---------------------------------------------------------- status */

    public function status(): string
    {
        $now = now();

        return match (true) {
            $this->closed_at !== null || $now->gt($this->closes_at) => 'selesai',
            $now->lt($this->opens_at) => 'terjadwal',
            default => 'dibuka',
        };
    }

    public function isOpen(): bool
    {
        return $this->status() === 'dibuka';
    }

    public function statusLabel(): string
    {
        return ['terjadwal' => 'Terjadwal', 'dibuka' => 'Berlangsung', 'selesai' => 'Selesai'][$this->status()];
    }

    public function scopeOpenNow(Builder $q): Builder
    {
        return $q->whereNull('closed_at')->where('opens_at', '<=', now())->where('closes_at', '>=', now());
    }

    /** Admin & Admin SHE melihat semua sesi; GL: sesi rutin + sesi yang ia buat/pimpin. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->canManageContent() ? $q : $q->where(fn ($w) => $w->whereNotNull('attendance_schedule_id')->orWhere('host_id', $user->id)->orWhere('created_by', $user->id));
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->canManageContent() || $this->host_id === $user->id || $this->created_by === $user->id
            || ($this->isRoutine() && $user->canHostAttendance());
    }

    public function isRoutine(): bool
    {
        return $this->attendance_schedule_id !== null;
    }

    public function requiresGps(): bool
    {
        return in_array($this->checkin_mode, ['gps', 'qr'], true);
    }

    /** Area GPS yang sah untuk sesi ini. */
    public function allowedLocations(): Collection
    {
        return AttendanceLocation::mapped()->when($this->location_ids, fn ($q) => $q->whereIn('id', $this->location_ids))->orderBy('name')->get();
    }

    public function locationLabel(): ?string
    {
        if ($this->location) {
            return $this->location;
        }
        if (! $this->location_ids) {
            return $this->requiresGps() ? 'Semua area' : null;
        }

        return AttendanceLocation::whereIn('id', $this->location_ids)->orderBy('name')->pluck('name')->join(', ');
    }

    /** Apakah karyawan ini termasuk peserta sasaran (atau sesi terbuka untuk semua). */
    public function targets(User $user): bool
    {
        $targets = $this->target_departments ?: [];

        return ! $targets || in_array(Str::upper((string) $user->department), array_map([Str::class, 'upper'], $targets), true);
    }

    /* ------------------------------------------------------------ token */

    public function token(?int $window = null): string
    {
        $window ??= intdiv(now()->timestamp, self::TOKEN_PERIOD);
        $hash = hash_hmac('sha256', $this->code.'|'.$window, $this->secret);

        return str_pad((string) (hexdec(substr($hash, 0, 8)) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function tokenValid(?string $token): bool
    {
        if (! $token || ! preg_match('/^\d{6}$/', $token)) {
            return false;
        }
        $window = intdiv(now()->timestamp, self::TOKEN_PERIOD);
        for ($i = 0; $i <= self::TOKEN_GRACE; $i++) {
            if (hash_equals($this->token($window - $i), $token)) {
                return true;
            }
        }

        return false;
    }

    public function secondsToNextToken(): int
    {
        return self::TOKEN_PERIOD - (now()->timestamp % self::TOKEN_PERIOD);
    }

    public function checkinUrl(bool $withToken = true): string
    {
        return route('attendance.checkin', array_filter(['code' => $this->code, 't' => $withToken && $this->checkin_mode === 'qr' ? $this->token() : null]));
    }

    /* ------------------------------------------------------- peserta */

    /** Karyawan sasaran (berdasarkan seksi). Kosong bila sesi terbuka untuk semua. */
    public function expectedUsers(): Collection
    {
        $targets = $this->target_departments ?: [];
        if (! $targets) {
            return new Collection;
        }

        return User::where('is_active', true)->whereIn('department', $targets)->orderBy('department')->orderBy('name')->get();
    }

    public function isLateAt(\DateTimeInterface $time): bool
    {
        return $this->late_after !== null && $time > $this->starts_at->copy()->addMinutes($this->late_after);
    }

    public function displayTitle(): string
    {
        return $this->type->name.($this->title ? ' — '.$this->title : '');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AttendanceSchedule::class, 'attendance_schedule_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AttendanceType::class, 'attendance_type_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
