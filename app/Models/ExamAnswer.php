<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = ['exam_attempt_id', 'question_id', 'position', 'option_order', 'choice', 'is_correct', 'flagged', 'answered_at'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'flagged' => 'boolean', 'answered_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** Huruf tampilan (A, B, …) untuk kunci opsi asli pada lembar peserta ini. */
    public function displayLetter(?string $original): ?string
    {
        if ($original === null) {
            return null;
        }
        $pos = strpos($this->option_order, $original);

        return $pos === false ? null : Question::LETTERS[$pos];
    }
}
