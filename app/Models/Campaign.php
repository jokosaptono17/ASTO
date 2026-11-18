<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Campaign extends Model
{
    public const FORMATS = [
        'card' => ['label' => 'Kartu (4:5)', 'width' => 864, 'height' => 1080],
        'banner' => ['label' => 'Banner lebar (2:1)', 'width' => 1440, 'height' => 720],
    ];

    protected $fillable = [
        'title', 'subtitle', 'format', 'image_path', 'link_url', 'link_label',
        'starts_at', 'ends_at', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')->latest('id');
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

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function clickLogs(): HasMany
    {
        return $this->hasMany(CampaignClick::class);
    }
}
