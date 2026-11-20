<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Satu baris buku besar poin. Tidak pernah diubah atau dihapus. */
class PointTransaction extends Model
{
    public const UPDATED_AT = null;

    public const KINDS = [
        'earn' => ['label' => 'Otomatis', 'icon' => 'ri-flashlight-line'],
        'grant' => ['label' => 'Reward event', 'icon' => 'ri-trophy-line'],
        'redeem' => ['label' => 'Penukaran', 'icon' => 'ri-gift-line'],
        'refund' => ['label' => 'Pengembalian', 'icon' => 'ri-refund-2-line'],
        'adjust' => ['label' => 'Penyesuaian', 'icon' => 'ri-equalizer-line'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function icon(): string
    {
        return match ($this->rule) {
            'attend' => 'ri-team-line',
            'speaker' => 'ri-mic-line',
            'streak7' => 'ri-fire-line',
            default => self::KINDS[$this->kind]['icon'] ?? 'ri-coin-line',
        };
    }
}
