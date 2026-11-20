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
        'popup' => ['label' => 'Pop-up (3:4)', 'width' => 900, 'height' => 1200],
    ];

    /** Seberapa sering pop-up muncul untuk satu karyawan. */
    public const POPUP_FREQUENCIES = [
        'once' => ['label' => 'Sekali saja', 'hint' => 'Muncul satu kali per karyawan (di HP mana pun)'],
        'daily' => ['label' => 'Sekali sehari', 'hint' => 'Muncul lagi keesokan harinya sampai masa tayang habis'],
        'session' => ['label' => 'Setiap buka aplikasi', 'hint' => 'Muncul setiap kali AstoOne dibuka — untuk info darurat'],
    ];

    protected $fillable = [
        'title', 'subtitle', 'format', 'image_path', 'link_url', 'link_label',
        'starts_at', 'ends_at', 'is_active', 'sort_order',
        'popup_frequency', 'popup_seconds', 'popup_ack', 'target_departments',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'popup_ack' => 'boolean',
            'popup_seconds' => 'integer',
            'target_departments' => 'array',
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

    public function isPopup(): bool
    {
        return $this->format === 'popup';
    }

    public function targets(User $user): bool
    {
        return ! $this->target_departments || in_array($user->department, $this->target_departments, true);
    }

    /**
     * Pop-up yang perlu ditampilkan ke karyawan saat ini (paling banyak satu per buka aplikasi).
     * Urutan: yang wajib dibaca dulu, lalu urutan admin, lalu terbaru.
     */
    public static function popupFor(User $user): ?self
    {
        $popups = static::live()->where('format', 'popup')->get()->filter(fn (self $c) => $c->targets($user));
        if ($popups->isEmpty()) {
            return null;
        }
        $views = CampaignView::where('user_id', $user->id)->whereIn('campaign_id', $popups->pluck('id'))->get()->keyBy('campaign_id');

        return $popups->sortBy(fn (self $c) => [$c->popup_ack ? 0 : 1, $c->sort_order, -$c->id])
            ->first(function (self $c) use ($views) {
                $v = $views->get($c->id);
                if (! $v) {
                    return true;
                }
                if ($c->popup_ack) {
                    return $v->acknowledged_at === null;   // tetap muncul sampai dikonfirmasi
                }
                if ($v->dismissed_at) {
                    return false;
                }

                return match ($c->popup_frequency) {
                    'daily' => ! $v->last_seen_at?->isToday(),
                    'session' => true,                   // dibatasi sekali per sesi browser di sisi klien
                    default => false,                    // once
                };
            });
    }

    /** Karyawan sasaran pop-up (untuk statistik "sudah membaca"). */
    public function audience()
    {
        return User::where('is_active', true)->whereNotNull('pin')
            ->when($this->target_departments, fn ($q) => $q->whereIn('department', $this->target_departments));
    }

    public function views(): HasMany
    {
        return $this->hasMany(CampaignView::class);
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
