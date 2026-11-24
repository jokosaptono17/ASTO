<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    public const STATUSES = [
        'in_progress' => ['label' => 'Mengerjakan', 'chip' => 'chip bg-sky-400/15 text-sky-300', 'icon' => 'ri-edit-2-line'],
        'submitted' => ['label' => 'Selesai', 'chip' => 'chip chip-tayang', 'icon' => 'ri-checkbox-circle-line'],
        'disqualified' => ['label' => 'Diskualifikasi', 'chip' => 'chip chip-darurat', 'icon' => 'ri-forbid-2-line'],
    ];

    public const REASONS = [
        'user' => 'Dikumpulkan peserta',
        'timeout' => 'Waktu habis',
        'violations' => 'Batas pelanggaran tercapai',
        'admin' => 'Dikumpulkan admin',
    ];

    protected $fillable = [
        'exam_id', 'user_id', 'attempt_no', 'status', 'finish_reason', 'started_at', 'deadline_at', 'submitted_at',
        'total', 'answered', 'correct', 'score', 'violations', 'violation_log', 'last_seen_at', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'deadline_at' => 'datetime', 'submitted_at' => 'datetime', 'last_seen_at' => 'datetime',
            'violation_log' => 'array', 'score' => 'float',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class)->orderBy('position');
    }

    public function isActive(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isFinished(): bool
    {
        return ! $this->isActive();
    }

    public function passed(): bool
    {
        return $this->status === 'submitted' && $this->score !== null && $this->score >= $this->exam->pass_score;
    }

    /** Lama mengerjakan (detik), dipakai sebagai penentu bila nilai sama. */
    public function durationSeconds(): ?int
    {
        $end = $this->submitted_at ?? ($this->isActive() ? now() : null);

        return $end ? max(0, (int) $this->started_at->diffInSeconds(min($end, $this->deadline_at->copy()->addMinute()))) : null;
    }

    public static function formatDuration(?int $s): string
    {
        if ($s === null) {
            return '–';
        }

        return $s >= 3600 ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    public function secondsLeft(): int
    {
        return max(0, (int) now()->diffInSeconds($this->deadline_at, false));
    }
}
