<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Paket soal di bank soal (mis. "PM SCANIA", "BMC2026"). */
class QuestionSet extends Model
{
    protected $fillable = ['name', 'description', 'created_by'];

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeQuestions(): HasMany
    {
        return $this->questions()->where('is_active', true);
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class)->withPivot('take');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
