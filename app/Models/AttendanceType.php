<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceType extends Model
{
    protected $fillable = ['name', 'color', 'icon', 'track_fatigue', 'allow_speaker', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['track_fatigue' => 'boolean', 'allow_speaker' => 'boolean', 'is_active' => 'boolean'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
