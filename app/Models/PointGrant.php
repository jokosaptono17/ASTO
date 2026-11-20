<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pemberian poin untuk event (juara, improvement, temuan hazard, …). */
class PointGrant extends Model
{
    public const STATUSES = [
        'draft' => ['label' => 'Draf', 'chip' => 'chip-berakhir'],
        'pending' => ['label' => 'Menunggu persetujuan', 'chip' => 'chip-penting'],
        'approved' => ['label' => 'Disetujui · poin terkirim', 'chip' => 'chip-tayang'],
        'rejected' => ['label' => 'Ditolak', 'chip' => 'chip-nonaktif'],
    ];

    protected $fillable = ['title', 'description'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function recalc(): void
    {
        $this->total_points = (int) $this->recipients()->sum('points');
        $this->save();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(PointGrantRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
