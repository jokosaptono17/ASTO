<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointGrantRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = ['point_grant_id', 'user_id', 'points', 'note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(PointGrant::class, 'point_grant_id');
    }
}
