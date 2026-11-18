<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentariFaq extends Model
{
    protected $fillable = ['question', 'keywords', 'answer', 'link_label', 'link_url', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return list<string> */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map(fn ($k) => mb_strtolower(trim($k)), explode(',', $this->keywords))));
    }
}
