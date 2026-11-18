<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppVisit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['app_id', 'user_id'];

    public function app(): BelongsTo
    {
        return $this->belongsTo(AppLink::class, 'app_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
