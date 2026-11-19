<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const FATIGUE = [
        'fit' => ['label' => 'Fit to Work', 'tone' => 'neon', 'icon' => 'ri-checkbox-circle-fill'],
        'coaching' => ['label' => 'Coaching Atasan', 'tone' => 'hivis', 'icon' => 'ri-user-voice-fill'],
        'risk' => ['label' => 'Fatigue Risk', 'tone' => 'alarm', 'icon' => 'ri-alarm-warning-fill'],
    ];

    public const GEO = [
        'inside' => ['label' => 'Di area', 'tone' => 'neon', 'icon' => 'ri-map-pin-2-fill'],
        'unverified' => ['label' => 'Lokasi tidak terverifikasi', 'tone' => 'hivis', 'icon' => 'ri-map-pin-line'],
        'none' => ['label' => 'Tanpa cek lokasi', 'tone' => 'mist', 'icon' => 'ri-map-pin-line'],
    ];

    public const METHODS = [
        'gps' => 'Aplikasi + GPS',
        'qr' => 'Scan QR',
        'kode' => 'Kode layar',
        'open' => 'Aplikasi',
        'manual' => 'Dicatat host',
    ];

    protected $fillable = [
        'attendance_session_id', 'user_id', 'nrp', 'name', 'position', 'department', 'company', 'role',
        'sleep_at', 'wake_at', 'sleep_minutes', 'fatigue', 'checked_in_at', 'is_late', 'method', 'recorded_by', 'note',
        'geo_status', 'attendance_location_id', 'latitude', 'longitude', 'accuracy', 'distance_m', 'device_id',
    ];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'is_late' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    /**
     * Durasi tidur & status fatigue (aturan sama dengan form P5M lama):
     * ≥ 6 jam Fit to Work · 5–6 jam Coaching Atasan · < 5 jam Fatigue Risk.
     *
     * @return array{0:int,1:string}|null
     */
    public static function classifySleep(?string $sleepAt, ?string $wakeAt): ?array
    {
        if (! $sleepAt || ! $wakeAt) {
            return null;
        }
        [$sh, $sm] = array_map('intval', explode(':', $sleepAt));
        [$wh, $wm] = array_map('intval', explode(':', $wakeAt));
        $minutes = ($wh * 60 + $wm) - ($sh * 60 + $sm);
        if ($minutes <= 0) {
            $minutes += 24 * 60;
        }
        $status = $minutes >= 360 ? 'fit' : ($minutes >= 300 ? 'coaching' : 'risk');

        return [$minutes, $status];
    }

    public function sleepLabel(): ?string
    {
        if ($this->sleep_minutes === null) {
            return null;
        }
        $h = intdiv($this->sleep_minutes, 60);
        $m = $this->sleep_minutes % 60;

        return $h.' jam'.($m ? ' '.$m.' mnt' : '');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'attendance_location_id');
    }

    /** Keterangan lokasi singkat, mis. "WS 25" atau "±80 m dari Quarry". */
    public function geoLabel(): string
    {
        return match ($this->geo_status) {
            'inside' => $this->location?->name ?? 'Di area',
            'unverified' => $this->distance_m !== null && $this->location
                ? 'Belum pasti · '.$this->distance_m.' m dari '.$this->location->name
                : ($this->latitude !== null ? 'Belum pasti · GPS ±'.$this->accuracy.' m' : 'Belum pasti · GPS tidak aktif'),
            default => $this->method === 'manual' ? 'Dicatat host' : '–',
        };
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Data ringkas untuk daftar live. */
    public function toLive(): array
    {
        return [
            'id' => $this->id,
            'nrp' => $this->nrp,
            'name' => \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($this->name)),
            'initials' => collect(preg_split('/\s+/', trim($this->name)))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join(''),
            'department' => $this->department,
            'position' => $this->position,
            'role' => $this->role,
            'fatigue' => $this->fatigue,
            'sleep' => $this->sleepLabel(),
            'late' => $this->is_late,
            'method' => self::METHODS[$this->method] ?? $this->method,
            'geo' => $this->geo_status,
            'place' => $this->geoLabel(),
            'accuracy' => $this->accuracy,
            'time' => $this->checked_in_at->format('H:i'),
            'ts' => $this->checked_in_at->timestamp,
        ];
    }
}
