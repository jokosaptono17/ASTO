<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Jadwal rutin (P5M tiap shift, Safety Talk Sabtu, …). Sesi hari ini dibuat
 * otomatis saat pertama kali dibutuhkan — tidak perlu cron, GL tidak perlu
 * membuat sesi setiap hari.
 */
class AttendanceSchedule extends Model
{
    public const DAYS = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 0 => 'Min'];

    protected $fillable = [
        'attendance_type_id', 'title', 'shift', 'days', 'open_time', 'close_time', 'late_after',
        'location_ids', 'target_departments', 'company', 'is_active',
    ];

    protected function casts(): array
    {
        return ['days' => 'array', 'location_ids' => 'array', 'target_departments' => 'array', 'is_active' => 'boolean'];
    }

    public function runsOn(Carbon $date): bool
    {
        return in_array($date->dayOfWeek, array_map('intval', $this->days ?? []), true);
    }

    public function daysLabel(): string
    {
        $days = array_map('intval', $this->days ?? []);
        if (count($days) === 7) {
            return 'Setiap hari';
        }

        return collect(self::DAYS)->filter(fn ($l, $d) => in_array($d, $days, true))->join(', ');
    }

    public function window(): string
    {
        return substr($this->open_time, 0, 5).'–'.substr($this->close_time, 0, 5);
    }

    /** Atribut sesi untuk tanggal tertentu. */
    public function sessionAttributes(Carbon $date): array
    {
        $open = Carbon::parse($date->toDateString().' '.substr($this->open_time, 0, 5));
        $close = Carbon::parse($date->toDateString().' '.substr($this->close_time, 0, 5));
        if ($close->lte($open)) {
            $close->addDay();   // jadwal melewati tengah malam
        }

        return [
            'attendance_type_id' => $this->attendance_type_id,
            'title' => $this->title,
            'date' => $date->copy()->startOfDay(),
            'shift' => $this->shift,
            'starts_at' => $open,
            'opens_at' => $open,
            'closes_at' => $close,
            'late_after' => $this->late_after,
            'location_ids' => $this->location_ids ?: null,
            'target_departments' => $this->target_departments ?: null,
            'company' => $this->company,
            'checkin_mode' => 'gps',
        ];
    }

    /** Pastikan sesi rutin hari ini (dan kemarin, untuk jadwal lewat tengah malam) sudah ada. */
    public static function ensureSessions(bool $force = false): void
    {
        $today = today();
        if (! $force && ! Cache::add('attendance.ensured.'.$today->toDateString(), true, 600)) {
            return;
        }
        $schedules = static::where('is_active', true)->get();
        foreach ([$today->copy()->subDay(), $today] as $date) {
            foreach ($schedules as $schedule) {
                if (! $schedule->runsOn($date)) {
                    continue;
                }
                $attrs = $schedule->sessionAttributes($date);
                if ($attrs['closes_at']->isPast()) {
                    continue;   // tidak perlu membuat sesi yang sudah lewat
                }
                try {
                    AttendanceSession::firstOrCreate(['attendance_schedule_id' => $schedule->id, 'date' => $attrs['date']], $attrs);
                } catch (QueryException) {
                    // dibuat bersamaan oleh permintaan lain — abaikan
                }
            }
        }
    }

    /** Setelah jadwal diubah: sesuaikan sesi hari ini yang belum selesai. */
    public function syncUpcoming(): void
    {
        $this->sessions()->whereDate('date', '>=', today()->subDay())->whereNull('closed_at')->where('closes_at', '>', now())->get()
            ->each(function (AttendanceSession $s) {
                if (! $this->is_active || ! $this->runsOn($s->date)) {
                    $s->records()->doesntExist() && $s->delete();

                    return;
                }
                $s->fill($this->sessionAttributes($s->date))->save();
            });
        Cache::forget('attendance.ensured.'.today()->toDateString());
        static::ensureSessions(true);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AttendanceType::class, 'attendance_type_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
