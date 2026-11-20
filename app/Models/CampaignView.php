<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignView extends Model
{
    public $timestamps = false;

    protected $fillable = ['campaign_id', 'user_id', 'views', 'first_seen_at', 'last_seen_at', 'acknowledged_at', 'dismissed_at'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'acknowledged_at' => 'datetime', 'dismissed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
