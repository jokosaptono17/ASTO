<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'description'];

    public static function record(string $action, string $description): void
    {
        static::create(['user_id' => auth()->id(), 'action' => $action, 'description' => mb_substr($description, 0, 255)]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
