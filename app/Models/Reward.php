<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Hadiah di katalog Poin HEBAT (disediakan tim admin). */
class Reward extends Model
{
    public const CATEGORIES = [
        'merchandise' => ['label' => 'Merchandise', 'icon' => 'ri-t-shirt-2-line'],
        'apd' => ['label' => 'APD & perlengkapan', 'icon' => 'ri-shield-user-line'],
        'makanan' => ['label' => 'Makanan & minuman', 'icon' => 'ri-cup-line'],
        'voucher' => ['label' => 'Voucher', 'icon' => 'ri-coupon-3-line'],
        'pengalaman' => ['label' => 'Pengalaman', 'icon' => 'ri-star-smile-line'],
    ];

    protected $fillable = ['name', 'description', 'category', 'cost', 'stock', 'per_user_limit', 'pickup_note', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'cost' => 'integer', 'stock' => 'integer', 'per_user_limit' => 'integer'];
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('cost');
    }

    public function inStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function categoryMeta(): array
    {
        return self::CATEGORIES[$this->category] ?? self::CATEGORIES['merchandise'];
    }

    /** Berapa kali karyawan ini sudah menukar (tidak termasuk yang dibatalkan). */
    public function redeemedBy(User $user): int
    {
        return $this->redemptions()->where('user_id', $user->id)->where('status', '!=', 'cancelled')->count();
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }
}
