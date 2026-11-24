<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentariLog extends Model
{
    public const UPDATED_AT = null;

    public const SOURCES = [
        'guide' => 'Otak bawaan',
        'knowledge' => 'Pengetahuan',
        'ai' => 'AI',
        'fallback' => 'Belum terjawab',
    ];

    protected $fillable = ['user_id', 'question', 'normalized', 'intent', 'source', 'provider', 'answered', 'helpful', 'ms', 'resolved_at'];

    protected function casts(): array
    {
        return ['answered' => 'boolean', 'helpful' => 'boolean', 'resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
