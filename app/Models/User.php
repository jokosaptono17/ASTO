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
        'she' => 'Admin SHE',
        'admin' => 'Administrator',
    ];

    protected $fillable = [
        'nrp', 'name', 'department', 'position', 'phone', 'role', 'is_active',
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

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(AppLink::class, 'favorites', 'user_id', 'app_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(AppVisit::class);
    }
}
