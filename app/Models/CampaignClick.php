<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignClick extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['campaign_id', 'user_id'];
}
