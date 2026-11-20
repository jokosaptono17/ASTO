<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penukaran poin → kupon QR yang ditunjukkan ke admin saat mengambil hadiah. */
class Redemption extends Model
{
    public const STATUSES = [
        'pending' => ['label' => 'Siap diambil', 'chip' => 'chip-penting', 'icon' => 'ri-qr-code-line'],
        'fulfilled' => ['label' => 'Sudah diambil', 'chip' => 'chip-tayang', 'icon' => 'ri-checkbox-circle-line'],
        'cancelled' => ['label' => 'Dibatalkan', 'chip' => 'chip-nonaktif', 'icon' => 'ri-close-circle-line'],
    ];

    protected function casts(): array
    {
        return ['fulfilled_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
