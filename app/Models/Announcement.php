<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public const LEVELS = [
        'info' => ['label' => 'Info', 'icon' => 'ri-information-fill'],
        'penting' => ['label' => 'Penting', 'icon' => 'ri-error-warning-fill'],
        'darurat' => ['label' => 'Darurat', 'icon' => 'ri-alarm-warning-fill'],
    ];

    protected $fillable = ['title', 'body', 'level', 'link_url', 'is_pinned', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByDesc('is_pinned')->orderByRaw('COALESCE(starts_at, created_at) DESC');
    }

    public function publishedAt()
    {
        return $this->starts_at ?? $this->created_at;
    }

    public function status(): string
    {
        return match (true) {
            ! $this->is_active => 'nonaktif',
            $this->starts_at && $this->starts_at->isFuture() => 'terjadwal',
            $this->ends_at && $this->ends_at->isPast() => 'berakhir',
            default => 'tayang',
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
