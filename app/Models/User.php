<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory;

    public const ROLES = [
        'user' => 'Karyawan',
        'gl' => 'GL / Host absensi',
        'she' => 'Admin SHE',
        'admin' => 'Administrator',
    ];

    protected $fillable = [
        'nrp', 'name', 'department', 'position', 'phone', 'role', 'is_active',
        'grade', 'supervisor', 'supervisor_nrp', 'location', 'program',
    ];

    protected $hidden = ['pin', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'announcements_seen_at' => 'datetime',
            'pin' => 'hashed',
        ];
    }

    /** Laravel memakai kolom ini sebagai "password" untuk Auth. */
    public function getAuthPasswordName(): string
    {
        return 'pin';
    }

    public static function normalizeNrp(?string $nrp): string
    {
        return Str::upper(preg_replace('/\s+/', '', (string) $nrp));
    }

    public function setNrpAttribute($value): void
    {
        $this->attributes['nrp'] = self::normalizeNrp($value);
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = Str::upper(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }

    public function setSupervisorAttribute($value): void
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));
        $this->attributes['supervisor'] = $value === '' ? null : Str::upper($value);
    }

    /**
     * Cocokkan nama GL wali dengan karyawan terdaftar (ejaan di Excel kadang
     * berbeda, mis. SETIYAWAN vs SETIAWAN) lalu isi supervisor_nrp.
     */
    public function resolveSupervisor(?\Illuminate\Support\Collection $candidates = null): void
    {
        if (! $this->supervisor) {
            $this->supervisor_nrp = null;

            return;
        }
        $candidates ??= static::query()->where('is_active', true)->get(['nrp', 'name']);
        $target = self::nameKey($this->supervisor);
        $best = null;
        $bestScore = 0.0;
        foreach ($candidates as $c) {
            if ($c->nrp === $this->nrp) {
                continue;
            }
            $key = self::nameKey($c->name);
            if ($key === $target) {
                [$best, $bestScore] = [$c, 100.0];
                break;
            }
            similar_text($key, $target, $pct);
            // Nama di Excel kadang disingkat: "MUHAMMAD RISWAN A" vs "MUHAMMAD RISWAN ARIADI"
            if (str_starts_with($key, $target) || str_starts_with($target, $key)) {
                $pct = max($pct, 92.0);
            }
            if ($pct > $bestScore) {
                [$best, $bestScore] = [$c, $pct];
            }
        }
        $this->supervisor_nrp = $bestScore >= 88 ? $best->nrp : null;
    }

    public static function nameKey(string $name): string
    {
        return Str::of($name)->upper()->replaceMatches('/[^A-Z ]/', '')->squish()->toString();
    }

    public function isActivated(): bool
    {
        return $this->pin !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canManageContent(): bool
    {
        return in_array($this->role, ['admin', 'she'], true);
    }

    /** Boleh membuat & memimpin sesi absensi (P5M, safety talk). */
    public function canHostAttendance(): bool
    {
        return in_array($this->role, ['admin', 'she', 'gl'], true);
    }

    public function firstName(): string
    {
        return Str::title(Str::before($this->name.' ', ' '));
    }

    public function displayName(): string
    {
        return Str::title(Str::lower($this->name));
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return Str::upper(Str::substr($parts[0] ?? '', 0, 1).Str::substr($parts[1] ?? '', 0, 1));
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function supervisorUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_nrp', 'nrp');
    }

    public function mentees(): HasMany
    {
        return $this->hasMany(User::class, 'supervisor_nrp', 'nrp');
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(AppLink::class, 'favorites', 'user_id', 'app_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(AppVisit::class);
    }
}
