<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PointRule extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['points', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'points' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('point-rules'));
    }

    /** Poin aturan aktif, 0 bila nonaktif. */
    public static function value(string $key): int
    {
        $rules = Cache::remember('point-rules', 300, fn () => static::all()->keyBy('key'));
        $rule = $rules->get($key);

        return $rule && $rule->is_active ? max(0, $rule->points) : 0;
    }
}
