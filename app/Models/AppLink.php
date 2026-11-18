<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Satu aplikasi/tautan di portal (tabel apps).
 * placement: featured = ikon besar baris atas beranda, grid = ikon kecil
 * baris kedua, list = hanya di halaman Semua Aplikasi.
 */
class AppLink extends Model
{
    protected $table = 'apps';

    public const PLACEMENTS = [
        'featured' => 'Utama (baris atas beranda)',
        'grid' => 'Pintasan (baris kedua beranda)',
        'list' => 'Hanya di daftar aplikasi',
    ];

    protected $fillable = [
        'category_id', 'name', 'tagline', 'description', 'howto', 'pic_name', 'pic_phone', 'url', 'icon_type', 'icon', 'color', 'placement',
        'badge', 'new_tab', 'is_active', 'health_check', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'new_tab' => 'boolean',
            'is_active' => 'boolean',
            'health_check' => 'boolean',
            'health_checked_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function iconUrl(): ?string
    {
        return $this->icon_type === 'image' ? Storage::disk('public')->url($this->icon) : null;
    }

    /** Tautan WhatsApp ke PIC aplikasi (null bila nomor kosong). */
    public function picWhatsapp(?string $text = null): ?string
    {
        $number = preg_replace('/\D+/', '', (string) $this->pic_phone);
        if ($number === '') {
            return null;
        }
        $number = str_starts_with($number, '0') ? '62'.substr($number, 1) : $number;

        return 'https://wa.me/'.$number.($text ? '?text='.rawurlencode($text) : '');
    }

    public function host(): string
    {
        return (string) parse_url($this->url, PHP_URL_HOST);
    }

    /** Data ringkas untuk JavaScript (pencarian, Mentari). */
    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'category' => $this->category?->name,
            'icon' => $this->icon_type === 'remix' ? $this->icon : null,
            'image' => $this->iconUrl(),
            'color' => $this->color,
            'href' => route('apps.go', $this),
            'new_tab' => $this->new_tab,
            'status' => $this->health_check ? $this->health_status : null,
        ];
    }
}
